<?php
/**
 * Customer Search Tracker
 * Track and analyze customer search behavior
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerSearchTracker extends Module
{
    public function __construct()
    {
        $this->name = 'customersearchtracker';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'CODEX for LO';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Customer Search Tracker');
        $this->description = $this->l('Track customer searches to understand their needs better');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionSearch') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->installDb() &&
            $this->installTab();
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            $this->uninstallDb() &&
            $this->uninstallTab();
    }

    private function installDb()
    {
        $sql = array();
        
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'search_tracker` (
            `id_search` int(11) NOT NULL AUTO_INCREMENT,
            `id_customer` int(11) DEFAULT NULL,
            `id_shop` int(11) NOT NULL,
            `search_query` varchar(255) NOT NULL,
            `results_count` int(11) DEFAULT 0,
            `clicked_result` int(11) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text,
            `referer` text,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_search`),
            KEY `idx_customer` (`id_customer`),
            KEY `idx_query` (`search_query`),
            KEY `idx_date` (`date_add`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'search_analytics` (
            `id_analytics` int(11) NOT NULL AUTO_INCREMENT,
            `search_term` varchar(255) NOT NULL,
            `search_count` int(11) DEFAULT 1,
            `conversion_count` int(11) DEFAULT 0,
            `no_results_count` int(11) DEFAULT 0,
            `avg_click_position` float DEFAULT NULL,
            `date_updated` datetime NOT NULL,
            PRIMARY KEY (`id_analytics`),
            UNIQUE KEY `search_term` (`search_term`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function uninstallDb()
    {
        $sql = array();
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'search_tracker`';
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'search_analytics`';
        
        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSearchTracker';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Search Tracker';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminStats');
        $tab->module = $this->name;
        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminSearchTracker');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    public function hookActionSearch($params)
    {
        $this->trackSearch($params);
    }

    private function trackSearch($params)
    {
        $context = Context::getContext();
        
        $data = array(
            'id_customer' => $context->customer->id ?: null,
            'id_shop' => $context->shop->id,
            'search_query' => pSQL($params['expr']),
            'results_count' => isset($params['total']) ? (int)$params['total'] : 0,
            'ip_address' => Tools::getRemoteAddr(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'date_add' => date('Y-m-d H:i:s')
        );

        Db::getInstance()->insert('search_tracker', $data);
        $this->updateAnalytics($params['expr'], $data['results_count']);
    }

    private function updateAnalytics($searchTerm, $resultsCount)
    {
        $exists = Db::getInstance()->getValue('
            SELECT id_analytics FROM '._DB_PREFIX_.'search_analytics
            WHERE search_term = "'.pSQL($searchTerm).'"
        ');

        if ($exists) {
            $sql = 'UPDATE '._DB_PREFIX_.'search_analytics 
                    SET search_count = search_count + 1,
                        no_results_count = no_results_count + '.($resultsCount == 0 ? 1 : 0).',
                        date_updated = NOW()
                    WHERE search_term = "'.pSQL($searchTerm).'"';
        } else {
            $sql = 'INSERT INTO '._DB_PREFIX_.'search_analytics 
                    (search_term, search_count, no_results_count, date_updated)
                    VALUES ("'.pSQL($searchTerm).'", 1, '.($resultsCount == 0 ? 1 : 0).', NOW())';
        }
        
        Db::getInstance()->execute($sql);
    }

    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submitSearchTrackerModule')) {
            Configuration::updateValue('SEARCH_TRACKER_ENABLED', Tools::getValue('SEARCH_TRACKER_ENABLED'));
            Configuration::updateValue('SEARCH_TRACKER_RETENTION', Tools::getValue('SEARCH_TRACKER_RETENTION'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        
        return $output . $this->displayForm();
    }

    private function displayForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable tracking'),
                        'name' => 'SEARCH_TRACKER_ENABLED',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->l('No'))
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Data retention (days)'),
                        'name' => 'SEARCH_TRACKER_RETENTION',
                        'size' => 10,
                        'required' => true
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            )
        );
        
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitSearchTrackerModule';
        
        $helper->fields_value['SEARCH_TRACKER_ENABLED'] = Configuration::get('SEARCH_TRACKER_ENABLED', true);
        $helper->fields_value['SEARCH_TRACKER_RETENTION'] = Configuration::get('SEARCH_TRACKER_RETENTION', 90);
        
        return $helper->generateForm(array($fields_form));
    }
}