<?php
declare(strict_types=1);

namespace PunktDe\Sylius\NeosIntegration\Service\DataSource;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use PunktDe\Sylius\Api\Dto\Product;
use PunktDe\Sylius\Api\Dto\ProductVariant;
use PunktDe\Sylius\Api\Exception\SyliusApiException;
use PunktDe\Sylius\Api\Resource\ProductResource;
use PunktDe\Sylius\Api\Resource\ProductVariantResource;

class ProductVariantDataSource extends AbstractDataSource
{

    /**
     * @Flow\Inject
     * @var ProductResource
     */
    protected $productResource;

    /**
     * @Flow\Inject
     * @var ProductVariantResource
     */
    protected $productVariantResource;

    /**
     * @var string
     */
    protected static $identifier = 'PunktDeSyliusNeosIntegration_ProductVariantList';

    /**
     * @param NodeInterface|null $node
     * @param string[] $arguments
     * @return string[]
     * @throws SyliusApiException
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $syliusProductCode = $arguments['syliusProduct'] ?? '';
        if ($syliusProductCode === '' || str_starts_with($syliusProductCode, 'ClientEval')) {
            return [];
        }

        /** @var Product $product */
        $product = $this->productResource->get($syliusProductCode);

        $result = [];

        foreach ($product->getVariants() as $productVariant) {
            $productVariant = $this->productVariantResource->get($productVariant);

            /** @var ProductVariant $productVariant */
            $result[] = [
                'value' => $productVariant->getIdentifier(),
                'label' => $productVariant->getName()
            ];
        }
        return $result;
    }
}
