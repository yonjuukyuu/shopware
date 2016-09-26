<?php

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

class TestCase extends \Enlight_Components_Test_TestCase
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Converter
     */
    protected $converter;

    protected function setUp()
    {
        $this->helper = new Helper();
        $this->converter = new Converter();
        parent::setUp();
    }

    protected function tearDown()
    {
        $this->helper->cleanUp();
        parent::tearDown();
    }

    /**
     * @param array $products
     * @param array $expectedNumbers
     * @param Category $category
     * @param ConditionInterface[] $conditions
     * @param FacetInterface[] $facets
     * @param SortingInterface[] $sortings
     * @param null $context
     * @param array $configs
     * @return ProductNumberSearchResult
     */
    protected function search(
        $products,
        $expectedNumbers,
        $category = null,
        $conditions = array(),
        $facets = array(),
        $sortings = array(),
        $context = null,
        array $configs = []
    ) {
        if ($context === null) {
            $context = $this->getContext();
        }

        if ($category === null) {
            $category = $this->helper->createCategory();
        }

        $config = Shopware()->Container()->get('config');
        $originals = [];
        foreach ($configs as $key => $value) {
            $originals[$key] = $config->get($key);
            $config->offsetSet($key, $value);
        }

        $this->createProducts($products, $context, $category);

        $this->helper->refreshSearchIndexes($context->getShop());

        $criteria = new Criteria();

        $this->addCategoryBaseCondition($criteria, $category, $conditions, $context);

        $this->addConditions($criteria, $conditions);

        $this->addFacets($criteria, $facets);

        $this->addSortings($criteria, $sortings);

        $criteria->offset(0)->limit(4000);

        $search = Shopware()->Container()->get('shopware_search.product_number_search');

        $result = $search->search($criteria, $context);

        foreach ($originals as $key => $value) {
            $config->offsetSet($key, $value);
        }

        $this->assertSearchResult($result, $expectedNumbers);
        return $result;
    }

    /**
     * @param Criteria $criteria
     * @param Category $category
     * @param $conditions
     * @param ShopContext $context
     */
    protected function addCategoryBaseCondition(
        Criteria $criteria,
        Category $category,
        $conditions,
        ShopContext $context
    ) {
        if ($category) {
            $criteria->addBaseCondition(
                new CategoryCondition(array($category->getId()))
            );
        }
    }

    /**
     * @param Criteria $criteria
     * @param ConditionInterface[] $conditions
     */
    protected function addConditions(Criteria $criteria, $conditions)
    {
        foreach ($conditions as $condition) {
            $criteria->addCondition($condition);
        }
    }

    /**
     * @param Criteria $criteria
     * @param FacetInterface[] $facets
     */
    protected function addFacets(Criteria $criteria, $facets)
    {
        foreach ($facets as $facet) {
            $criteria->addFacet($facet);
        }
    }

    /**
     * @param Criteria $criteria
     * @param SortingInterface[] $sortings
     */
    protected function addSortings(Criteria $criteria, $sortings)
    {
        foreach ($sortings as $sorting) {
            $criteria->addSorting($sorting);
        }
    }

    /**
     * @param $products
     * @param ShopContext $context
     * @param Category $category
     * @return Article[]
     */
    public function createProducts($products, ShopContext $context, Category $category)
    {
        $articles = array();
        foreach ($products as $number => $additionally) {
            $articles[] = $this->createProduct(
                $number,
                $context,
                $category,
                $additionally
            );
        }
        return $articles;
    }

    /**
     * @param $number
     * @param ShopContext $context
     * @param Category $category
     * @param $additionally
     * @return Article
     */
    protected function createProduct(
        $number,
        ShopContext $context,
        Category $category,
        $additionally
    ) {
        $data = $this->getProduct(
            $number,
            $context,
            $category,
            $additionally
        );
        return $this->helper->createArticle($data);
    }

    /**
     * @param ProductNumberSearchResult $result
     * @param $expectedNumbers
     */
    protected function assertSearchResult(
        ProductNumberSearchResult $result,
        $expectedNumbers
    ) {
        $numbers = array_map(function (BaseProduct $product) {
            return $product->getNumber();
        }, $result->getProducts());

        foreach ($numbers as $number) {
            $this->assertContains($number, $expectedNumbers, sprintf("Product with number: `%s` found but not expected", $number));
        }
        foreach ($expectedNumbers as $number) {
            $this->assertContains($number, $numbers, sprintf("Expected product number: `%s` not found", $number));
        }

        $this->assertCount(count($expectedNumbers), $result->getProducts());
        $this->assertEquals(count($expectedNumbers), $result->getTotalCount());
    }

    protected function assertSearchResultSorting(
        ProductNumberSearchResult $result,
        $expectedNumbers
    ) {
        $productResult = array_values($result->getProducts());

        /** @var BaseProduct $product */
        foreach ($productResult as $index => $product) {
            $expectedProduct = $expectedNumbers[$index];

            $this->assertEquals(
                $expectedProduct,
                $product->getNumber(),
                sprintf(
                    'Expected %s at search result position %s, but got product %s',
                    $expectedProduct, $index, $product->getNumber()
                )
            );
        }
    }

    /**
     * @return TestContext
     */
    protected function getContext()
    {
        $tax = $this->helper->createTax();
        $customerGroup = $this->helper->createCustomerGroup();

        $shop = $this->helper->getShop();

        return $this->helper->createContext(
            $customerGroup,
            $shop,
            array($tax)
        );
    }

    /**
     * @param $number
     * @param ShopContext $context
     * @param Category $category
     * @param null $additionally
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additionally = null
    ) {
        $product = $this->helper->getSimpleProduct(
            $number,
            array_shift($context->getTaxRules()),
            $context->getCurrentCustomerGroup()
        );
        $product['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];

        if ($category) {
            $product['categories'] = array(
                array('id' => $category->getId())
            );
        }

        return $product;
    }

    /**
     * @return \Shopware\Bundle\StoreFrontBundle\Struct\Customer\Group
     */
    public function getEkCustomerGroup()
    {
        return $this->converter->convertCustomerGroup(
            Shopware()->Container()->get('models')->find('Shopware\Models\Customer\Group', 1)
        );
    }
}
