<?php
declare(strict_types=1);
namespace PunktDe\Sylius\NeosIntegration\Eel;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Http\Factories\UriFactory;
use Neos\Media\Domain\Model\Asset;
use Neos\Utility\Files;
use Psr\Http\Message\UriInterface;
use PunktDe\Sylius\Api\Client;
use PunktDe\Sylius\Api\Dto\Product;
use PunktDe\Sylius\Api\Resource\ProductResource;
use PunktDe\Sylius\NeosIntegration\Service\AssetService;


class ProductHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var Client
     */
    protected $apiClient;

    /**
     * @Flow\Inject
     * @var ProductResource
     */
    protected $product;

    /**
     * @Flow\Inject
     * @var AssetService
     */
    protected $assetService;

    /**
     * @param string $productCode
     * @return Product|null
     */
    public function getProduct(string $productCode = ''): ?Product
    {
        return $this->product->get($productCode);
    }

    /**
     * @param Product $product
     * @param string $imageType
     * @return Asset | null
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\ResourceManagement\Exception
     */
    public function getAsset(Product $product, string $imageType = ''): ?Asset
    {
        return $this->assetService->importSyliusAsset($product, $imageType);
    }

    /**
     * @param Product $product
     * @param string|null $locale
     * @return UriInterface
     */
    public function getProductUri(Product $product, string $locale = ''): UriInterface
    {
        $uri = (new UriFactory)->createUri($this->apiClient->getBaseUri());
        return $uri->withPath(Files::concatenatePaths([($locale === '' ? $product->getDefaultLocale() : $locale), 'products', $product->getSlug($locale)]));
    }

    /**
     * @inheritDoc
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
