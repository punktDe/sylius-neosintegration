<?php
namespace PunktDe\Sylius\NeosIntegration\Service\DataSource;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use PunktDe\Sylius\Api\Dto\ProductVariant;
use PunktDe\Sylius\Api\Exception\SyliusApiException;
use PunktDe\Sylius\Api\Resource\ProductVariantResource;

class ProductVariantDataSource extends AbstractDataSource
{

    /**
     * @var ProductVariantResource
     * @Flow\Inject
     */
    protected $productVariant;

    /**
     * @var string
     */
    protected static $identifier = 'PunktDeSyliusNeosItegration_ProductVariantList';

    /**
     * @param NodeInterface|null $node
     * @param string[] $arguments
     * @return string[]
     * @throws SyliusApiException
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $syliusProductCode = $arguments['syliusProduct'] ?? '';
        if($syliusProductCode === '') {
            return [];
        }

        $result = [];
        $productVariants = $this->productVariant->getAll([], 100, [], $syliusProductCode);
        foreach ($productVariants as $productVariant) { /** @var ProductVariant $productVariant */
            $result[] = [
                'value' => $productVariant->getIdentifier(),
                'label' => $productVariant->getName()
            ];
        }
        return $result;
    }
}
