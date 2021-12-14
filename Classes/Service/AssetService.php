<?php
declare(strict_types=1);

namespace PunktDe\Sylius\NeosIntegration\Service;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client as HttpClient;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\Exception;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\Tag;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\TagRepository;
use Neos\Media\Domain\Service\AssetService as NeosMediaAssetService;
use Neos\Utility\Files;
use Neos\Utility\MediaTypes;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use PunktDe\Sylius\Api\Client;
use PunktDe\Sylius\Api\Dto\Product;

class AssetService
{
    protected const TAG_NAME_SYLIUS = 'Sylius';

    /**
     * @Flow\Inject
     * @var Client
     */
    protected $apiClient;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var NeosMediaAssetService
     */
    protected $assetService;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ResponseInterface[]
     */
    protected static $shopResourceResponseCache = [];

    /**
     * @param Product $product
     * @param string $imageType
     * @param \DateTime|null $maxLifetimeDate
     * @return Asset|null
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function importSyliusAsset(Product $product, string $imageType = '', \DateTime $maxLifetimeDate = null): ?Asset
    {
        $syliusAssetTag = $this->findOrCreateTag();
        $shopUrl = $this->apiClient->getBaseUri();
        $imagePath = $product->getImagePathByType($imageType);
        $url = Files::concatenatePaths([$shopUrl, 'media/image', $imagePath]);

        $parts = explode('.', $imagePath);
        $fileExtension = end($parts);
        $fileName = sprintf('%s-%s.%s', $product->getCode(), $imageType === '' ? 'default' : $imageType, $fileExtension);

        /** @var Image $availableImage */
        $availableImage = $this->assetRepository->findBySearchTermOrTags($fileName)->getFirst();

        // If there is an image
        if ($availableImage instanceof Image) {

            // If maximum lifetime is specified, check the image against this date
            if ($maxLifetimeDate instanceof \DateTime && $availableImage->getLastModified() < $maxLifetimeDate) {
                return $availableImage;
            }

            // If the local image date is newer then the last modified date of the remote
            if ($availableImage->getLastModified() > $this->getShopResourceLastModified($url)) {
                return $availableImage;
            }

            // If there is an outdated image and the resource exists in the shop, fetch it and replace the local resource
            if ($this->shopResourceExists($url)) {
                $newResource = $this->resourceManager->importResource($url);
                $oldResource = $availableImage->getResource();
                $this->assetService->replaceAssetResource($availableImage, $newResource);
                $this->resourceManager->deleteResource($oldResource);
                $this->persistenceManager->persistAll();
            }
        }

        // If there is no local image, create it
        if ($availableImage === null && $this->shopResourceExists($url)) {
            $resource = $this->resourceManager->importResource($url);

            $image = new Image($resource);
            $image->getResource()->setFilename($fileName);
            $image->getResource()->setMediaType(MediaTypes::getMediaTypeFromFilename($fileName));
            $image->addTag($syliusAssetTag);
            $this->assetRepository->add($image);

            $this->persistenceManager->persistAll();

            return $image;
        }

        return null;
    }

    /**
     * @return Tag
     * @throws IllegalObjectTypeException
     */
    protected function findOrCreateTag(): Tag
    {
        /** @var Tag $tag */
        $tag = $this->tagRepository->findByLabel(self::TAG_NAME_SYLIUS)->getFirst();

        if ($tag === null) {
            $tag = new Tag(self::TAG_NAME_SYLIUS);
            $this->tagRepository->add($tag);
        }

        return $tag;
    }

    protected function getShopResourceLastModified(string $url): \DateTime
    {
        return empty($this->fetchShopResourceState($url)->getHeader('last-modified')) ? new \DateTime() : new \DateTime($this->fetchShopResourceState($url)->getHeader('last-modified')[0]);
    }

    protected function shopResourceExists(string $url): bool
    {
        if ($this->fetchShopResourceState($url)->getStatusCode() === 200) {
            return true;
        }

        $this->logger->warning(sprintf('Resource %s was not found. HTTP status code: %s', $url, $statusCode), LogEnvironment::fromMethodName(__METHOD__));
        return false;
    }

    protected function fetchShopResourceState(string $url): ResponseInterface
    {
        if (!isset(self::$shopResourceResponseCache[$url])) {
            $client = new HttpClient();
            self::$shopResourceResponseCache[$url] = $client->head($url, ['http_errors' => false]);
        }

        return self::$shopResourceResponseCache[$url];
    }
}
