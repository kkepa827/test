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
class ProductsByAttributesCoreClass extends Module
{
    /**
     * The module's prefix to use for caching, configuration, etc...
     *
     * @var string
     */
    public static $_module_prefix = 'SPA';
    protected $_coreClassName;
    protected $_html = '';
    protected $_base_config_url = '';
    protected $_support_link = [];
    protected $_copyright_link = [
        'link' => '',
        'img' => '//www.presta-module.com/img/logo-module.JPG',
    ];
    protected $tabs = [];
    protected $default_tab = 'configuration';
    protected $_defaultConfiguration = [];
    public function __construct()
    {
        parent::__construct();
        $this->_coreClassName = Tools::strtolower(get_class());
        $this->_support_link = [
            // ['link' => $forum_url, 'target' => '_blank', 'label' => $this->l('Forum topic', $this->_coreClassName)],
            ['link' => 'https://addons.prestashop.com/contact-form.php?id_product=20451', 'target' => '_blank', 'label' => $this->l('Support contact', $this->_coreClassName)],
        ];
    }
    protected static function getDataSerialized($data, $type = 'base64')
    {
        if (is_array($data)) {
            return array_map($type . '_encode', [$data]);
        } else {
            return current(array_map($type . '_encode', [$data]));
        }
    }
    protected static function getDataUnserialized($data, $type = 'base64')
    {
        if (is_array($data)) {
            return array_map($type . '_decode', [$data]);
        } else {
            return current(array_map($type . '_decode', [$data]));
        }
    }
    public function checkIfModuleIsUpdate($updateDb = false, $displayConfirm = true, $firstInstall = false)
    {
        $previousVersion = Configuration::get('PM_' . self::$_module_prefix . '_LAST_VERSION');
        if (!$updateDb && $this->version != $previousVersion) {
            return false;
        }
        if ($firstInstall) {
        }
        if ($updateDb) {
            $config = $this->getModuleConfiguration();
            foreach ($this->_defaultConfiguration as $configKey => $configValue) {
                if (!isset($config[$configKey])) {
                    $config[$configKey] = $configValue;
                }
            }
            $this->setModuleConfiguration($config);
            if (!$firstInstall) {
                if (method_exists($this, 'processModuleUpdate')) {
                    $this->processModuleUpdate($previousVersion, $this->version);
                }
            }
            Configuration::updateValue('PM_' . self::$_module_prefix . '_LAST_VERSION', $this->version);
            if ($displayConfirm) {
                $this->context->controller->confirmations[] = $this->l('Module updated successfully', $this->_coreClassName);
            }
        }
        return true;
    }
    public function showRating($show = false)
    {
        $dismiss = Configuration::getGlobalValue('PM_' . self::$_module_prefix . '_DISMISS_RATING');
        if ($show && $dismiss != 1 && self::getNbDaysModuleUsage() >= 3) {
            $this->_html .= $this->display($this->getLocalPath(), 'views/templates/admin/core/show_rating.tpl');
        }
    }
    public function displayFooterSupport()
    {
        $pm_addons_products = $this->getAddonsModulesFromApi();
        $pm_products = [];
        if (!is_array($pm_addons_products)) {
            $pm_addons_products = [];
        }
        $this->shuffleArray($pm_addons_products);
        if (is_array($pm_addons_products) && count($pm_addons_products)) {
            $addonsList = $this->getPMAddons();
            if ($addonsList && is_array($addonsList) && count($addonsList)) {
                foreach (array_keys($addonsList) as $moduleName) {
                    foreach ($pm_addons_products as $k => $pm_addons_product) {
                        if ($pm_addons_product['name'] == $moduleName) {
                            unset($pm_addons_products[$k]);
                            break;
                        }
                    }
                }
            }
        }
        $this->context->smarty->assign([
            'support_links' => (is_array($this->_support_link) && count($this->_support_link) ? $this->_support_link : []),
            'copyright_link' => (is_array($this->_copyright_link) && count($this->_copyright_link) ? $this->_copyright_link : false),
            'pm_module_version' => $this->version,
            'pm_data' => $this->getPMdata(),
            'pm_addons_products' => $pm_addons_products,
        ]);
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/core/support.tpl');
    }
    private function shuffleArray(&$a)
    {
        if (is_array($a) && count($a)) {
            $ks = array_keys($a);
            shuffle($ks);
            $new = [];
            foreach ($ks as $k) {
                $new[$k] = $a[$k];
            }
            $a = $new;
            return true;
        }
        return false;
    }
    private function getAddonsModulesFromApi()
    {
        $modules = Configuration::get('PM_' . self::$_module_prefix . '_AM');
        $modules_date = (int)Configuration::get('PM_' . self::$_module_prefix . '_AMD');
        if ($modules && strtotime('+2 day', $modules_date) > time()) {
            return json_decode($modules, true);
        }
        $jsonResponse = $this->doHttpRequest();
        if (empty($jsonResponse->products)) {
            return [];
        }
        $dataToStore = [];
        foreach ($jsonResponse->products as $addonsEntry) {
            $dataToStore[(int)$addonsEntry->id] = [
                'name' => $addonsEntry->name,
                'displayName' => $addonsEntry->displayName,
                'url' => $addonsEntry->url,
                'compatibility' => $addonsEntry->compatibility,
                'version' => $addonsEntry->version,
                'description' => $addonsEntry->description,
            ];
        }
        Configuration::updateValue('PM_' . self::$_module_prefix . '_AM', json_encode($dataToStore));
        Configuration::updateValue('PM_' . self::$_module_prefix . '_AMD', time());
        return json_decode(Configuration::get('PM_' . self::$_module_prefix . '_AM'), true);
    }
    private function getPMAddons()
    {
        $pmAddons = [];
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT DISTINCT name FROM ' . _DB_PREFIX_ . 'module WHERE name LIKE "pm_%"');
        if ($result && is_array($result) && count($result)) {
            foreach ($result as $module) {
                $instance = Module::getInstanceByName($module['name']);
                if (!empty($instance) && !empty($instance->version)) {
                    $pmAddons[(string)$module['name']] = $instance->version;
                }
            }
        }
        return $pmAddons;
    }
    private function doHttpRequest($data = [], $c = 'prestashop', $s = 'api.addons')
    {
        $data = array_merge([
            'version' => _PS_VERSION_,
            'iso_lang' => Tools::strtolower($this->context->language->iso_code),
            'iso_code' => Tools::strtolower(Country::getIsoById((int)Configuration::get('PS_COUNTRY_DEFAULT'))),
            'module_key' => $this->module_key,
            'method' => 'contributor',
            'action' => 'all_products',
        ], $data);
        $postData = http_build_query($data);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => $postData,
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'timeout' => 15,
            ],
        ]);
        $response = Tools::file_get_contents('https://' . $s . '.' . $c . '.com', false, $context);
        if (empty($response)) {
            return false;
        }
        $responseToJson = json_decode($response);
        if (empty($responseToJson)) {
            return false;
        }
        return $responseToJson;
    }
    
    private function getPMdata()
    {
        $param = [];
        $param[] = 'ver-' . _PS_VERSION_;
        $param[] = 'current-' . $this->name;
        
        $result = $this->getPMAddons();
        if ($result && is_array($result) && count($result)) {
            foreach ($result as $moduleName => $moduleVersion) {
                $param[] = $moduleName . '-' . $moduleVersion;
            }
        }
        return $this->getDataSerialized(implode('|', $param));
    }
    private static function getNbDaysModuleUsage()
    {
        $sql = 'SELECT DATEDIFF(NOW(),date_add)
                FROM ' . _DB_PREFIX_ . 'configuration
                WHERE name = \'' . pSQL('PM_' . self::$_module_prefix . '_LAST_VERSION') . '\'
                ORDER BY date_add ASC';
        return (int)Db::getInstance()->getValue($sql);
    }
    protected function getModuleConfiguration($shop = null)
    {
        if (Validate::isLoadedObject($shop)) {
            $conf = Configuration::get('PM_' . self::$_module_prefix . '_CONF', null, null, (int)$shop->id);
        } else {
            $conf = Configuration::get('PM_' . self::$_module_prefix . '_CONF');
        }
        if (!empty($conf)) {
            $json = json_decode($conf, true);
            foreach ($this->_defaultConfiguration as $k => $v) {
                if (!isset($json[$k])) {
                    $json[$k] = $this->_defaultConfiguration[$k];
                }
            }
            return $json;
        }
        return $this->_defaultConfiguration;
    }
    public static function getModuleConfigurationStatic()
    {
        $conf = Configuration::get('PM_' . self::$_module_prefix . '_CONF');
        if (!empty($conf)) {
            return json_decode($conf, true);
        }
        return [];
    }
    protected function setModuleConfiguration($newConf)
    {
        Configuration::updateValue('PM_' . self::$_module_prefix . '_CONF', json_encode($newConf));
    }
    protected function setDefaultConfiguration()
    {
        if (!is_array($this->getModuleConfiguration()) || !count($this->getModuleConfiguration())) {
            Configuration::updateValue('PM_' . self::$_module_prefix . '_CONF', json_encode($this->_defaultConfiguration));
        }
        return true;
    }
    public function getContent()
    {
        if (Tools::getIsset('dismissRating') && Tools::getValue('dismissRating')) {
            Configuration::updateGlobalValue('PM_' . self::$_module_prefix . '_DISMISS_RATING', 1);
        } else {
            if (Tools::getIsset('submitModuleConfiguration') && Tools::isSubmit('submitModuleConfiguration')) {
                if (method_exists($this, 'postProcess')) {
                    $this->postProcess();
                }
            }
            if (Tools::getIsset('submitAjaxMethod') && Tools::isSubmit('submitAjaxMethod')) {
                if (method_exists($this, '_postProcessAjax')) {
                    $this->_postProcessAjax();
                }
            }
            if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
                $this->context->controller->errors[] = $this->l('You must select a specific shop in order to continue. You can\'t manage the module configuration from the "all shops" or "group of shops" context', $this->_coreClassName);
                return;
            }
            if (version_compare(_PS_VERSION_, '1.7.7.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJqueryUI(['ui.sortable']);
            $this->context->controller->addJS($this->_path . 'views/js/selectize/selectize.min.js');
            $this->context->controller->addCSS($this->_path . 'views/css/selectize/selectize.ps.css');
            $this->context->controller->addCSS($this->_path . 'views/css/admin-module.css', 'all');
            $this->context->controller->addJS($this->_path . 'views/js/admin-module.js');
            $this->addAssets();
            $this->_base_config_url = $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name;
            $this->context->smarty->assign([
                '_base_config_url' => $this->_base_config_url,
                '_path' => $this->_path,
                'module_prefix' => self::$_module_prefix,
                'pm_module_name' => $this->name,
                'employee_language' => (int)$this->context->employee->id_lang,
            ]);
            if (Tools::getValue('makeUpdate')) {
                $this->checkIfModuleIsUpdate(true);
            }
            if (!$this->checkIfModuleIsUpdate(false)) {
                return $this->display($this->getLocalPath(), 'views/templates/admin/core/new_version_available.tpl');
            }
            $this->showRating(true);
            $this->processContent();
            $selected_tab = Tools::getValue('selected_tab', $this->default_tab);
            if (!array_key_exists($selected_tab, $this->tabs)) {
                $selected_tab = $this->default_tab;
            }
            $this->context->smarty->assign('pm_tabs', $this->tabs);
            $this->context->smarty->assign('pm_selected_tab', $selected_tab);
            $this->context->smarty->assign('configurations', $this->getModuleConfiguration());
            $this->_html .= $this->display($this->getLocalPath(), 'views/templates/admin/get_content.tpl');
            $this->_html .= $this->displayFooterSupport();
            return $this->_html;
        }
    }
    protected function processContent()
    {
    }
    public function addAssets()
    {
        $this->context->controller->addJqueryUI('ui.tabs');
        $this->context->controller->addJqueryPlugin('chosen');
        $this->context->controller->addJS($this->_path . 'views/js/jquery.tiptip.min.js');
    }
}
