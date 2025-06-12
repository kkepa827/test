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

use PrestaShop\PrestaShop\Core\Product\Search\SortOrderFactory;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;

class Pm_ProductsByAttributesCategoryProvider implements ProductSearchProviderInterface
{
    private $module;
    private $category;
    private $translator;
    private $sortOrderFactory;
    private $advancedSearchInstance = null;
    private $isAdvancedSearchProCached = null;

    public function __construct(
        $translator,
        Category $category,
        pm_productsbyattributes $module
    ) {
        $this->module = $module;
        $this->translator = $translator;
        $this->category = $category;
        $this->sortOrderFactory = new SortOrderFactory($this->translator);
    }

    /**
     * Główna metoda optymalizująca - pobiera produkty i liczbę w jednym wywołaniu
     */
    private function getProductsAndCount(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $config = $this->module->getModuleConfiguration();
        
        // Przygotowanie sortowania dla Advanced Search
        // $this->prepareSortOrder($query);
        
        // Optymalizacja: Sprawdź czy moduł udostępnia metodę zwracającą oba wyniki jednocześnie
        if (method_exists($this->module, 'getCategoryProductsOptimized')) {
            return $this->module->getCategoryProductsOptimized(
                (int)$this->category->id,
                $context->getIdLang(),
                $query->getPage(),
                $query->getResultsPerPage(),
                $query->getSortOrder()->toLegacyOrderBy(),
                $query->getSortOrder()->toLegacyOrderWay(),
                !empty($config['fullTree'])
            );
        }
        
        // Fallback: jeśli nie ma zoptymalizowanej metody, wykonaj dwa wywołania
        $products = $this->module->getCategoryProducts(
            (int)$this->category->id,
            $context->getIdLang(),
            $query->getPage(),
            $query->getResultsPerPage(),
            $query->getSortOrder()->toLegacyOrderBy(),
            $query->getSortOrder()->toLegacyOrderWay(),
            false,
            !empty($config['fullTree'])
        );
        
        $count = $this->module->getCategoryProducts(
            (int)$this->category->id,
            $context->getIdLang(),
            $query->getPage(),
            $query->getResultsPerPage(),
            $query->getSortOrder()->toLegacyOrderBy(),
            $query->getSortOrder()->toLegacyOrderWay(),
            true,
            !empty($config['fullTree'])
        );

        // var_dump('fallback');
        // var_dump($products);
        // var_dump($count);
        
        return [
            'products' => $products,
            'count' => $count
        ];
    }

    /**
     * Przygotowanie sortowania dla Advanced Search
     */
    private function prepareSortOrder(ProductSearchQuery $query)
    {
        $advancedSearchInstance = $this->getAdvancedSearchInstance();
        
        if (!$advancedSearchInstance) {
            return;
        }
        
        if (!$this->isAdvancedSearchCompatible($advancedSearchInstance)) {
            return;
        }
        
        $fullTreeProvider = $this->getFullTreeProvider($advancedSearchInstance);
        
        if (!$fullTreeProvider) {
            return;
        }
        
        $sortOrder = $fullTreeProvider->getSearchEngineSortOrder($query);
        if (!empty($sortOrder)) {
            $query->setSortOrder($sortOrder);
        }
    }

    /**
     * Sprawdza czy Advanced Search jest kompatybilny
     */
    private function isAdvancedSearchCompatible($advancedSearchInstance)
    {
        return version_compare($advancedSearchInstance->version, '5.0.3', '>=')
            && method_exists($advancedSearchInstance, 'isFullTreeModeEnabled')
            && $advancedSearchInstance->isFullTreeModeEnabled();
    }

    /**
     * Pobiera instancję Full Tree Provider
     */
    private function getFullTreeProvider($advancedSearchInstance)
    {
        $providerClass = $this->isAdvancedSearchPro($advancedSearchInstance)
            ? 'AdvancedSearch\SearchProvider\FullTree'
            : 'AdvancedSearchStd\SearchProvider\FullTree';
        
        if (!class_exists($providerClass)) {
            return null;
        }
        
        return new $providerClass(
            $advancedSearchInstance,
            $this->translator
        );
    }

    /**
     * Główna metoda wywoływana przez PrestaShop
     */
    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult();
        $cacheKey = 'category_' . $this->category->id;
        
        $cachedData = $this->module->getFromCache($cacheKey);
        
        if ($cachedData !== false) {
            $result->setProducts($cachedData['products'])
                ->setTotalProductsCount($cachedData['count']);
            
            return $result;
        }
        
        $data = $this->getProductsAndCount($context, $query);
        
        if (empty($data['products'])) {
            $result->setProducts([])
                ->setTotalProductsCount(0);
            
            $this->module->saveToCache($cacheKey, [
                'products' => [],
                'count' => 0
            ]);
            
            return $result;
        }
        
        $result->setProducts($data['products'])
            ->setTotalProductsCount($data['count'])
            ->setAvailableSortOrders(
                $this->sortOrderFactory->getDefaultSortOrders()
            );
        
        $this->module->saveToCache($cacheKey, [
            'products' => $data['products'],
            'count' => $data['count']
        ]);
        
        return $result;
    }

    /**
     * Cachowana metoda pobierania instancji Advanced Search
     */
    private function getAdvancedSearchInstance()
    {
        if ($this->advancedSearchInstance !== null) {
            return $this->advancedSearchInstance;
        }
        
        // Próbuj najpierw wersję Pro
        $moduleNames = ['pm_advancedsearch4', 'pm_advancedsearch'];
        
        foreach ($moduleNames as $moduleName) {
            $module = Module::getInstanceByName($moduleName);
            if (is_object($module) && $module->active) {
                $this->advancedSearchInstance = $module;
                return $module;
            }
        }
        
        $this->advancedSearchInstance = false;
        return false;
    }

    /**
     * Cachowana metoda sprawdzania czy to wersja Pro
     */
    private function isAdvancedSearchPro($moduleInstance)
    {
        if ($this->isAdvancedSearchProCached === null) {
            $this->isAdvancedSearchProCached = ($moduleInstance->name == 'pm_advancedsearch4');
        }
        return $this->isAdvancedSearchProCached;
    }
}