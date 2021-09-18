<?php
declare(strict_types=1);

namespace PunktDe\Sylius\NeosIntegration\Service\DataSource;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use PunktDe\Sylius\Api\Dto\Product;
use PunktDe\Sylius\Api\Resource\ProductResource;

class ProductListDataSource extends AbstractDataSource
{
    /**
     * @var ProductResource
     * @Flow\Inject
     */
    protected $products;

    /**
     * @var string
     */
    protected static $identifier = 'PunktDeSyliusNeosIntegration_ProductList';

    /**
     * @param NodeInterface|null $node
     * @param string[] $arguments
     * @return string[]
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {
        $productList = $this->products->getAll();

        $result = [];

        /** @var Product $productDto */
        foreach ($productList as $productDto) {
            $result[] = [
                'value' => $productDto->getIdentifier(),
                'label' => $productDto->getName()
            ];
        }
        return $result;
    }
}
