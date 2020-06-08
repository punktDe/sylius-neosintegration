<?php
declare(strict_types=1);
namespace PunktDe\Sylius\NeosIntegration\Eel;

use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;
use PunktDe\Sylius\Api\Dto\ProductVariant;
use PunktDe\Sylius\Api\Resource\ProductVariantResource;

class ProductVariantHelper implements ProtectedContextAwareInterface
{

    /**
     * @var ProductVariantResource
     * @Flow\Inject
     */
    protected $productVariant;

    /**
     * @param string $productCode
     * @param string $productVariantCode
     * @return ProductVariant|null
     */
    public function getVariant(string $productCode = '', string $productVariantCode = ''): ?ProductVariant
    {
        return $this->productVariant->get($productVariantCode, $productCode);
    }

    /**
     * @inheritDoc
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
