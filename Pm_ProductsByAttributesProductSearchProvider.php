<?php
/**
 * @author Presta-Module.com <support@presta-module.com>
 * @copyright Presta-Module
 * @license   see file: LICENSE.txt
 *
 *           ____     __  __
 *          |  _ \   |  \/  |
 *          | |_) |  | |\/| |
 *          |  __/   | |  | |
 *          |_|      |_|  |_|
 *
 ****/

if (!defined('_PS_VERSION_')) {
    exit;
}
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\FacetsRendererInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
class Pm_ProductsByAttributesProductSearchProvider implements FacetsRendererInterface, ProductSearchProviderInterface
{
    private $module;
    private $providerModule;
    private $searchProvider;
    private $conf;
    private $fullTreeAlreadyDone = false;
    public function __construct(pm_productsbyattributes $module, array $conf = [])
    {
        $this->module = $module;
        $this->conf = $conf;
    }
    protected function getDefaultProductSearchProvider()
    {

        $provider = null;
        switch (get_class(Context::getContext()->controller)) {
            case 'BestSalesController':
                return new PrestaShop\PrestaShop\Adapter\BestSales\BestSalesProductSearchProvider(
                    Context::getContext()->getTranslator()
                );
            case 'CategoryController':
                if (!$this->conf['fullTree']) {
                    $this->fullTreeAlreadyDone = true;
                }
                require_once _PS_ROOT_DIR_ . '/modules/pm_productsbyattributes/src/Pm_ProductsByAttributesCategoryProvider.php';
                return new Pm_ProductsByAttributesCategoryProvider(
                    Context::getContext()->getTranslator(),
                    new Category(
                        (int)Tools::getValue('id_category'),
                        Context::getContext()->language->id
                    ),
                    $this->module
                );
            case 'ManufacturerController':
                return new PrestaShop\PrestaShop\Adapter\Manufacturer\ManufacturerProductSearchProvider(
                    Context::getContext()->getTranslator(),
                    new Manufacturer(
                        (int)Tools::getValue('id_manufacturer'),
                        Context::getContext()->language->id
                    )
                );
            case 'NewProductsController':
                return new PrestaShop\PrestaShop\Adapter\NewProducts\NewProductsProductSearchProvider(
                    Context::getContext()->getTranslator()
                );
            case 'PricesDropController':
                return new PrestaShop\PrestaShop\Adapter\PricesDrop\PricesDropProductSearchProvider(
                    Context::getContext()->getTranslator()
                );
            case 'IndexController':
                return new PrestaShop\PrestaShop\Adapter\PricesDrop\PricesDropProductSearchProvider(
                    Context::getContext()->getTranslator()
                );
            case 'SearchController':
            case 'IqitSearchSearchiqitModuleFrontController':
            case 'AmbjolisearchjolisearchModuleFrontController':
                return new PrestaShop\PrestaShop\Adapter\Search\SearchProductSearchProvider(
                    Context::getContext()->getTranslator()
                );
            case 'SupplierController':
                return new PrestaShop\PrestaShop\Adapter\Supplier\SupplierProductSearchProvider(
                    Context::getContext()->getTranslator(),
                    new Supplier(
                        (int)Tools::getValue('id_supplier'),
                        Context::getContext()->language->id
                    )
                );
            default:
                break;
        }
        return $provider;
    }
    protected function getProductSearchProvider(ProductSearchQuery $query)
    {

        $providers = Hook::exec(
            'productSearchProvider',
            ['query' => $query],
            null,
            true
        );
        if (!is_array($providers)) {
            $providers = [];
        }

        foreach ($providers as $module_name => $provider) {
            if ($module_name != 'pm_productsbyattributes' && $provider instanceof ProductSearchProviderInterface) {
                if (Validate::isModuleName($module_name)) {
                    $this->providerModule = Module::getInstanceByName($module_name);
                }
                return $provider;
            } else {
                $provider = null;
            }
        }

        return $this->getDefaultProductSearchProvider();
    }
    public function renderFacets(
        ProductSearchContext $context,
        ProductSearchResult $result
    ) {
        if (isset($this->searchProvider) && is_object($this->searchProvider) && $this->searchProvider instanceof FacetsRendererInterface) {
            return $this->searchProvider->renderFacets($context, $result);
        }
        return '';
    }
    public function renderActiveFilters(
        ProductSearchContext $context,
        ProductSearchResult $result
    ) {
        if (isset($this->searchProvider) && is_object($this->searchProvider) && $this->searchProvider instanceof FacetsRendererInterface) {
            return $this->searchProvider->renderActiveFilters($context, $result);
        }
        return '';
    }
    public function runQuery(ProductSearchContext $context, ProductSearchQuery $query) {

        $cacheKey = 'search_query_' . $query->getIdCategory() . '_' . $query->getResultsPerPage() . '_' . $query->getPage();
    
        $cachedResult = $this->module->getFromCache($cacheKey);

        if ($cachedResult !== false) {
            $result = new ProductSearchResult();
            $result->setProducts($cachedResult['products']);
            $result->setTotalProductsCount($cachedResult['total']);

            // if (isset($cachedResult['facets'])) {
            //     $result->setFacetCollection($cachedResult['facets']);
            // }

            // var_dump($cachedResult['products']);
            // var_dump($result->getTotalProductsCount());
            // var_dump($cachedResult['total']);
            var_dump('cached loaded');

            return $result;
        }

        // $this->searchProvider = $this->getProductSearchProvider($query);
        $resultsPerPage = (int)$query->getResultsPerPage();

        $page = (int)$query->getPage();
        $provider = $this->getProductSearchProvider($query);

        $result = $provider->runQuery($context, $query);

        // if (!$this->module->isInPerformanceMode($provider)) {
        //     $query->setPage(1);
        //     $countResult = $provider->runQuery($context, $query);
        //     $query->setResultsPerPage((int)$countResult->getTotalProductsCount());
        // }
        // $result = $provider->runQuery($context, $query);
        // if ($encodedSortOrder = Tools::getValue('order')) {
        //     $query->setSortOrder(SortOrder::newFromString(
        //         $encodedSortOrder
        //     ));
        // }
        // if (!$this->module->isInPerformanceMode($provider)) {
        //     $result = $provider->runQuery($context, $query);
        //     $query->setResultsPerPage((int)$resultsPerPage);
        //     $query->setPage((int)$page);
        // }
        // if (!$result->getCurrentSortOrder()) {
        //     $result->setCurrentSortOrder($query->getSortOrder());
        // }

        // $facetedSearchSelectedFilters = $this->getPsFacetedSearchSelectedFilters($provider, $result, $query);
        // $selectedSearchAttributesIdList = [];
        // foreach ($facetedSearchSelectedFilters as $facetedSearchSelectedAttribute) {
        //     $selectedSearchAttributesIdList = array_merge($selectedSearchAttributesIdList, array_keys($facetedSearchSelectedAttribute));
        // }
        // $selectedSearchAttributes = array_keys($facetedSearchSelectedFilters);

        $globalContext = Context::getContext();
        $idLang = (int)$context->getIdLang();

        $productsDataSet = [];
        $totalProcessedProducts = 0;

        $visibleProductsOffsetStart = ((int)$resultsPerPage * ($page - 1));
        $visibleProductsOffsetEnd = ((int)$resultsPerPage * $page);

        $checkStock = (!empty($this->conf['hideCombinationsWithoutStock']) || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR'));
        $showDefaultCombinationIfOos = !empty($this->conf['showDefaultCombinationIfOos']);

        if (!$this->fullTreeAlreadyDone) {
            $packIdList = false;
            // if (class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getIdsPacks')) {
            //     $packIdList = AdvancedPack::getIdsPacks(true);
            // }
            $productsList = $result->getProducts();
            if ($result->getCurrentSortOrder()->getEntity() == 'product' && $result->getCurrentSortOrder()->getField() == 'price') {
                // Tools::orderbyPrice($productsList, Tools::strtolower($result->getCurrentSortOrder()->getDirection()));
            }
            $already_done = [];

        } else {
            $productsList = $result->getProducts();

            // var_dump($productsList);

            foreach ($productsList as $product) {
                if (!empty($this->conf['changeProductName'])) {
                    $product['product_name'] = pm_productsbyattributes::getFullProductName($product['name'], (int)$product['id_product'], (int)$product['id_product_attribute'], $idLang);
                    $product['id_product_pack'] = 'spa-' . (int)$product['id_product'] . '-' . (int)$product['id_product_attribute'];
                } else {
                    $product['product_name'] = $product['name'];
                }
                $product['split-by-spa'] = true;
                $productsDataSet[] = $product;
                $totalProcessedProducts++;
            }
        }
        // if ($this->module->isInPerformanceMode($provider)) {
        //     $result->setTotalProductsCount((int)$result->getTotalProductsCount());
        // } else {
        //     $result->setTotalProductsCount((int)count($productsDataSet));
        // }
        // if (!empty($this->conf['sortCombinationBy']) && $this->conf['sortCombinationBy'] == 'inherit') {
        //     $orderBy = $query->getSortOrder()->toLegacyOrderBy();
        //     $orderWay = $query->getSortOrder()->toLegacyOrderWay();
        //     switch ($orderBy) {
        //         case 'name':
        //             $orderBy = 'product_name';
        //             break;
        //         case 'price':
        //             $orderBy = 'orderprice';
        //             break;
        //     }
        //     // $productsDataSet = $this->setNewCombinationInformationOnProducts($productsDataSet);
        //     // $this->module->resortProductsAfterSplit($productsDataSet, [
        //     //     'orderBy' => $orderBy,
        //     //     'originalOrderBy' => $orderBy,
        //     //     'orderWay' => $orderWay,
        //     // ]);
        //     if ($this->module->isInPerformanceMode($provider)) {
        //         $productsDataSet = array_slice($productsDataSet, 0, (int)$resultsPerPage);
        //     } else {
        //         $productsDataSet = array_slice($productsDataSet, (int)$resultsPerPage * ($page - 1), (int)$resultsPerPage);
        //     }
        // } else {
            if ($this->module->isInPerformanceMode($provider)) {
                $productsDataSet = array_slice($productsDataSet, 0, (int)$resultsPerPage);
            } else {
                $productsDataSet = array_slice($productsDataSet, (int)$resultsPerPage * ($page - 1), (int)$resultsPerPage);
            }
            // $productsDataSet = $this->setNewCombinationInformationOnProducts($productsDataSet);
        // }

        $productsDataSet = array_slice($productsDataSet, (int)$resultsPerPage * ($page - 1), (int)$resultsPerPage);

        // $filteredProducts = array_map(function($product) {
        //     return [
        //         'id_product' => $product['id_product'],
        //         'id_product_attribute' => $product['id_product_attribute'],
        //         'name' => $product['product_name'],
        //         'price' => (float)$product['price'],
        //         'link' => $product['link'],
        //         'id_image' => $product['id_image']
        //     ];
        // }, $productsDataSet);

        $result->setProducts($productsDataSet);

        $dataToCache = [
            'products' => $productsDataSet,
            'total' => $totalProcessedProducts,
            'facets' => null,
        ];
        $this->module->saveToCache($cacheKey, $dataToCache);
        
        return $result;
    }
    protected function setNewCombinationInformationOnProducts($productsDataSet)
    {
        $globalContext = Context::getContext();
        $idLang = (int)$globalContext->language->id;
        foreach ($productsDataSet as &$product) {
            if (empty($product['spa-combination'])) {
                continue;
            }
            $combination = $product['spa-combination'];
            $product['pai_id_product_attribute'] = (int)$combination['id_product_attribute'];
            $product['cache_default_attribute'] = (int)$combination['id_product_attribute'];
            $product['id_product_attribute'] = (int)$combination['id_product_attribute'];
            $product['split-by-spa'] = true;
            if (isset($product['attributes'])) {
                $product['attributes'] = [];
            }
            if (!isset($product['name'])) {
                $product = self::getProductAssemblerInstance()->assembleProduct($product);
            }
            if (!empty($this->conf['changeProductName'])) {
                $product['product_name'] = pm_productsbyattributes::getFullProductName($product['name'], (int)$product['id_product'], (int)$product['id_product_attribute'], $idLang);
                $product['id_product_pack'] = 'spa-' . (int)$product['id_product'] . '-' . (int)$product['id_product_attribute'];
            } else {
                $product['product_name'] = $product['name'];
            }

            $product['quantity_sql'] = $combination['quantity'];
            $product['is_color_group'] = (bool)$combination['is_color_group'];
            if (version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
                $combination_image = Image::getBestImageAttribute((int)$globalContext->shop->id, (int)$idLang, (int)$product['id_product'], (int)$product['id_product_attribute']);
                if (isset($combination_image['id_image'])) {
                    $product['cover_image_id'] = (int)$combination_image['id_image'];
                }
            }
            if (empty($product['attributes'])) {
                $combinationAttributes = Product::getAttributesParams((int)$product['id_product'], (int)$product['id_product_attribute']);
                foreach ($combinationAttributes as $attribute) {
                    $product['attributes'][$attribute['id_attribute_group']] = $attribute;
                }
            }
        }
        return $productsDataSet;
    }
    protected static function getProductAssemblerInstance()
    {
        static $assembler = null;
        if ($assembler === null) {
            $context = Context::getContext();
            $assembler = new ProductAssembler($context);
        }
        return $assembler;
    }
    protected function getPsFacetedSearchSelectedFilters($provider, ProductSearchResult $result, ProductSearchQuery $query)
    {
        $facetedSearchSelectedFilters = [];
        if ($provider instanceof PrestaShop\Module\FacetedSearch\Product\SearchProvider && !empty($this->providerModule)) {
            $facetCollection = $result->getFacetCollection();
            $facets = $facetCollection->getFacets();
            if (!empty($facets) && is_array($facets)) {
                foreach ($facets as $facet) {
                    if (!$facet->getProperty('id_attribute_group')) {
                        continue;
                    }
                    foreach ($facet->getFilters() as $filter) {
                        if (!$filter->isActive()) {
                            continue;
                        }
                        if (!isset($facetedSearchSelectedFilters[(int)$facet->getProperty('id_attribute_group')])) {
                            $facetedSearchSelectedFilters[(int)$facet->getProperty('id_attribute_group')] = [];
                        }
                        $facetedSearchSelectedFilters[(int)$facet->getProperty('id_attribute_group')][(int)$filter->getValue()] = true;
                    }
                }
            }
        }
        if (class_exists('Ps_FacetedsearchProductSearchProvider') && $provider instanceof Ps_FacetedsearchProductSearchProvider) {
            require_once _PS_ROOT_DIR_ . '/modules/ps_facetedsearch/src/Ps_FacetedsearchFiltersConverter.php';
            $facetedSearchFilters = [];
            if (class_exists('Ps_FacetedsearchFiltersConverter')) {
                $filtersConverter = new Ps_FacetedsearchFiltersConverter();
                $facetCollectionFromEncodedFacets = $provider->getFacetCollectionFromEncodedFacets($query);
                $facetedSearchFilters = $filtersConverter->getFacetedSearchFiltersFromFacets(
                    $facetCollectionFromEncodedFacets->getFacets()
                );
            }
            foreach ($facetedSearchFilters as $key => $filter_values) {
                if (!count($filter_values)) {
                    continue;
                }
                preg_match('/^(.*[^_0-9])/', $key, $res);
                $key = $res[1];
                switch ($key) {
                    case 'id_attribute_group':
                        foreach ($filter_values as $filter_value) {
                            $filter_value_array = explode('_', $filter_value);
                            if (!isset($facetedSearchSelectedFilters[$filter_value_array[0]])) {
                                $facetedSearchSelectedFilters[$filter_value_array[0]] = [];
                            }
                            $facetedSearchSelectedFilters[$filter_value_array[0]][(int)$filter_value_array[1]] = true;
                        }
                        break;
                }
            }
        }
        return $facetedSearchSelectedFilters;
    }
}
