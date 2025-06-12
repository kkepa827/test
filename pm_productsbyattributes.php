<?php
/**
 * Products by Attributes
 *
 * @author    Presta-Module.com <support@presta-module.com> - http://www.presta-module.com
 * @copyright Presta-Module 2024 - http://www.presta-module.com
 * @license   see file: LICENSE.txt
 *
 * @version   2.2.0
 *
 *           ____     __  __
 *          |  _ \   |  \/  |
 *          | |_) |  | |\/| |
 *          |  __/   | |  | |
 *          |_|      |_|  |_|
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
include_once _PS_ROOT_DIR_ . '/modules/pm_productsbyattributes/ProductsByAttributesCoreClass.php';
class pm_productsbyattributes extends ProductsByAttributesCoreClass
{
    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfiguration = [
        'selectedGroups' => [],
        'changeProductName' => true,
        'hideCombinationsWithoutStock' => false,
        'showDefaultCombinationIfOos' => false,
        'hideCombinationsWithoutCover' => false,
        'hideColorSquares' => true,
        'maintenanceMode' => false,
        'performanceMode' => false,
        'fullTree' => true,
        'enabledControllers' => [
            'BestSales' => 1,
            'Category' => 1,
            'Manufacturer' => 1,
            'NewProducts' => 1,
            'PricesDrop' => 1,
            'Search' => 1,
            'Supplier' => 1,
        ],
        'selectedResourceId' => [
            'Category' => [],
        ],
        'exclusionMode' => [
            'Category' => 1,
        ],
        'nameSeparator' => ' - ',
        'autoReindex' => true,
        'sortCombinationBy' => 'inherit',
        'combinationToHighlight' => '',

    ];
    public function __construct()
    {
        $this->need_instance = 0;
        $this->name = 'pm_productsbyattributes';
        $this->module_key = 'c44c197e40ce99724b7e5f6c631dacc4';
        $this->author = 'Presta-Module';
        $this->tab = 'front_office_features';
        $this->version = '2.2.0';
        $this->ps_versions_compliancy['min'] = '1.7.6.0';
        $this->bootstrap = true;
        $this->displayName = $this->l('Show Products by Attributes');
        $this->description = $this->l('Show as many products you have attributes into your category pages');
        parent::__construct();
    }
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('productSearchProvider')
            || !$this->registerHook(version_compare(_PS_VERSION_, '1.7.8.0', '<') ? 'actionGetProductPropertiesAfter' : 'actionGetProductPropertiesAfterUnitPrice')
            || !$this->registerHook('filterProductSearch')
            || !$this->registerHook('actionObjectAddAfter') || !$this->registerHook('actionObjectUpdateAfter') || !$this->registerHook('actionObjectDeleteBefore')
            || !$this->createCacheTable()
            || !$this->registerHook('actionClearCache')
        ) {
            return false;
        }
        $id_hook = Hook::getIdByName('productSearchProvider');
        $this->updatePosition($id_hook, false, 1);
        $this->checkIfModuleIsUpdate(true, false, true);
        return true;
    }

    // public function hookActionClearCache($params) {
    //     $cacheDir = _PS_CACHE_DIR_ . 'pm_productsbyattributes/';
    //     if (is_dir($cacheDir)) {
    //         $files = glob($cacheDir . '*.json');
    //         foreach ($files as $file) {
    //             unlink($file);
    //         }
    //     }
    // }

    public function getCacheFilePath($cacheKey) {
        $cacheDir = _PS_CACHE_DIR_ . 'pm_productsbyattributes/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        return $cacheDir . $cacheKey . '.json';
    }

    public function getFromCache($cacheKey) {
        $filePath = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return json_decode($content, true);
        }
        return false;
    }

    public function saveToCache($cacheKey, $data) {
        $filePath = $this->getCacheFilePath($cacheKey);
        return file_put_contents($filePath, json_encode($data));
    }

    public function createCacheTable()
    {
        $res = (bool)Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pm_spa_cache`');
        $res &= (bool)Db::getInstance()->Execute('CREATE TABLE `' . _DB_PREFIX_ . 'pm_spa_cache` (
            `id_product` int(11) UNSIGNED NOT NULL,
            `id_product_attribute` int(11) UNSIGNED NOT NULL,
            `id_shop` int(11) UNSIGNED NOT NULL,
            `id_attribute_list` text NOT NULL,
            PRIMARY KEY (`id_product`, `id_product_attribute`, `id_shop`) USING BTREE
            ) ENGINE = ' . _MYSQL_ENGINE_);
        // $this->fillCacheTable();
        return (bool)$res;
    }
    // public function fillCacheTable($idProduct = null)
    // {
    //     $res = true;
    //     if (!empty($idProduct)) {
    //         $res &= (bool)Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'pm_spa_cache` WHERE `id_product`=' . (int)$idProduct . ' AND `id_shop` IN (' . implode(',', array_map('intval', Shop::getContextListShopID())) . ')');
    //     } else {
    //         $res &= (bool)Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'pm_spa_cache` WHERE `id_shop` IN (' . implode(',', array_map('intval', Shop::getContextListShopID())) . ')');
    //     }
    //     foreach (Shop::getContextListShopID() as $idShop) {
    //         $shop = new Shop($idShop);
    //         $config = $this->getModuleConfiguration($shop);
    //         if (empty($config['selectedGroups']) || !is_array($config['selectedGroups'])) {
    //             continue;
    //         }
    //         $checkStock = (!empty($config['hideCombinationsWithoutStock']) || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR', null, null, (int)$shop->id)) && Configuration::get('PS_STOCK_MANAGEMENT', null, null, (int)$shop->id);
    //         $oosSetting = (int)Configuration::get('PS_ORDER_OUT_OF_STOCK', null, null, (int)$shop->id);
    //         $showDefaultCombinationIfOos = !empty($config['showDefaultCombinationIfOos']);
    //         $checkProductCover = (bool)$config['hideCombinationsWithoutCover'];
    //         $joinStockTable = $checkStock;
    //         $combinationToHighlight = null;
    //         if (!empty($config['combinationToHighlight'])) {
    //             switch ($config['combinationToHighlight']) {
    //                 case 'quantity_asc':
    //                     $combinationToHighlight = 'stock.`quantity` ASC';
    //                     $joinStockTable = true;
    //                     break;
    //                 case 'quantity_desc':
    //                     $combinationToHighlight = 'stock.`quantity` DESC';
    //                     $joinStockTable = true;
    //                     break;
    //                 case 'price_asc':
    //                     $combinationToHighlight = 'pa_shop.`price` ASC';
    //                     break;
    //                 case 'price_desc':
    //                     $combinationToHighlight = 'pa_shop.`price` DESC';
    //                     break;
    //                 case 'unit_price_asc':
    //                     $combinationToHighlight = 'pa_shop.`unit_price_impact` ASC';
    //                     break;
    //                 case 'unit_price_desc':
    //                     $combinationToHighlight = 'pa_shop.`unit_price_impact` DESC';
    //                     break;
    //             }
    //         }
    //         $res &= (bool)Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'pm_spa_cache`
    //         (
    //             SELECT
    //             *
    //             FROM
    //             (
    //             SELECT
    //                 *
    //             FROM
    //                 (
    //                 SELECT
    //                     pa.`id_product`,
    //                     pa.`id_product_attribute`,
    //                     "' . (int)$shop->id . '" AS `id_shop`,
    //                     GROUP_CONCAT( pac.`id_attribute` ORDER BY pac.`id_attribute` ) AS `id_attribute_list`
    //                 FROM
    //                     `' . _DB_PREFIX_ . 'product_attribute_combination` pac
    //                     JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (
    //                         pa.`id_product_attribute` = pac.`id_product_attribute`
    //                         AND pac.id_attribute IN (
    //                         SELECT
    //                             `id_attribute`
    //                         FROM
    //                             `' . _DB_PREFIX_ . 'attribute`
    //                         WHERE
    //                             `id_attribute_group` IN (' . implode(',', array_map('intval', $config['selectedGroups'])) . ')
    //                         )
    //                     )
    //                     INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_shop pa_shop ON (pa_shop.`id_product_attribute` = pa.`id_product_attribute` AND pa_shop.`id_shop`=' . (int)$shop->id . ')
    //                     JOIN `' . _DB_PREFIX_ . 'product` p ON ( p.`id_product` = pa.`id_product` ' . (!empty($idProduct) ? ' AND p.`id_product`=' . (int)$idProduct : '') . ')
    //                     ' . ($joinStockTable ? Product::sqlStock('p', 'pa', false, $shop) : '') . '
    //                     WHERE 1
    //                     ' . ($checkProductCover ? ' AND pa.`id_product_attribute` IN (
    //                         SELECT pai.`id_product_attribute`
    //                         FROM `' . _DB_PREFIX_ . 'image` i
    //                         JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop ON (i.`id_image` = image_shop.`id_image` AND image_shop.`id_shop` = ' . (int)$shop->id . ')
    //                         JOIN `' . _DB_PREFIX_ . 'product_attribute_image` pai  ON (pai.`id_image` = i.`id_image`)
    //                         GROUP BY pai.`id_product_attribute`
    //                     )' : '') . '
    //                     ' . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '') . '
    //                 GROUP BY
    //                     pa.`id_product`,
    //                     pa.`id_product_attribute`
    //                 ' . (!empty($combinationToHighlight) ? ' ORDER BY ' . pSQL($combinationToHighlight) : '') . '
    //                 ) AS `tmp_cartesian_table`
    //             GROUP BY
    //                 `id_product`,
    //                 `id_attribute_list`
    //             ) AS `tmp_cartesian_table_bis`
    //         )');
    //     }
    //     return (bool)$res;
    // }
    public function getModuleConfiguration($shop = null)
    {
        $config = parent::getModuleConfiguration($shop);
        if (!isset($config['selectedGroups'])) {
            $config['selectedGroups'] = [];
            $this->setModuleConfiguration($config);
        } elseif (!is_array($config['selectedGroups'])) {
            $config['selectedGroups'] = [$config['selectedGroups']];
            $this->setModuleConfiguration($config);
        }
        $asInstance = $this->getAdvancedSearchInstance();
        if (!empty($asInstance->active) && method_exists($asInstance, 'isFullTreeModeEnabled') && $asInstance->isFullTreeModeEnabled()) {
            $config['fullTree'] = true;
        }
        return $config;
    }

    // public function getCategoryProductsOptimized($idCategory, $idLang, $page, $resultsPerPage, $orderBy, $orderWay, $fullTree = false)
    // {
    //     $context = Context::getContext();
        
    //     // Minimalne zapytanie pobierające wszystkie kombinacje produktów z kategorii
    //     $sql = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT
    //             p.id_product,
    //             pa.id_product_attribute,
    //             p.reference,
    //             pa.reference as combination_reference,
    //             pl.name,
    //             pl.link_rewrite,
    //             p.active,
    //             product_shop.price,
    //             product_shop.visibility,
    //             pa.price AS combination_price,
    //             pa.default_on,
    //             stock.quantity AS stock_quantity,
    //             stock.out_of_stock,
    //             image_shop.id_image,
    //             il.legend,
    //             m.name AS manufacturer_name,
    //             GROUP_CONCAT(DISTINCT CONCAT(agl.name, " : ", al.name) ORDER BY ag.position, a.position SEPARATOR ", ") as combination_name
    //         FROM '._DB_PREFIX_.'product p
    //         '.Shop::addSqlAssociation('product', 'p').'
    //         INNER JOIN '._DB_PREFIX_.'product_attribute pa ON (p.id_product = pa.id_product)
    //         '.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.default_on = 1').'
    //         '.Product::sqlStock('p', 'pa').'
    //         LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = '.(int)$idLang.' AND pl.id_shop = '.(int)$context->shop->id.')
    //         LEFT JOIN '._DB_PREFIX_.'category_product cp ON (p.id_product = cp.id_product)
    //         LEFT JOIN '._DB_PREFIX_.'manufacturer m ON (m.id_manufacturer = p.id_manufacturer)
    //         LEFT JOIN '._DB_PREFIX_.'image_shop image_shop ON (image_shop.id_product = p.id_product AND image_shop.cover = 1 AND image_shop.id_shop = '.(int)$context->shop->id.')
    //         LEFT JOIN '._DB_PREFIX_.'image_lang il ON (image_shop.id_image = il.id_image AND il.id_lang = '.(int)$idLang.')
    //         LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
    //         LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute = pac.id_attribute)
    //         LEFT JOIN '._DB_PREFIX_.'attribute_lang al ON (al.id_attribute = a.id_attribute AND al.id_lang = '.(int)$idLang.')
    //         LEFT JOIN '._DB_PREFIX_.'attribute_group ag ON (ag.id_attribute_group = a.id_attribute_group)
    //         LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = '.(int)$idLang.')
    //         WHERE cp.id_category = '.(int)$idCategory.'
    //         AND p.active = 1
    //         AND product_shop.active = 1
    //         AND product_shop.visibility IN ("both", "catalog")
    //         GROUP BY p.id_product, pa.id_product_attribute';
        
    //     // Dodaj sortowanie
    //     if ($orderBy && $orderWay) {
    //         // Mapowanie sortowania dla kombinacji
    //         $orderByReal = $orderBy;
    //         $orderWayReal = $orderWay;
            
    //         switch ($orderBy) {
    //             case 'name':
    //                 $orderByReal = 'pl.name';
    //                 break;
    //             case 'price':
    //                 $orderByReal = '(product_shop.price + IFNULL(pa.price, 0))';
    //                 break;
    //             case 'quantity':
    //                 $orderByReal = 'stock.quantity';
    //                 break;
    //             case 'reference':
    //                 $orderByReal = 'IFNULL(pa.reference, p.reference)';
    //                 break;
    //             case 'position':
    //                 $orderByReal = 'cp.position';
    //                 break;
    //         }
            
    //         $sql .= ' ORDER BY '.pSQL($orderByReal).' '.pSQL($orderWayReal);
            
    //         // Dodaj sortowanie po domyślnej kombinacji jako drugie kryterium
    //         $sql .= ', pa.default_on DESC';
    //     }
        
    //     // Dodaj limit
    //     $sql .= ' LIMIT '.(int)(($page - 1) * $resultsPerPage).', '.(int)$resultsPerPage;
        
    //     // Wykonaj zapytanie
    //     $products = Db::getInstance()->executeS($sql);
        
    //     // Pobierz całkowitą liczbę kombinacji
    //     $totalProducts = (int)Db::getInstance()->getValue('SELECT FOUND_ROWS()');
        
    //     // Przetwórz wyniki - dodaj pełną nazwę produktu z kombinacją
    //     foreach ($products as &$product) {
    //         // Buduj pełną nazwę produktu z atrybutami
    //         if (!empty($product['combination_name'])) {
    //             $product['name_with_combination'] = $product['name'] . ' - ' . $product['combination_name'];
    //         } else {
    //             $product['name_with_combination'] = $product['name'];
    //         }
            
    //         // Oblicz finalną cenę
    //         $product['final_price'] = $product['price'] + (float)$product['combination_price'];
            
    //         // Ustaw referencję
    //         $product['final_reference'] = !empty($product['combination_reference']) ? $product['combination_reference'] : $product['reference'];
    //     }
        
    //     return [
    //         'products' => $products,
    //         'count' => $totalProducts
    //     ];
    // }

    public function processModuleUpdate($previousVersion, $currentVersion)
    {
        if (version_compare($previousVersion, '1.1.0', '<') && version_compare($this->version, '1.1.0', '>=')) {
            $this->registerHook('productSearchProvider');
            $this->registerHook(version_compare(_PS_VERSION_, '1.7.8.0', '<') ? 'actionGetProductPropertiesAfter' : 'actionGetProductPropertiesAfterUnitPrice');
            $this->registerHook('filterProductSearch');
            $id_hook = Hook::getIdByName('productSearchProvider');
            $this->updatePosition($id_hook, false, 1);
        }
        if (version_compare($previousVersion, '2.0.0', '<') && version_compare($this->version, '2.0.0', '>=')) {
            $this->createCacheTable();
            $this->registerHook('actionObjectAddAfter');
            $this->registerHook('actionObjectUpdateAfter');
            $this->registerHook('actionObjectDeleteBefore');
        }
    }
    public function processContent()
    {
        $this->tabs['configuration'] = [
            'icon' => 'cogs',
            'label' => $this->l('Configuration'),
        ];
        $this->tabs['performance'] = [
            'icon' => 'database',
            'label' => $this->l('Performance'),
        ];
        $this->tabs['restrictions'] = [
            'icon' => 'eye-open',
            'label' => $this->l('Restrictions'),
        ];
        $this->tabs['cron'] = [
            'icon' => 'link',
            'label' => $this->l('CRON'),
        ];
        $this->tabs['maintenance'] = [
            'icon' => 'code',
            'label' => $this->l('Maintenance'),
        ];
        $config = $this->getModuleConfiguration();
        $warnings = [];
        if (!empty($config['maintenanceMode'])) {
            $warnings[] = $this->l('Module is currently running in Maintenance Mode');
        }
        if (!Configuration::getGlobalValue('PM_SPA_SECURE_KEY')) {
            Configuration::updateGlobalValue('PM_SPA_SECURE_KEY', Tools::strtoupper(Tools::passwdGen(16)));
        }
        $this->context->smarty->assign([
            'warnings' => $warnings,
            'attributeGroupOptions' => $this->getAttributeGroupOptions(),
            'sortCombinationsByOptions' => $this->getSortCombinationsByOptions(),
            'highlightCombinationsOptions' => $this->getHighlightCombinationsOptions(),
            // 'colorGroups' => $this->getColorGroups(),
            'psDispUnavailableAttr' => (bool)Configuration::get('PS_DISP_UNAVAILABLE_ATTR'),
            'psStockManagement' => (bool)Configuration::get('PS_STOCK_MANAGEMENT'),
            'layeredModuleIsEnabled' => $this->layeredModuleIsEnabled(),
            'cronURL' => $this->context->link->getModuleLink($this->name, 'cron', ['secure_key' => Configuration::getGlobalValue('PM_SPA_SECURE_KEY')]),
            'pmCategoriesBox' => $this->renderCategoryTree($config['selectedResourceId']['Category']),
        ]);
    }
    protected function renderCategoryTree($selectedCategories = [])
    {
        if (!is_array($selectedCategories)) {
            $selectedCategories = [];
        }
        $tree = new HelperTreeCategories('pmCategoriesBox');
        $tree->setInputName('pmCategoriesBox')
            ->setUseCheckBox(true)
            ->setRootCategory(Category::getRootCategory()->id)
            ->setSelectedCategories($selectedCategories);
        return $tree->render();
    }
    protected function postProcess()
    {
        if (Tools::getIsset('submitModuleConfiguration') && Tools::isSubmit('submitModuleConfiguration')) {
            $config = $this->getModuleConfiguration();
            foreach (['changeProductName', 'hideCombinationsWithoutStock', 'showDefaultCombinationIfOos', 'hideColorSquares', 'maintenanceMode', 'performanceMode', 'fullTree', 'hideCombinationsWithoutCover', 'autoReindex'] as $configKey) {
                $config[$configKey] = (bool)Tools::getValue($configKey);
                if ($configKey == 'hideCombinationsWithoutStock' && !Configuration::get('PS_STOCK_MANAGEMENT')) {
                    $config[$configKey] = false;
                }
                if ($configKey == 'showDefaultCombinationIfOos' && !$config['hideCombinationsWithoutStock']) {
                    $config[$configKey] = false;
                }
            }
            foreach (['selectedGroups'] as $configKey) {
                $config[$configKey] = Tools::getValue($configKey);
                if (!empty($config[$configKey]) && is_array($config[$configKey])) {
                    $config[$configKey] = array_map('intval', $config[$configKey]);
                } else {
                    $config[$configKey] = [];
                }
            }
            foreach (['exclusionMode', 'enabledControllers', 'nameSeparator', 'sortCombinationBy', 'combinationToHighlight'] as $configKey) {
                $config[$configKey] = Tools::getValue($configKey);
            }
            $config['selectedResourceId']['Category'] = [];
            if (!empty($config['enabledControllers']['Category']) && is_array(Tools::getValue('pmCategoriesBox'))) {
                $config['selectedResourceId']['Category'] = array_map('intval', Tools::getValue('pmCategoriesBox'));
            }
            $this->setModuleConfiguration($config);
            // $this->fillCacheTable();
            $this->context->controller->confirmations[] = $this->l('Module configuration successfully saved');
        }
    }
    protected function layeredModuleIsEnabled()
    {
        return Module::isEnabled('ps_facetedsearch')
            || Module::isEnabled('pm_advancedsearch4')
            || Module::isEnabled('ps_specials')
            || Module::isEnabled('pm_advancedsearch');
    }
    public function isInPerformanceMode(PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface $provider = null)
    {
        if ($provider != null && !$this->isProviderAllowedForPerformanceMode($provider)) {
            return false;
        }
        static $isInPerformanceMode = null;
        if ($isInPerformanceMode === null) {
            $config = $this->getModuleConfiguration();
            $isInPerformanceMode = $config['performanceMode'] == true;
        }
        return $isInPerformanceMode;
    }
    protected function isProviderAllowedForPerformanceMode(PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface $provider)
    {
        $advancedSearchProFacetsProvider = '\AdvancedSearch\SearchProvider\Facets';
        $advancedSearchProFullTreeProvider = '\AdvancedSearch\SearchProvider\FullTree';
        $advancedSearchFacetsProvider = '\AdvancedSearchStd\SearchProvider\Facets';
        $advancedSearchFullTreeProvider = '\AdvancedSearchStd\SearchProvider\FullTree';
        return
            !$provider instanceof $advancedSearchProFacetsProvider
            && !$provider instanceof $advancedSearchProFullTreeProvider
            && !$provider instanceof $advancedSearchFacetsProvider
            && !$provider instanceof $advancedSearchFullTreeProvider
        ;
    }
    protected function getAdvancedSearchInstance()
    {
        // if (Module::isEnabled('pm_advancedsearch4')) {
        //     return Module::getInstanceByName('pm_advancedsearch4');
        // }
        // if (Module::isEnabled('pm_advancedsearch')) {
        //     return Module::getInstanceByName('pm_advancedsearch');
        // }
        return null;
    }
    // private function getColorGroups()
    // {
    //     $content = '';
    //     $sql = 'SELECT `id_attribute_group` FROM `' . _DB_PREFIX_ . 'attribute_group` WHERE `is_color_group` = 1';
    //     $groups = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql);
    //     if (is_array($groups)) {
    //         $attribute_groups_html = '';
    //         foreach ($groups as $group) {
    //             $attribute_groups_html .= $group['id_attribute_group'] . ', ';
    //         }
    //         $content = Tools::substr($attribute_groups_html, 0, -2);
    //     }
    //     return $content;
    // }
    protected function isInMaintenance()
    {
        static $isInMaintenance = null;
        if ($isInMaintenance === null) {
            $config = $this->getModuleConfiguration();
            if (!empty($config['maintenanceMode'])) {
                $ips = explode(',', Configuration::get('PS_MAINTENANCE_IP'));
                $isInMaintenance = !in_array($_SERVER['REMOTE_ADDR'], $ips);
            }
        }
        return $isInMaintenance;
    }
    public function hasAtLeastOneAttributeGroup()
    {
        static $hasAtLeastOneAttributeGroup = null;
        if ($hasAtLeastOneAttributeGroup === null) {
            $conf = $this->getModuleConfiguration();
            if (Combination::isFeatureActive() && isset($conf['selectedGroups']) && is_array($conf['selectedGroups']) && count($conf['selectedGroups'])) {
                $hasAtLeastOneAttributeGroup = true;
            } else {
                $hasAtLeastOneAttributeGroup = false;
            }
        }
        return $hasAtLeastOneAttributeGroup;
    }
    protected function hasAutoReindexEnabled()
    {
        static $hasAutoReindexEnabled = null;
        if ($hasAutoReindexEnabled === null) {
            $conf = $this->getModuleConfiguration();
            $hasAutoReindexEnabled = !empty($conf['autoReindex']);
        }
        return $hasAutoReindexEnabled;
    }
    public function hookActionProductListOverride($params)
    {
        if ($this->isInMaintenance()) {
            return;
        }
        if (!$this->hasAtLeastOneAttributeGroup()) {
            $params['hookExecuted'] = false;
            return;
        }
        // $conf = $this->getModuleConfiguration();
        // $currentController = get_class($this->context->controller);
        // $controllerClass = Tools::strReplaceFirst('Controller', '', $currentController);
        // if ($controllerClass == 'Category' && isset($conf['enabledControllers']['Category']) && $conf['enabledControllers']['Category']) {
        //     if (method_exists($this->context->controller, 'getCategory')) {
        //         $currentCategory = $this->context->controller->getCategory();
        //         if (!$this->validateResourceIdCondition($currentCategory->id, 'Category')) {
        //             $params['hookExecuted'] = false;
        //             return;
        //         }
        //     } else {
        //         $params['hookExecuted'] = false;
        //         return;
        //     }
        // }
        // $pageName = Context::getContext()->controller->php_self;
        // if($pageName != 'module-pm_advancedsearch4-seo') {
            // if ((!isset($params['module']) || !in_array($params['module'], ['pm_advancedsearch4', 'pm_advancedsearch'])) && (Module::isEnabled('pm_advancedsearch4') || Module::isEnabled('pm_advancedsearch')) && $this->context->controller instanceof CategoryController) {
            //     $asInstance = $this->getAdvancedSearchInstance();
            //     if (method_exists($asInstance, 'isFullTreeModeEnabled') && $asInstance->isFullTreeModeEnabled()) {
            //         return;
            //     }
            // }
            // if (isset($params['module']) && in_array($params['module'], ['pm_advancedsearch4', 'pm_advancedsearch'])) {
            //     $this->splitProductsListOfSearchResults($params);
            //     $this->splitProductsList($params['catProducts']);
            //     // if (!empty($conf['sortCombinationBy']) && $conf['sortCombinationBy'] == 'inherit' && isset($params['version']) && version_compare($params['version'], '5.0.3', '>=')) {
            //     //     $this->resortProductsAfterSplit($params['catProducts'], $params);
            //     // }
            //     $params['splitDone'] = true;
            //     $params['hookExecuted'] = $params['splitDone'];
            // }
        // }

    }
    protected function validateResourceIdCondition($resourceId, $type)
    {
        $config = $this->getModuleConfiguration();
        $exclusionMode = !empty($config['exclusionMode'][$type]);
        if (!isset($config['selectedResourceId'][$type])) {
            return false;
        }
        if (!is_array($config['selectedResourceId'][$type])) {
            return false;
        }
        if ($exclusionMode) {
            return !in_array((int)$resourceId, $config['selectedResourceId'][$type]);
        }
        return in_array((int)$resourceId, $config['selectedResourceId'][$type]);
    }
    public function hookProductSearchProvider()
    {
        if ($this->isInMaintenance() || !$this->hasAtLeastOneAttributeGroup()) {
            return null;
        }
        $conf = $this->getModuleConfiguration();
        $currentController = get_class($this->context->controller);
        // if (in_array($currentController, ['IqitSearchSearchiqitModuleFrontController', 'AmbjolisearchjolisearchModuleFrontController'])) {
        //     $currentController = 'SearchController';
        // }
        require_once _PS_ROOT_DIR_ . '/modules/pm_productsbyattributes/src/Pm_ProductsByAttributesProductSearchProvider.php';

        $controllerClass = Tools::strReplaceFirst('Controller', '', $currentController);
        if (isset($conf['enabledControllers'][$controllerClass]) && $conf['enabledControllers'][$controllerClass]) {
            if ($controllerClass == 'Category' && method_exists($this->context->controller, 'getCategory')) {
                $currentCategory = $this->context->controller->getCategory();
                if ($this->validateResourceIdCondition($currentCategory->id, $controllerClass)) {
                    return new Pm_ProductsByAttributesProductSearchProvider($this, $conf);
                }
            } else {
                return new Pm_ProductsByAttributesProductSearchProvider($this, $conf);
            }
        }
        return null;
    }
    // public function hookActionGetProductPropertiesAfterUnitPrice($params)
    // {
    //     $this->hookActionGetProductPropertiesAfter($params);
    // }
    // public function hookActionGetProductPropertiesAfter($params)
    // {
    //     if (isset($params['product']['product_name'])) {
    //         $params['product']['name'] = $params['product']['product_name'];
    //     }
    // }
    // public function hookFilterProductSearch($params)
    // {
    //     $conf = $this->getModuleConfiguration();
    //     foreach ($params['searchVariables']['products'] as &$product) {
    //         if (empty($product['split-by-spa'])) {
    //             continue;
    //         }
    //         if (!empty($conf['hideColorSquares'])) {
    //             $product->offsetSet('main_variants', [], true);
    //         }
    //         if (version_compare(_PS_VERSION_, '1.7.6.0', '>=') && version_compare(_PS_VERSION_, '1.7.6.1', '<=')) {
    //             $product->offsetSet('canonical_url', $product['url'], true);
    //         }
    //     }
    // }
    public function hookActionObjectAddAfter($params)
    {
        if (!isset($params['object']) || !Validate::isLoadedObject($params['object']) || !$this->hasAutoReindexEnabled()) {
            return;
        }
        // if ($params['object'] instanceof Product) {
        //     return $this->fillCacheTable((int)$params['object']->id);
        // } elseif ($params['object'] instanceof Combination && !empty($params['object']->id_product)) {
        //     return $this->fillCacheTable((int)$params['object']->id_product);
        // }
    }
    public function hookActionObjectUpdateAfter($params)
    {
        if (!isset($params['object']) || !Validate::isLoadedObject($params['object']) || !$this->hasAutoReindexEnabled()) {
            return;
        }

        // $cacheDir = _PS_CACHE_DIR_ . 'pm_productsbyattributes/';
        // if (is_dir($cacheDir)) {
        //     $files = glob($cacheDir . '*.json');
        //     foreach ($files as $file) {
        //         unlink($file);
        //     }
        // }

        // if ($params['object'] instanceof Product) {
        //     return $this->fillCacheTable((int)$params['object']->id);
        // } elseif ($params['object'] instanceof Combination && !empty($params['object']->id_product)) {
        //     return $this->fillCacheTable((int)$params['object']->id_product);
        // }
    }
    public function hookActionObjectDeleteBefore($params)
    {
        if (!isset($params['object']) || !Validate::isLoadedObject($params['object']) || !$this->hasAutoReindexEnabled()) {
            return;
        }
        // if ($params['object'] instanceof Product) {
        //     return $this->fillCacheTable((int)$params['object']->id);
        // } elseif ($params['object'] instanceof Combination && !empty($params['object']->id_product)) {
        //     return $this->fillCacheTable((int)$params['object']->id_product);
        // }
    }
    public function splitProductsListOfSearchResults(&$params)
    {
        $conf = $this->getModuleConfiguration();
        $selectedSearchAttributes = [];
        $selectedSearchAttributesIdList = [];
        $packIdList = false;
        if (class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getIdsPacks')) {
            $packIdList = AdvancedPack::getIdsPacks(true);
        }
        // if (isset($params['module']) && in_array($params['module'], ['pm_advancedsearch4', 'pm_advancedsearch'])) {
        //     foreach ($params['selected_criterion'] as $id_criterion_group => $selected_criterions) {
        //         foreach ($selected_criterions as $selected_criterion) {
        //             if ($params['selected_criteria_groups_type'][(int)$id_criterion_group]['criterion_group_type'] != 'attribute') {
        //                 continue;
        //             }
        //             if (!empty($params['id_search'])) {
        //                 $idSearch = (int)$params['id_search'];
        //             } else {
        //                 $idSearch = (int)Tools::getValue('id_search', (int)Tools::getValue('id_seo_id_search', 0));

        //             }
        //             if (empty($idSearch)) {
        //                 continue;
        //             }
        //             $isVisible = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT visible FROM ' . _DB_PREFIX_ . 'pm_advancedsearch_criterion_group_' . (int)$idSearch . ' WHERE id_criterion_group = ' . (int)$id_criterion_group);
        //             if (empty($isVisible)) {
        //                 continue;
        //             }
        //             $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT id_criterion_linked FROM ' . _DB_PREFIX_ . 'pm_advancedsearch_criterion_' . (int)$idSearch . '_link WHERE id_criterion = ' . (int)$selected_criterion);
        //             foreach ($rows as $row) {
        //                 if (version_compare(_PS_VERSION_, '8.0.0', '>=') && class_exists('ProductAttribute')) {
        //                     $selectedAttribute = new ProductAttribute((int)$row['id_criterion_linked']);
        //                 } else {
        //                     $selectedAttribute = new Attribute((int)$row['id_criterion_linked']);
        //                 }
        //                 if (Validate::isLoadedObject($selectedAttribute)) {
        //                     if (!isset($selectedSearchAttributes[(int)$selectedAttribute->id_attribute_group])) {
        //                         $selectedSearchAttributes[(int)$selectedAttribute->id_attribute_group] = [];
        //                     }
        //                     $selectedSearchAttributes[(int)$selectedAttribute->id_attribute_group][] = $selectedAttribute->id;
        //                     $selectedSearchAttributesIdList[] = $selectedAttribute->id;
        //                 }
        //             }
        //         }
        //     }
        // }
        $newProductList = [];
        $already_done = [];
        $checkStock = (!empty($conf['hideCombinationsWithoutStock']) || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) && Configuration::get('PS_STOCK_MANAGEMENT');
        if (!empty($params['orderBy']) && $params['orderBy'] == 'orderprice') {
            Tools::orderbyPrice($params['catProducts'], Tools::strtolower($params['orderWay']));
        }
        $idProductListToCheck = [];
        foreach ($params['catProducts'] as $product) {
            $idProductListToCheck[] = (int)$product['id_product'];
        }
        $eligibleProducts = pm_productsbyattributes::getEligibleProducts($idProductListToCheck);

        // foreach ($params['catProducts'] as &$product) {
        //     if (!in_array((int)$product['id_product'], $eligibleProducts)) {
        //         $combinations = [];
        //     } else {
        //         if (empty($selectedSearchAttributesIdList) || empty(array_diff(array_keys($selectedSearchAttributes), $conf['selectedGroups']))) {
        //             // $combinations = pm_productsbyattributes::getAttributeCombinationsById((int)$product['id_product'], null, (int)$this->context->language->id, $conf['selectedGroups'], true);
        //             // foreach (array_keys($combinations) as $k) {
        //             //     $combinations[$k]['keepCombination'] = true;
        //             // }
        //         } else {
        //             // $combinations = pm_productsbyattributes::getAttributeCombinationsById((int)$product['id_product'], null, (int)$this->context->language->id, [], true);
        //             // foreach (array_keys($combinations) as $k) {
        //             //     foreach ($selectedSearchAttributesIdList as $idAttribute) {
        //             //         if (in_array($idAttribute, $combinations[$k]['id_attribute_list_custom'])) {
        //             //             $combinations[$k]['keepCombination'] = true;
        //             //             break;
        //             //         }
        //             //     }
        //             //     if (empty($combinations[$k]['keepCombination'])) {
        //             //         unset($combinations[$k]);
        //             //         continue;
        //             //     }
        //             // }
        //         }
        //         if (!is_array($combinations)) {
        //             continue;
        //         }
        //     }
        //     if (!count($combinations)) {
        //         if (isset($already_done[(int)$product['id_product'] . '_0'])) {
        //             continue;
        //         }
        //         $product['spa-no-eligible-combinations'] = true;
        //         $newProductList[] = $product;
        //         $already_done[(int)$product['id_product'] . '_0'] = true;
        //     }
        //     $isPack = (is_array($packIdList) && in_array((int)$product['id_product'], $packIdList));
        //     if ($isPack) {
        //         $newProductList[] = $product;
        //         $already_done[(int)$product['id_product'] . '_0'] = true;
        //         continue;
        //     }
        //     foreach ($combinations as $combination) {
        //         if (isset($already_done[(int)$combination['id_product'] . '_' . (int)$combination['id_product_attribute']])) {
        //             continue;
        //         }
        //         if (array_intersect($combination['id_attribute_group'], $conf['selectedGroups'])) {
        //             if (isset($params['module']) && in_array($params['module'], ['pm_advancedsearch4', 'pm_advancedsearch']) && count($selectedSearchAttributes) > 0) {
        //                 $attributesMatched = true;
        //                 foreach ($selectedSearchAttributes as $selectedAttributes) {
        //                     // $productAttributes = self::getAttributeCombinationsById((int)$combination['id_product'], (int)$combination['id_product_attribute'], (int)$this->context->language->id);
        //                     // $matchesCount = 0;
        //                     // foreach ($productAttributes as $product_attribute) {
        //                     //     if (array_intersect($product_attribute['id_attribute'], $selectedAttributes)) {
        //                     //         $matchesCount++;
        //                     //     }
        //                     // }
        //                     if (!empty($combination['keepCombination']) && ($matchesCount > 0 && $matchesCount <= count($selectedAttributes))) {
        //                         $attributesMatched &= true;
        //                     } else {
        //                         $attributesMatched &= false;
        //                     }
        //                 }
        //                 if (!$attributesMatched) {
        //                     $already_done[(int)$combination['id_product'] . '_' . (int)$combination['id_product_attribute']] = true;
        //                     continue;
        //                 }
        //             }
        //             if ($checkStock && (int)$combination['quantity'] <= 0 && empty($combination['forceCombinationAvailability'])) {
        //                 $already_done[(int)$combination['id_product'] . '_' . (int)$combination['id_product_attribute']] = true;
        //                 continue;
        //             }
        //             if (!$checkStock || ($checkStock && ((int)$combination['quantity'] > 0 || !empty($combination['forceCombinationAvailability'])))) {
        //                 $product['pai_id_product_attribute'] = (int)$combination['id_product_attribute'];
        //                 $product['id_product_attribute'] = (int)$combination['id_product_attribute'];
        //                 $product['cache_default_attribute'] = (int)$combination['id_product_attribute'];
        //                 $product['split-by-spa'] = true;
        //                 // if (!empty($conf['changeProductName'])) {
        //                 //     $product['product_name'] = pm_productsbyattributes::getFullProductName($product['name'], (int)$product['id_product'], (int)$combination['id_product_attribute'], (int)$this->context->language->id);
        //                 // }
        //                 $product['product_name'] = 'test';
        //                 $product['quantity_sql'] = $combination['quantity'];
        //                 $product['is_color_group'] = (bool)array_sum($combination['is_color_group']) > 0;
        //                 $already_done[(int)$combination['id_product'] . '_' . (int)$combination['id_product_attribute']] = true;
        //                 $newProductList[] = $product;
        //             }
        //         }
        //     }
        // }
        $params['nbProducts'] = count($newProductList);
        if (isset($params['products_per_page'])) {
            if (!empty($params['p']) && !empty($params['n'])) {
                $params['catProducts'] = array_slice($newProductList, (int)$params['products_per_page'] * ((int)$params['p'] - 1), $params['n']);
            } else {
                $params['catProducts'] = array_slice($newProductList, (int)$params['products_per_page'] * ((int)Tools::getValue('p', 1) - 1), Tools::getValue('n', (int)$params['products_per_page']));
            }
        } else {
            $params['catProducts'] = $newProductList;
        }
    }
    public function resortProductsAfterSplit(&$products, $params)
    {
        $orderBy = $params['orderBy'];
        $originalOrderBy = $params['originalOrderBy'];
        $orderWay = $params['orderWay'];
        if ($originalOrderBy == 'sales' && $orderBy == 'quantity') {
            $orderBy = 'sales';
        }
        if ($orderBy == 'orderprice') {
            Tools::orderbyPrice($products, Tools::strtolower($orderWay));
            return;
        }
        $firstProduct = current($products);
        if (!isset($firstProduct[$orderBy])) {
            return;
        }
        usort($products, function ($a, $b) use ($orderBy, $orderWay) {
            $aColumn = $bColumn = $orderBy;
            if ($orderBy == 'quantity') {
                if (isset($a['quantity_sql'])) {
                    $aColumn = 'quantity_sql';
                }
                if (isset($b['quantity_sql'])) {
                    $bColumn = 'quantity_sql';
                }
            }
            if ($orderWay == 'asc') {
                return $a[$aColumn] <=> $b[$bColumn];
            }
            return $b[$bColumn] <=> $a[$aColumn];
        });
    }

    // public function getCategoryProducts($id_category, $id_lang, $p, $n, $order_by = null, $order_way = null, $get_total = false, $fullTree = false)
    // {   
    //      $cacheKey = 'category_products_' . 
    //         'cat_' . $id_category . 
    //         '_lang_' . $id_lang . 
    //         '_order_' . ($order_by ?: 'position') . 
    //         '_' . ($order_way ?: 'ASC') . 
    //         '_total_' . ($get_total ? '1' : '0') . 
    //         '_tree_' . ($fullTree ? '1' : '0') .
    //         '_supplier_' . (int)Tools::getValue('id_supplier');
        
    //     if (!$get_total) {
    //         $fullListCacheKey = $cacheKey . '_full_list';
    //         $cachedFullList = $this->getFromCache($fullListCacheKey);
            
    //         if ($cachedFullList !== false) {
    //             $offset = (($p - 1) * $n);
    //             $pageProducts = array_slice($cachedFullList, $offset, $n);
                
    //             $expandedProducts = [];
    //             foreach ($pageProducts as $product) {
    //                 $fullProduct = $product;
                    
    //                 $fullProduct['id_shop_default'] = 1;
    //                 $fullProduct['id_lang'] = 1;
                    
    //                 $expandedProducts[] = $fullProduct;
    //             }
                
    //             return Product::getProductsProperties($id_lang, $expandedProducts);
    //         }
    //     } else {
    //         $cachedData = $this->getFromCache($cacheKey);
    //         if ($cachedData !== false) {
    //             return $cachedData;
    //         }
    //     }

    //     $context = Context::getContext();
    //     $currentCategory = new Category((int)$id_category);
    //     if (!Validate::isLoadedObject($currentCategory)) {
    //         if (method_exists($context->controller, 'getCategory')) {
    //             $currentCategory = $context->controller->getCategory();
    //         }
    //     }
    //     if (!Validate::isLoadedObject($currentCategory)) {
    //         if ($get_total) {
    //             $result = 0;
    //         } else {
    //             $result = [];
    //         }
    //         $this->saveToCache($cacheKey, $result);
    //         return $result;
    //     }
    //     if (!$id_lang) {
    //         $id_lang = (int)$this->context->language->id;
    //     }
    //     $conf = $this->getModuleConfiguration();
    //     $front = in_array($context->controller->controller_type, ['front', 'modulefront']);
    //     $id_supplier = (int)Tools::getValue('id_supplier');
    //     $packIdList = false;
    //     if (class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getIdsPacks')) {
    //         $packIdList = AdvancedPack::getIdsPacks(true);
    //     }
    //     $checkStock = ($conf['hideCombinationsWithoutStock'] || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) && Configuration::get('PS_STOCK_MANAGEMENT');
    //     $showDefaultCombinationIfOos = !empty($conf['showDefaultCombinationIfOos']);
    //     $oosSetting = (int)Configuration::get('PS_ORDER_OUT_OF_STOCK');
    //     if ($get_total) {
    //         $sql = '
    //                 SELECT COUNT(total) as total
    //                 FROM
    //                 (
    //                     (
    //                         SELECT COUNT(p.`id_product`) as total
    //                         FROM `' . _DB_PREFIX_ . 'category_product` cp
    //                         RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
    //                         ' . Shop::addSqlAssociation('product', 'p') . '
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
    //                         JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = p.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)$context->shop->id . ')
    //                         ' . ($checkStock ? Product::sqlStock('p', 'pa') : '') . '
    //                         JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')
    //                         WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
    //                         ' . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '') . '
    //                         AND product_shop.`active` = 1
    //                         AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
    //                         . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
    //                         . (is_array($packIdList) && count($packIdList) ? ' AND cp.id_product NOT IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '')
    //                         . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
    //                         . ' GROUP BY cp.`id_product`, pa.`id_product_attribute`
    //                     )
    //                     UNION ALL
    //                     (
    //                         SELECT COUNT(p.`id_product`) as total
    //                         FROM `' . _DB_PREFIX_ . 'category_product` cp
    //                         RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
    //                         ' . Shop::addSqlAssociation('product', 'p') . '
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product` AND pa.`default_on` = 1
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
    //                         LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')'
    //                         . Product::sqlStock('p', 0) . '
    //                         WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
    //                         AND product_shop.`active` = 1
    //                         AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
    //                         . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
    //                         . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
    //                         . ' GROUP BY cp.`id_product`
    //                         HAVING (COUNT(a.`id_attribute`) = 0' . (is_array($packIdList) && count($packIdList) ? ' OR cp.id_product IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '') . ')
    //                     )
    //                 ) total_query';
    //         $result = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    //         $this->saveToCache($cacheKey, $result);
    //         return $result;
    //         // return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    //     }
    //     $nb_days_new_product = Configuration::get('PS_NB_DAYS_NEW_PRODUCT');
    //     if (!Validate::isUnsignedInt($nb_days_new_product)) {
    //         $nb_days_new_product = 20;
    //     }
    //     $originalOrderBy = $order_by = Validate::isOrderBy($order_by) ? Tools::strtolower($order_by) : 'position';
    //     $order_way = Validate::isOrderWay($order_way) ? Tools::strtoupper($order_way) : 'ASC';
    //     $need_order_value_field = null;
    //     if ($order_by == 'date_add' || $order_by == 'date_upd') {
    //         $need_order_value_field = true;
    //         $order_by_prefix = 'p';
    //     } elseif ($originalOrderBy == 'sales') {
    //         $need_order_value_field = true;
    //         $order_by_prefix = 'p_sale';
    //         $order_by = 'quantity';
    //     } elseif ($order_by == 'position') {
    //         $need_order_value_field = true;
    //         $order_by_prefix = 'cp';
    //     } elseif ($order_by == 'id_product') {
    //         $need_order_value_field = true;
    //         $order_by_prefix = 'p';
    //     } elseif ($order_by == 'name') {
    //         $need_order_value_field = true;
    //         $order_by_prefix = 'pl';
    //     }
    //     $sortCombinationColumnName = null;
    //     $sortCombinationColumnWay = null;
    //     if (!empty($conf['sortCombinationBy'])) {
    //         switch ($conf['sortCombinationBy']) {
    //             case 'quantity_asc':
    //                 $sortCombinationColumnName = 'stock.`quantity`';
    //                 $sortCombinationColumnWay = 'ASC';
    //                 break;
    //             case 'quantity_desc':
    //                 $sortCombinationColumnName = 'stock.`quantity`';
    //                 $sortCombinationColumnWay = 'DESC';
    //                 break;
    //             case 'price_asc':
    //                 $sortCombinationColumnName = 'product_attribute_shop.`price`';
    //                 $sortCombinationColumnWay = 'ASC';
    //                 break;
    //             case 'price_desc':
    //                 $sortCombinationColumnName = 'product_attribute_shop.`price`';
    //                 $sortCombinationColumnWay = 'DESC';
    //                 break;
    //             case 'unit_price_asc':
    //                 $sortCombinationColumnName = 'product_attribute_shop.`unit_price_impact`';
    //                 $sortCombinationColumnWay = 'ASC';
    //                 break;
    //             case 'unit_price_desc':
    //                 $sortCombinationColumnName = 'product_attribute_shop.`unit_price_impact`';
    //                 $sortCombinationColumnWay = 'DESC';
    //                 break;
    //             case 'attribute_reference_asc':
    //                 $sortCombinationColumnName = 'pa.`reference`';
    //                 $sortCombinationColumnWay = 'ASC';
    //                 break;
    //             case 'attribute_reference_desc':
    //                 $sortCombinationColumnName = 'pa.`reference`';
    //                 $sortCombinationColumnWay = 'DESC';
    //                 break;
    //             case 'inherit':
    //                 break;
    //         }
    //     }
    //     $sql = '(SELECT ' . (!empty($need_order_value_field) ? $order_by_prefix . '.' . bqSQL($order_by) . ' AS `order_value`, ' : '') . 'p.*, product_shop.*, pl.`name`, stock.out_of_stock, IFNULL(stock.quantity, 0) AS quantity, IFNULL(stock.quantity, 0) AS quantity_sql,
    //                 IFNULL(product_attribute_shop.id_product_attribute, 0) AS id_product_attribute,
    //                 product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, pl.`description`, pl.`description_short`, pl.`available_now`,
    //                 pl.`available_later`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, image_shop.`id_image` id_image,
    //                 il.`legend` as legend, m.`name` AS manufacturer_name, cl.`name` AS category_default,
    //                 DATEDIFF(product_shop.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00",
    //                 INTERVAL ' . (int)$nb_days_new_product . ' DAY)) > 0 AS new, (product_shop.`price` + IFNULL(product_attribute_shop.`price`, 0)) AS orderprice, cp.`position`, a.`position` as `position_attribute`, pa.`id_product_attribute` as pai_id_product_attribute, MAX(ag.`is_color_group`),
    //                 CONCAT_WS("-", "spa", "a", p.id_product, pa.id_product_attribute) as `id_product_pack`' . (!empty($sortCombinationColumnName) ? ', ' . pSQL($sortCombinationColumnName) . ' AS `spa_sort_column`' : '') . ', 1 AS spa_eligible_product
    //             FROM `' . _DB_PREFIX_ . 'category_product` cp
    //             RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
    //             ' . Shop::addSqlAssociation('product', 'p') . '
    //             LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (product_shop.`id_category_default` = cl.`id_category` AND cl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('cl') . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$context->shop->id . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
    //             JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = p.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)$context->shop->id . ')
    //             JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
    //             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int)$id_lang . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (
    //                 p.`id_product` = product_attribute_shop.`id_product`
    //                 AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute`
    //                 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . '
    //             )'
    //             . ($originalOrderBy == 'sales' ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_sale` p_sale ON (p_sale.`id_product` = p.`id_product`) ' : '')
    //             . Product::sqlStock('p', 'pa') . '
    //             WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
    //                 AND product_shop.`active` = 1
    //                 AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
    //                 . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '')
    //                 . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
    //                 . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
    //                 . (is_array($packIdList) && count($packIdList) ? ' AND cp.id_product NOT IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '')
    //                 . ' GROUP BY cp.`id_product`, pa.`id_product_attribute`)';
    //     $sql .= '
    //     UNION ALL
    //     ';
    //     $sql .= '(SELECT ' . (!empty($need_order_value_field) ? $order_by_prefix . '.' . bqSQL($order_by) . ' AS `order_value`, ' : '') . 'p.*, product_shop.*, pl.`name`, stock.out_of_stock, IFNULL(stock.quantity, 0) AS quantity, IFNULL(stock.quantity, 0) AS quantity_sql,
    //                 IFNULL(product_attribute_shop.id_product_attribute, 0) AS id_product_attribute,
    //                 product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, pl.`description`, pl.`description_short`, pl.`available_now`,
    //                 pl.`available_later`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, image_shop.`id_image` id_image,
    //                 il.`legend` as legend, m.`name` AS manufacturer_name, cl.`name` AS category_default,
    //                 DATEDIFF(product_shop.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00", INTERVAL ' . (int)$nb_days_new_product . ' DAY)) > 0 AS new, (product_shop.`price` + IFNULL(product_attribute_shop.`price`, 0)) AS orderprice,
    //                 cp.`position`, a.`position` as `position_attribute`, pa.`id_product_attribute` as pai_id_product_attribute, "0" as `is_color_group`,
    //                 "spa-nochanges" as `id_product_pack`' . (!empty($sortCombinationColumnName) ? ', ' . pSQL($sortCombinationColumnName) . ' AS `spa_sort_column`' : '') . ', 0 AS spa_eligible_product
    //             FROM `' . _DB_PREFIX_ . 'category_product` cp
    //             RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
    //             ' . Shop::addSqlAssociation('product', 'p') . '
    //             LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (product_shop.`id_category_default` = cl.`id_category` AND cl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('cl') . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$context->shop->id . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ')
    //             LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
    //             LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
    //             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')'
    //             . ($originalOrderBy == 'sales' ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_sale` p_sale ON (p_sale.`id_product` = p.`id_product`) ' : '')
    //             . Product::sqlStock('p', 0) . '
    //             WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
    //                 AND product_shop.`active` = 1
    //                 AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
    //                 . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
    //                 . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
    //                . ' GROUP BY cp.id_product
    //                     HAVING (COUNT(a.`id_attribute`) = 0' . (is_array($packIdList) && count($packIdList) ? ' OR cp.id_product IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '') . ')
    //                )';
    //     if ($p < 1) {
    //         $p = 1;
    //     }
    //     $order_by2 = false;
    //     if ($order_by == 'date_add' || $order_by == 'date_upd' || $order_by == 'id_product' || $order_by == 'name') {
    //         $order_by = 'order_value';
    //     } elseif ($order_by == 'manufacturer' || $order_by == 'manufacturer_name') {
    //         $order_by = 'manufacturer_name';
    //     } elseif ($originalOrderBy == 'sales') {
    //         $order_by = 'order_value';
    //     } elseif ($order_by == 'quantity') {
    //         $order_by = 'quantity_sql';
    //     } elseif ($order_by == 'price') {
    //         $order_by = 'orderprice';
    //     } elseif ($order_by == 'position') {
    //         $order_by = 'order_value';
    //         if (empty($sortCombinationColumnName)) {
    //             $order_by2 = 'position_attribute';
    //         }
    //     }
    //     $sql .= ' ORDER BY `' . bqSQL($order_by) . '`' . (!empty($order_by2) ? ', `' . bqSQL($order_by2) . '`' : '') . ' ' . pSQL($order_way) . (!empty($sortCombinationColumnName) ? ', `spa_sort_column` ' . pSQL($sortCombinationColumnWay) : '');
    //     $sql .= ' LIMIT ' . (((int)$p - 1) * (int)$n) . ',' . (int)$n;
    //     $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql, true, false);
    //     if (!$products) {
    //         return [];
    //     }

    //     // foreach ($products as &$product) {
    //     //     if (empty($product['spa_eligible_product'])) {
    //     //         continue;
    //     //     }
    //     //     $combinationImage = Image::getBestImageAttribute((int)$this->context->shop->id, (int)$this->context->language->id, (int)$product['id_product'], (int)$product['pai_id_product_attribute']);
    //     //     $customIdImage = null;
    //     //     if (isset($combinationImage['id_image'])) {
    //     //         $customIdImage = (int)$combinationImage['id_image'];
    //     //     } else {
    //     //         if (!empty($product['spa-no-eligible-combinations']) || !isset($conf['hideCombinationsWithoutCover']) || !$conf['hideCombinationsWithoutCover']) {
    //     //             $cover = Product::getCover((int)$product['id_product']);
    //     //             if (!empty($cover['id_image'])) {
    //     //                 $customIdImage = (int)$cover['id_image'];
    //     //             }
    //     //         } else {
    //     //             unset($products[(int)$p]);
    //     //             continue;
    //     //         }
    //     //     }
    //     //     if (!empty($customIdImage)) {
    //     //         $product['cover_image_id'] = (int)$customIdImage;
    //     //     }
    //     // }

    //     if ($get_total) {
    //         $this->saveToCache($cacheKey, $result);
    //         return $result;
    //     } else {
    //         $sqlFullList = str_replace(
    //             ' LIMIT ' . (((int)$p - 1) * (int)$n) . ',' . (int)$n,
    //             '', 
    //             $sql
    //         );
            
    //         $allProducts = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlFullList, true, false);
            
    //         if ($allProducts) {
    //             foreach ($allProducts as &$product) {
    //                 if (empty($product['spa_eligible_product'])) {
    //                     continue;
    //                 }
    //                 $combinationImage = Image::getBestImageAttribute((int)$this->context->shop->id, (int)$this->context->language->id, (int)$product['id_product'], (int)$product['pai_id_product_attribute']);
    //                 $customIdImage = null;
    //                 if (isset($combinationImage['id_image'])) {
    //                     $customIdImage = (int)$combinationImage['id_image'];
    //                 } else {
    //                     if (!empty($product['spa-no-eligible-combinations']) || !isset($conf['hideCombinationsWithoutCover']) || !$conf['hideCombinationsWithoutCover']) {
    //                         $cover = Product::getCover((int)$product['id_product']);
    //                         if (!empty($cover['id_image'])) {
    //                             $customIdImage = (int)$cover['id_image'];
    //                         }
    //                     }
    //                 }
    //                 if (!empty($customIdImage)) {
    //                     $product['cover_image_id'] = (int)$customIdImage;
    //                 }
    //             }
                
    //            $allProductsFiltered = array_map(function($product) {
    //                 return [
    //                     'id_product' => $product['id_product'],
    //                     'id_product_attribute' => $product['id_product_attribute'] ?? $product['pai_id_product_attribute'] ?? 0,
    //                     'name' => $product['name'],
    //                     'price' => (float)($product['orderprice'] ?? $product['price'] ?? 0),
    //                     'link' => '',
    //                     'id_image' => $product['id_image'] ?? null,
    //                     'cover_image_id' => $product['cover_image_id'] ?? null,
    //                     'pai_id_product_attribute' => $product['pai_id_product_attribute'] ?? null,
    //                     'spa_eligible_product' => $product['spa_eligible_product'] ?? 0,
    //                     'split-by-spa' => $product['split-by-spa'] ?? false,
    //                     'id_product_pack' => $product['id_product_pack'] ?? null,
    //                 ];
    //             }, $allProducts);
                
    //             $fullListCacheKey = $cacheKey . '_full_list';
    //             $this->saveToCache($fullListCacheKey, $allProductsFiltered);
                
    //             $offset = (($p - 1) * $n);
    //             $pageProducts = array_slice($allProducts, $offset, $n);
                
    //             return Product::getProductsProperties($id_lang, $pageProducts);
    //         }
            
    //         return [];
    //     }
    // }


    public function getCategoryProducts($id_category, $id_lang, $p, $n, $order_by = null, $order_way = null, $get_total = false, $fullTree = false)
    {   
        $cacheKey = 'category_products_' . 
            'cat_' . $id_category . 
            '_lang_' . $id_lang . 
            '_order_' . ($order_by ?: 'position') . 
            '_' . ($order_way ?: 'ASC') . 
            '_total_' . ($get_total ? '1' : '0') . 
            '_tree_' . ($fullTree ? '1' : '0') .
            '_supplier_' . (int)Tools::getValue('id_supplier');
        
        if (!$get_total) {
            $fullListCacheKey = $cacheKey . '_full_list';
            $cachedFullList = $this->getFromCache($fullListCacheKey);
            
            if ($cachedFullList !== false) {
                $offset = (($p - 1) * $n);
                $pageProducts = array_slice($cachedFullList, $offset, $n);
                
                $expandedProducts = [];
                foreach ($pageProducts as $product) {
                    $fullProduct = $product;
                    
                    $fullProduct['id_shop_default'] = 1;
                    $fullProduct['id_lang'] = 1;
                    
                    $expandedProducts[] = $fullProduct;
                }
                
                // return Product::getProductsProperties($id_lang, $pageProducts);
                return $pageProductss;
            }
        } else {
            $cachedData = $this->getFromCache($cacheKey);
            if ($cachedData !== false) {
                return $cachedData;
            }
        }

        $context = Context::getContext();
        $currentCategory = new Category((int)$id_category);
        if (!Validate::isLoadedObject($currentCategory)) {
            if (method_exists($context->controller, 'getCategory')) {
                $currentCategory = $context->controller->getCategory();
            }
        }
        if (!Validate::isLoadedObject($currentCategory)) {
            if ($get_total) {
                $result = 0;
            } else {
                $result = [];
            }
            $this->saveToCache($cacheKey, $result);
            return $result;
        }
        if (!$id_lang) {
            $id_lang = (int)$this->context->language->id;
        }
        $conf = $this->getModuleConfiguration();
        $front = in_array($context->controller->controller_type, ['front', 'modulefront']);
        $id_supplier = (int)Tools::getValue('id_supplier');
        $packIdList = false;
        if (class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getIdsPacks')) {
            $packIdList = AdvancedPack::getIdsPacks(true);
        }
        $checkStock = ($conf['hideCombinationsWithoutStock'] || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) && Configuration::get('PS_STOCK_MANAGEMENT');
        $showDefaultCombinationIfOos = !empty($conf['showDefaultCombinationIfOos']);
        $oosSetting = (int)Configuration::get('PS_ORDER_OUT_OF_STOCK');
        
        if ($get_total) {
            $sql = '
                    SELECT COUNT(total) as total
                    FROM
                    (
                        (
                            SELECT COUNT(p.`id_product`) as total
                            FROM `' . _DB_PREFIX_ . 'category_product` cp
                            RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
                            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                            ' . Shop::addSqlAssociation('product', 'p') . '
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                            JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = p.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)$context->shop->id . ')
                            ' . ($checkStock ? Product::sqlStock('p', 'pa') : '') . '
                            JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')
                            WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
                            ' . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '') . '
                            AND product_shop.`active` = 1
                            AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
                            . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                            . (is_array($packIdList) && count($packIdList) ? ' AND cp.id_product NOT IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '')
                            . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
                            . ' GROUP BY cp.`id_product`, pa.`id_product_attribute`
                        )
                        UNION ALL
                        (
                            SELECT COUNT(p.`id_product`) as total
                            FROM `' . _DB_PREFIX_ . 'category_product` cp
                            RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
                            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                            ' . Shop::addSqlAssociation('product', 'p') . '
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product` AND pa.`default_on` = 1
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
                            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')'
                            . Product::sqlStock('p', 0) . '
                            WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
                            AND product_shop.`active` = 1
                            AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
                            . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                            . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
                            . ' GROUP BY cp.`id_product`
                            HAVING (COUNT(a.`id_attribute`) = 0' . (is_array($packIdList) && count($packIdList) ? ' OR cp.id_product IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '') . ')
                        )
                    ) total_query';
            $result = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
            $this->saveToCache($cacheKey, $result);
            return $result;
        }
        
        $originalOrderBy = $order_by = Validate::isOrderBy($order_by) ? Tools::strtolower($order_by) : 'position';
        $order_way = Validate::isOrderWay($order_way) ? Tools::strtoupper($order_way) : 'ASC';
        $need_order_value_field = null;
        if ($order_by == 'date_add' || $order_by == 'date_upd') {
            $need_order_value_field = true;
            $order_by_prefix = 'p';
        } elseif ($originalOrderBy == 'sales') {
            $need_order_value_field = true;
            $order_by_prefix = 'p_sale';
            $order_by = 'quantity';
        } elseif ($order_by == 'position') {
            $need_order_value_field = true;
            $order_by_prefix = 'cp';
        } elseif ($order_by == 'id_product') {
            $need_order_value_field = true;
            $order_by_prefix = 'p';
        } elseif ($order_by == 'name') {
            $need_order_value_field = true;
            $order_by_prefix = 'pl';
        }
        
        $sortCombinationColumnName = null;
        $sortCombinationColumnWay = null;
        if (!empty($conf['sortCombinationBy'])) {
            switch ($conf['sortCombinationBy']) {
                case 'quantity_asc':
                    $sortCombinationColumnName = 'stock.`quantity`';
                    $sortCombinationColumnWay = 'ASC';
                    break;
                case 'quantity_desc':
                    $sortCombinationColumnName = 'stock.`quantity`';
                    $sortCombinationColumnWay = 'DESC';
                    break;
                case 'price_asc':
                    $sortCombinationColumnName = 'product_attribute_shop.`price`';
                    $sortCombinationColumnWay = 'ASC';
                    break;
                case 'price_desc':
                    $sortCombinationColumnName = 'product_attribute_shop.`price`';
                    $sortCombinationColumnWay = 'DESC';
                    break;
                case 'unit_price_asc':
                    $sortCombinationColumnName = 'product_attribute_shop.`unit_price_impact`';
                    $sortCombinationColumnWay = 'ASC';
                    break;
                case 'unit_price_desc':
                    $sortCombinationColumnName = 'product_attribute_shop.`unit_price_impact`';
                    $sortCombinationColumnWay = 'DESC';
                    break;
                case 'attribute_reference_asc':
                    $sortCombinationColumnName = 'pa.`reference`';
                    $sortCombinationColumnWay = 'ASC';
                    break;
                case 'attribute_reference_desc':
                    $sortCombinationColumnName = 'pa.`reference`';
                    $sortCombinationColumnWay = 'DESC';
                    break;
                case 'inherit':
                    break;
            }
        }
        
        // Minimalne zapytanie SQL - tylko niezbędne kolumny
        $sql = '(SELECT ' . (!empty($need_order_value_field) ? $order_by_prefix . '.' . bqSQL($order_by) . ' AS `order_value`, ' : '') . '
                    p.`id_product`, 
                    p.`reference`,
                    p.`ean13`,
                    p.`active`,
                    p.`cache_default_attribute`,
                    product_shop.`price`,
                    product_shop.`visibility`,
                    product_shop.`id_category_default`,
                    product_shop.`minimal_quantity`,
                    pl.`name`, 
                    pl.`link_rewrite`,
                    stock.out_of_stock,
                    ' . ($checkStock ? 'IFNULL(stock.quantity, 0) AS quantity_sql,' : '') . '
                    IFNULL(product_attribute_shop.id_product_attribute, 0) AS id_product_attribute,
                    (product_shop.`price` + IFNULL(product_attribute_shop.`price`, 0)) AS orderprice, 
                    cp.`position`, 
                    a.`position` as `position_attribute`, 
                    pa.`id_product_attribute` as pai_id_product_attribute, 
                    MAX(ag.`is_color_group`),
                    CONCAT_WS("-", "spa", "a", p.id_product, pa.id_product_attribute) as `id_product_pack`' 
                    . (!empty($sortCombinationColumnName) ? ', ' . pSQL($sortCombinationColumnName) . ' AS `spa_sort_column`' : '') 
                    . ', 1 AS spa_eligible_product
                FROM `' . _DB_PREFIX_ . 'category_product` cp
                RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = p.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)$context->shop->id . ')
                JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (
                    p.`id_product` = product_attribute_shop.`id_product`
                    AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute`
                    AND product_attribute_shop.id_shop=' . (int)$context->shop->id . '
                )'
                . ($originalOrderBy == 'sales' ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_sale` p_sale ON (p_sale.`id_product` = p.`id_product`) ' : '')
                . Product::sqlStock('p', 'pa') . '
                WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
                    AND product_shop.`active` = 1
                    AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
                    . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '')
                    . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                    . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
                    . (is_array($packIdList) && count($packIdList) ? ' AND cp.id_product NOT IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '')
                    . ' GROUP BY cp.`id_product`, pa.`id_product_attribute`)';
        
        $sql .= '
        UNION ALL
        ';
        
        // Zapytanie dla produktów bez kombinacji - również minimalne
        $sql .= '(SELECT ' . (!empty($need_order_value_field) ? $order_by_prefix . '.' . bqSQL($order_by) . ' AS `order_value`, ' : '') . '
                    p.`id_product`,
                    p.`reference`,
                    p.`ean13`,
                    p.`active`,
                    p.`cache_default_attribute`,
                    product_shop.`price`,
                    product_shop.`visibility`,
                    product_shop.`id_category_default`,
                    product_shop.`minimal_quantity`,
                    pl.`name`, 
                    pl.`link_rewrite`,
                    stock.out_of_stock,
                    ' . ($checkStock ? 'IFNULL(stock.quantity, 0) AS quantity_sql,' : '') . '
                    IFNULL(product_attribute_shop.id_product_attribute, 0) AS id_product_attribute,
                    (product_shop.`price` + IFNULL(product_attribute_shop.`price`, 0)) AS orderprice,
                    cp.`position`, 
                    a.`position` as `position_attribute`, 
                    pa.`id_product_attribute` as pai_id_product_attribute, 
                    "0" as `is_color_group`,
                    "spa-nochanges" as `id_product_pack`' 
                    . (!empty($sortCombinationColumnName) ? ', ' . pSQL($sortCombinationColumnName) . ' AS `spa_sort_column`' : '') 
                    . ', 0 AS spa_eligible_product
                FROM `' . _DB_PREFIX_ . 'category_product` cp
                RIGHT JOIN `' . _DB_PREFIX_ . 'category` c ON (c.`id_category` = cp.`id_category` AND c.nleft >= ' . (int)$currentCategory->nleft . ' AND c.nright <= ' . (int)$currentCategory->nright . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute` AND (a.`id_attribute_group` IN (' . implode(',', array_map('intval', $conf['selectedGroups'])) . '))
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`id_product_attribute` = pac.`id_product_attribute` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')'
                . ($originalOrderBy == 'sales' ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_sale` p_sale ON (p_sale.`id_product` = p.`id_product`) ' : '')
                . Product::sqlStock('p', 0) . '
                WHERE product_shop.`id_shop` = ' . (int)$context->shop->id . '
                    AND product_shop.`active` = 1
                    AND cp.`id_category` ' . (!$fullTree ? '= ' . (int)$currentCategory->id : ' > 0')
                    . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                    . ($id_supplier ? ' AND p.id_supplier = ' . (int)$id_supplier : '')
                . ' GROUP BY cp.id_product
                        HAVING (COUNT(a.`id_attribute`) = 0' . (is_array($packIdList) && count($packIdList) ? ' OR cp.id_product IN (' . implode(',', array_map('intval', $packIdList)) . ') ' : '') . ')
                )';
        
        if ($p < 1) {
            $p = 1;
        }
        
        $order_by2 = false;
        if ($order_by == 'date_add' || $order_by == 'date_upd' || $order_by == 'id_product' || $order_by == 'name') {
            $order_by = 'order_value';
        } elseif ($order_by == 'manufacturer' || $order_by == 'manufacturer_name') {
            // Nie pobieramy już manufacturer_name, więc pomijamy to sortowanie
            $order_by = 'name';
        } elseif ($originalOrderBy == 'sales') {
            $order_by = 'order_value';
        } elseif ($order_by == 'quantity') {
            $order_by = 'quantity_sql';
        } elseif ($order_by == 'price') {
            $order_by = 'orderprice';
        } elseif ($order_by == 'position') {
            $order_by = 'order_value';
            if (empty($sortCombinationColumnName)) {
                $order_by2 = 'position_attribute';
            }
        }
        
        $sql .= ' ORDER BY `' . bqSQL($order_by) . '`' . (!empty($order_by2) ? ', `' . bqSQL($order_by2) . '`' : '') . ' ' . pSQL($order_way) . (!empty($sortCombinationColumnName) ? ', `spa_sort_column` ' . pSQL($sortCombinationColumnWay) : '');
        $sql .= ' LIMIT ' . (((int)$p - 1) * (int)$n) . ',' . (int)$n;
        
        $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql, true, false);
        if (!$products) {
            return [];
        }

        if ($get_total) {
            $this->saveToCache($cacheKey, $result);
            return $result;
        } else {
            $sqlFullList = str_replace(
                ' LIMIT ' . (((int)$p - 1) * (int)$n) . ',' . (int)$n,
                '', 
                $sql
            );
            
            $allProducts = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlFullList, true, false);
            
            if ($allProducts) {
                // Przetwarzanie obrazków zostaje bez zmian
                foreach ($allProducts as &$product) {
                    if (empty($product['spa_eligible_product'])) {
                        continue;
                    }
                    $combinationImage = Image::getBestImageAttribute((int)$this->context->shop->id, (int)$this->context->language->id, (int)$product['id_product'], (int)$product['pai_id_product_attribute']);
                    $customIdImage = null;
                    if (isset($combinationImage['id_image'])) {
                        $customIdImage = (int)$combinationImage['id_image'];
                    } else {
                        if (!empty($product['spa-no-eligible-combinations']) || !isset($conf['hideCombinationsWithoutCover']) || !$conf['hideCombinationsWithoutCover']) {
                            $cover = Product::getCover((int)$product['id_product']);
                            if (!empty($cover['id_image'])) {
                                $customIdImage = (int)$cover['id_image'];
                            }
                        }
                    }
                    if (!empty($customIdImage)) {
                        $product['cover_image_id'] = (int)$customIdImage;
                    }
                }
                
                // Filtrowanie do minimalnego zestawu danych dla cache
                $allProductsFiltered = array_map(function($product) {
                    return [
                        'id_product' => $product['id_product'],
                        'id_product_attribute' => $product['id_product_attribute'] ?? $product['pai_id_product_attribute'] ?? 0,
                        'name' => $product['name'],
                        'price' => (float)($product['orderprice'] ?? $product['price'] ?? 0),
                        'link' => '',
                        'id_image' => $product['id_image'] ?? null,
                        'cover_image_id' => $product['cover_image_id'] ?? null,
                        'pai_id_product_attribute' => $product['pai_id_product_attribute'] ?? null,
                        'spa_eligible_product' => $product['spa_eligible_product'] ?? 0,
                        'split-by-spa' => $product['split-by-spa'] ?? false,
                        'id_product_pack' => $product['id_product_pack'] ?? null,
                    ];
                }, $allProducts);
                
                $fullListCacheKey = $cacheKey . '_full_list';
                $this->saveToCache($fullListCacheKey, $allProductsFiltered);
                
                $offset = (($p - 1) * $n);
                $pageProducts = array_slice($allProducts, $offset, $n);
                
                // Product::getProductsProperties doda brakujące informacje jak URL-e i formatowanie
                // return Product::getProductsProperties($id_lang, $pageProducts);
                return $pageProducts;
            }
            
            return [];
        }
    }

    public function splitProductsList(&$products)
    {
        $conf = $this->getModuleConfiguration();
        $combined = [];
        $packIdList = false;
        if (class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getIdsPacks')) {
            $packIdList = AdvancedPack::getIdsPacks(true);
        }
        $psRewritingSettings = Configuration::get('PS_REWRITING_SETTINGS');
        foreach ($products as $p => &$product) {
            $isPack = (is_array($packIdList) && in_array((int)$product['id_product'], $packIdList));
            if (!$isPack && (!isset($product['id_product_pack']) || $product['id_product_pack'] != 'spa-nochanges')) {
                $combined[(int)$product['id_product']] = true;
                if (isset($product['pai_id_product_attribute'])) {
                    $product['id_product_attribute'] = (int)$product['pai_id_product_attribute'];
                    $product['cache_default_attribute'] = (int)$product['pai_id_product_attribute'];
                } else {
                    $product['pai_id_product_attribute'] = (int)$product['id_product_attribute'];
                    $product['cache_default_attribute'] = (int)$product['pai_id_product_attribute'];
                }
                $product['split-by-spa'] = true;
                if (isset($product['quantity_sql'])) {
                    $product['quantity'] = (int)$product['quantity_sql'];
                }
                $combinationImage = Image::getBestImageAttribute((int)$this->context->shop->id, (int)$this->context->language->id, (int)$product['id_product'], (int)$product['pai_id_product_attribute']);
                $customIdImage = null;
                if (isset($combinationImage['id_image'])) {
                    $customIdImage = (int)$combinationImage['id_image'];
                } else {
                    if (!empty($product['spa-no-eligible-combinations']) || !isset($conf['hideCombinationsWithoutCover']) || !$conf['hideCombinationsWithoutCover']) {
                        $cover = Product::getCover((int)$product['id_product']);
                        if (!empty($cover['id_image'])) {
                            $customIdImage = (int)$cover['id_image'];
                        }
                    } else {
                        unset($products[(int)$p]);
                        continue;
                    }
                }
                if (!empty($customIdImage)) {
                    $product['cover_image_id'] = (int)$customIdImage;
                }
                $product['id_product_pack'] = 'spa-' . $product['id_product'] . '-' . $product['id_product_attribute'];
                $product = Product::getProductProperties((int)$this->context->language->id, $product);
                if (!empty($conf['changeProductName'])) {
                    $product['name'] = pm_productsbyattributes::getFullProductName($product['name'], (int)$product['id_product'], (int)$product['id_product_attribute'], (int)$this->context->language->id);
                }
                if (!isset($product['category']) || $product['category'] == '') {
                    $product['category'] = Category::getLinkRewrite((int)$product['id_category_default'], (int)$this->context->language->id);
                }
                $product['link'] = $this->context->link->getProductLink((int)$product['id_product'], $product['link_rewrite'], $product['category'], $product['ean13'], null, null, (int)$product['pai_id_product_attribute'], $psRewritingSettings, false, true);
                if (isset($combinationImage['id_image'])) {
                    $product['id_image'] = (int)$combinationImage['id_image'];
                } else {
                    if (!empty($product['spa-no-eligible-combinations']) || !isset($conf['hideCombinationsWithoutCover']) || !$conf['hideCombinationsWithoutCover']) {
                        $product['attribute_image'] = (int)$customIdImage;
                        $product['id_image'] = Product::defineProductImage([
                            'id_product' => (int)$product['id_product'],
                            'id_image' => (int)$customIdImage,
                        ], (int)$this->context->language->id);
                    }
                }
            } else {
                $product = Product::getProductProperties((int)$this->context->language->id, $product);
                if (!$isPack) {
                    $product['quantity'] = (int)$product['quantity_sql'];
                }
                if (isset($combined[(int)$product['id_product']])) {
                    unset($products[(int)$p]);
                } else {
                    $combined[(int)$product['id_product']] = true;
                }
            }
        }
        return $products;
    }
    protected function getAttributeGroupOptions()
    {
        $conf = $this->getModuleConfiguration();
        $attributeGroups = AttributeGroup::getAttributesGroups((int)$this->context->language->id);
        $return = [];
        foreach ($conf['selectedGroups'] as $selectedAttributeGroup) {
            foreach ($attributeGroups as $attributeGroup) {
                if ((int)$attributeGroup['id_attribute_group'] == (int)$selectedAttributeGroup) {
                    $return[(int)$attributeGroup['id_attribute_group']] = $attributeGroup['name'];
                    break;
                }
            }
        }
        foreach ($attributeGroups as $attributeGroup) {
            if (!isset($return[(int)$attributeGroup['id_attribute_group']])) {
                $return[(int)$attributeGroup['id_attribute_group']] = $attributeGroup['name'];
            }
        }
        $module = Module::getInstanceByName('pm_advancedpack');
        if (Validate::isLoadedObject($module) && class_exists('AdvancedPack') && method_exists('AdvancedPack', 'getPackAttributeGroupId') && isset($return[(int)AdvancedPack::getPackAttributeGroupId()])) {
            unset($return[(int)AdvancedPack::getPackAttributeGroupId()]);
        }
        return $return;
    }
    protected function getSortCombinationsByOptions()
    {
        $options = [
            'inherit' => $this->l('Inherit current page sort order'),
            '' => $this->l('Splitted attributes positions/Attribute values positions'),
            'quantity_asc' => $this->l('Available quantity (asc)'),
            'quantity_desc' => $this->l('Available quantity (desc)'),
            'price_asc' => $this->l('Price impact (asc)'),
            'price_desc' => $this->l('Price impact (desc)'),
            'unit_price_asc' => $this->l('Unit price impact (asc)'),
            'unit_price_desc' => $this->l('Unit price impact (desc)'),
            'attribute_reference_asc' => $this->l('Product attribute reference (asc)'),
            'attribute_reference_desc' => $this->l('Product attribute reference (desc)'),
        ];
        if (!Configuration::get('PS_STOCK_MANAGEMENT')) {
            unset($options['quantity_asc']);
            unset($options['quantity_desc']);
        }
        return $options;
    }
    protected function getHighlightCombinationsOptions()
    {
        $options = [
            '' => $this->l('--- Default sorting ---'),
            'quantity_asc' => $this->l('with the lowest available quantity'),
            'quantity_desc' => $this->l('with the largest available quantity'),
            'price_asc' => $this->l('with the lowest price impact'),
            'price_desc' => $this->l('with the largest price impact'),
            'unit_price_asc' => $this->l('with the lowest unit price impact'),
            'unit_price_desc' => $this->l('with the largest unit price impact'),
        ];
        if (!Configuration::get('PS_STOCK_MANAGEMENT')) {
            unset($options['quantity_asc']);
            unset($options['quantity_desc']);
        }
        return $options;
    }
    protected static function getEligibleProducts($idProductList)
    {
        $cacheKey = 'eligible_products_' . md5(json_encode($idProductList));
        $cacheFile = _PS_CACHE_DIR_ . 'pm_productsbyattributes/static/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        static $conf = null;
        static $checkStock = false;
        static $showDefaultCombinationIfOos = false;
        static $oosSetting = null;
        if ($conf === null) {
            $conf = self::getModuleConfigurationStatic();
            $checkStock = ($conf['hideCombinationsWithoutStock'] || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) && Configuration::get('PS_STOCK_MANAGEMENT');
            $showDefaultCombinationIfOos = !empty($conf['showDefaultCombinationIfOos']);
            $oosSetting = (int)Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }
        $result = [];
        foreach (array_chunk($idProductList, 500) as $idProductListChunked) {
            $sql = 'SELECT pa.`id_product`
                    FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                    ' . Shop::addSqlAssociation('product_attribute', 'pa') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                    LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                    JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = pa.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)Context::getContext()->shop->id . ')
                    ' . ($checkStock ? 'JOIN `' . _DB_PREFIX_ . 'product` p ON (p.`id_product` = pa.`id_product`)' : '') . '
                    ' . ($checkStock ? Product::sqlStock('p', 'pa') : '') . '
                    WHERE pa.`id_product` IN (' . implode(',', array_map('intval', $idProductListChunked)) . ')
                    ' . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '') . '
                    GROUP BY pa.`id_product` HAVING COUNT(pa.`id_product_attribute`) > 0';
            $result = array_merge($result, Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql));
        }
        $validIdProductList = [];
        foreach ($result as $row) {
            $validIdProductList[] = (int)$row['id_product'];
        }
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($cacheFile, json_encode($validIdProductList));
        
        return $validIdProductList;
    }
    public static function getAttributeCombinationsById($idProduct, $idProductAttribute, $idLang, $idAttributeGroupList = [], $withQuantity = false)
    {
        // $cacheKey = 'attr_comb_' . md5(json_encode(func_get_args()));
        $cacheKey = 'attr_comb_product_' . $idProduct . '_lang_' . $idLang;
        $cacheFile = _PS_CACHE_DIR_ . 'pm_productsbyattributes/static/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $allCombinations = json_decode(file_get_contents($cacheFile), true);
            
            if (!empty($idProductAttribute)) {
                $result = [];
                foreach ($allCombinations as $row) {
                    if ($row['id_product_attribute'] == $idProductAttribute) {
                        $result[] = $row;
                    }
                }
                return $result;
            }
            
            return $allCombinations;
        }

        static $conf = null;
        static $checkStock = false;
        static $showDefaultCombinationIfOos = false;
        static $oosSetting = null;
        if ($conf === null) {
            $conf = self::getModuleConfigurationStatic();
            $checkStock = ($conf['hideCombinationsWithoutStock'] || !Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) && Configuration::get('PS_STOCK_MANAGEMENT');
            $showDefaultCombinationIfOos = !empty($conf['showDefaultCombinationIfOos']);
            $oosSetting = (int)Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }
        if ($withQuantity && !Configuration::get('PS_STOCK_MANAGEMENT')) {
            $withQuantity = $checkStock = false;
        }
        $sortCombinationBy = null;
        if (!empty($conf['sortCombinationBy'])) {
            switch ($conf['sortCombinationBy']) {
                case 'quantity_asc':
                    $sortCombinationBy = 'stock.`quantity` ASC';
                    $withQuantity = true;
                    break;
                case 'quantity_desc':
                    $sortCombinationBy = 'stock.`quantity` DESC';
                    $withQuantity = true;
                    break;
                case 'price_asc':
                    $sortCombinationBy = 'product_attribute_shop.`price` ASC';
                    break;
                case 'price_desc':
                    $sortCombinationBy = 'product_attribute_shop.`price` DESC';
                    break;
                case 'unit_price_asc':
                    $sortCombinationBy = 'product_attribute_shop.`unit_price_impact` ASC';
                    break;
                case 'unit_price_desc':
                    $sortCombinationBy = 'product_attribute_shop.`unit_price_impact` DESC';
                    break;
                case 'attribute_reference_asc':
                    $sortCombinationBy = 'pa.`reference` ASC';
                    break;
                case 'attribute_reference_desc':
                    $sortCombinationBy = 'pa.`reference` DESC';
                    break;
                case 'inherit':
                    break;
            }
        }
        $isPricesDropPage = false;
        if(Context::getContext()->controller->php_self === 'prices-drop' || Context::getContext()->controller->php_self === 'index') {
            $isPricesDropPage = true;
        }
        //$isPricesDropPage = (Context::getContext()->controller->php_self === 'prices-drop');

        $sql = 'SELECT pa.*, product_attribute_shop.*, GROUP_CONCAT(ag.`id_attribute_group`) AS `id_attribute_group`, GROUP_CONCAT(a.`id_attribute`) AS `id_attribute`, GROUP_CONCAT(al.`name` SEPARATOR "|s|p|a|") AS attribute_name, GROUP_CONCAT(ag.`is_color_group`) AS `is_color_group`' . ($withQuantity ? ', stock.`quantity` as `sa_quantity`, stock.`out_of_stock`' : '') . ', GROUP_CONCAT(pac.`id_attribute`) as id_attribute_list_custom' . ($checkStock || $withQuantity ? ', p.cache_default_attribute' : '') . '
                FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                ' . Shop::addSqlAssociation('product_attribute', 'pa') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int)$idLang . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'specific_price` ps ON (pa.`id_product_attribute` = ps.`id_product_attribute`)

                ' . (!empty($idAttributeGroupList) ? 'JOIN `' . _DB_PREFIX_ . 'pm_spa_cache` pa_cartesian ON (pa_cartesian.`id_product` = pa.`id_product` AND pa_cartesian.`id_product_attribute` = pa.`id_product_attribute` AND pa_cartesian.`id_shop` = ' . (int)Context::getContext()->shop->id . ')' : '') . '
                ' . ($checkStock || $withQuantity ? 'JOIN `' . _DB_PREFIX_ . 'product` p ON (p.`id_product` = pa.`id_product`)' : '') . '
                ' . ($checkStock || $withQuantity ? Product::sqlStock('p', 'pa') : '') . '
                WHERE pa.`id_product` = ' . (int)$idProduct . '
                ' . ($isPricesDropPage ? 'AND ps.`reduction` > 0' : '') . '
                ' . ($checkStock ? ' AND IF (stock.`quantity` > 0' . ($showDefaultCombinationIfOos ? ' OR (p.`cache_default_attribute` > 0 AND p.`cache_default_attribute` = pac.`id_product_attribute`)' : '') . ', 1, IF (stock.`out_of_stock` = 2, ' . (int)$oosSetting . ' = 1, stock.`out_of_stock` = 1)) ' : '') . '
                ' . (!empty($idProductAttribute) ? ' AND pa.`id_product_attribute` = ' . (int)$idProductAttribute : '') . '
                GROUP BY pa.`id_product_attribute`
                ORDER BY ' . (empty($sortCombinationBy) ? 'SUM(ag.`position`), SUM(a.`position`), pac.`id_product_attribute`' : pSQL($sortCombinationBy));
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        foreach ($result as &$r) {
            $r['id_attribute_list_custom'] = array_map('intval', explode(',', $r['id_attribute_list_custom']));
            $r['id_attribute_group'] = array_map('intval', explode(',', $r['id_attribute_group']));
            $r['id_attribute'] = array_map('intval', explode(',', $r['id_attribute']));
            $r['is_color_group'] = array_map('intval', explode(',', $r['is_color_group']));
            $r['attribute_name'] = array_map('trim', explode('|s|p|a|', $r['attribute_name']));
        }
        if ($withQuantity) {
            foreach ($result as &$row) {
                $row['quantity'] = (int)$row['sa_quantity'];
                unset($row['sa_quantity']);
                if ($row['out_of_stock'] == 2) {
                    $row['forceCombinationAvailability'] = ($oosSetting == 1);
                } else {
                    $row['forceCombinationAvailability'] = ($row['out_of_stock'] == 1);
                }
                if ($showDefaultCombinationIfOos && $row['quantity'] == 0 && $row['out_of_stock'] == 2 && $row['id_product_attribute'] == $row['cache_default_attribute']) {
                    $row['forceCombinationAvailability'] = true;
                }
            }
        }
        // if (empty($idProductAttribute) && !isset($cache[$idProduct])) {
        //     $cache[$idProduct] = $result;
        // }
        
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($cacheFile, json_encode($result));
        
        if (!empty($idProductAttribute)) {
            $filteredResult = [];
            foreach ($result as $row) {
                if ($row['id_product_attribute'] == $idProductAttribute) {
                    $filteredResult[] = $row;
                }
            }
            return $filteredResult;
        }
        
        return $result;
    }
    public static function getFullProductName($originalProductName, $idProduct, $idProductAttribute, $idLang)
    {
        static $conf = null;
        if ($conf === null) {
            $conf = self::getModuleConfigurationStatic();
        }
        $tmpProductName = [$originalProductName];
        $productAttributes = self::getAttributeCombinationsById((int)$idProduct, (int)$idProductAttribute, (int)$idLang);
        if (!empty($conf['selectedGroups'])) {
            foreach ($conf['selectedGroups'] as $idAttributeGroup) {
                foreach ($productAttributes as $productAttribute) {

                    $configPosition = array_search($idAttributeGroup, $productAttribute['id_attribute_group']);
                    if ($configPosition !== false) {

                        if($idAttributeGroup == 4) {
                             $productAttribute['attribute_name'][$configPosition] = 'Φ '.$productAttribute['attribute_name'][$configPosition];
                        }

                        if($idAttributeGroup == 1) {
                             $productAttribute['attribute_name'][$configPosition] = 'h='.$productAttribute['attribute_name'][$configPosition];
                        }

                        $tmpProductName[] = $productAttribute['attribute_name'][$configPosition];
                    }
                }
            }
        }

        return implode($conf['nameSeparator'], $tmpProductName);
    }
    // public static function productHasCombinations($idProduct)
    // {
    //     return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
    //         '
    //         SELECT COUNT(*)
    //         FROM `' . _DB_PREFIX_ . 'product_attribute` pa
    //         ' . Shop::addSqlAssociation('product_attribute', 'pa') . '
    //         WHERE pa.`id_product` = ' . (int)$idProduct
    //     ) > 0;
    // }
    public function getHideColorSquaresConf()
    {
        $conf = $this->getModuleConfiguration();
        if (isset($conf['hideColorSquares']) && $conf['hideColorSquares']) {
            return true;
        }
        return false;
    }
    public function getSplittedGroups()
    {
        $config = $this->getModuleConfiguration();
        if (!isset($config['selectedGroups']) || empty($config['selectedGroups'])) {
            return [];
        }
        if (!is_array($config['selectedGroups'])) {
            return [(int)$config['selectedGroups']];
        }
        return $config['selectedGroups'];
    }
}
