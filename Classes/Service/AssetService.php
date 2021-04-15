<?php
declare(strict_types=1);

namespace PunktDe\Sylius\NeosIntegration\Service;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client as HttpClient;
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
     * @var NNeosMediaAssetService
     */
    protected $assetService;

    /**
     * @param Product $product
     * @param string $imageType
     * @param \DateTime|null $maxLifetimeDate
     * @return Asset
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function importSyliusAsset(Product $product, string $imageType = '', \DateTime $maxLifetimeDate = null): Asset
    {
        $shopUrl = $this->apiClient->getBaseUri();
        $imagePath = $product->getImagePathByType($imageType);
        $url = Files::concatenatePaths([$shopUrl, 'media/image', $imagePath]);

        $parts = explode('.', $imagePath);
        $fileExtension = end($parts);
        $fileName = sprintf('%s-%s.%s', $product->getCode(), $imageType === '' ? 'default' : $imageType, $fileExtension);

        /** @var Image $availableImage */
        $availableImage = $this->assetRepository->findBySearchTermOrTags($fileName)->getFirst();

        if (isset($availableImage) && $maxLifetimeDate instanceof \DateTime && $availableImage->getLastModified() < $maxLifetimeDate) {

            $client = new HttpClient();
            if ($client->head($url, ['http_errors' => false])->getStatusCode() === 200) {
                $newResource = $this->resourceManager->importResource($url);
                $this->assetService->replaceAssetResource($availableImage, $newResource);
            }
            return $availableImage;
        }

        if (isset($availableImage)) {
            return $availableImage;
        }

        $resource = $this->resourceManager->importResource($url);

        $image = new Image($resource);
        $image->getResource()->setFilename($fileName);
        $image->getResource()->setMediaType(MediaTypes::getMediaTypeFromFilename($fileName));
        $image->addTag($this->findOrCreateTag());
        $this->assetRepository->add($image);

        $this->persistenceManager->persistAll();

        return $image;
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
}
