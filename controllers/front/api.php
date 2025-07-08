<?php

class CustomersearchtrackerApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $action = Tools::getValue('action');
        $token = Tools::getValue('token');
        
        // Validate API token
        if (!$this->validateToken($token)) {
            $this->ajaxDie(json_encode(array(
                'error' => true,
                'message' => 'Invalid token'
            )));
        }
        
        switch ($action) {
            case 'getTopSearches':
                $this->getTopSearches();
                break;
            case 'getSearchTrends':
                $this->getSearchTrends();
                break;
            case 'getNoResultsSearches':
                $this->getNoResultsSearches();
                break;
            case 'getCustomerSearchHistory':
                $this->getCustomerSearchHistory();
                break;
            case 'getSearchInsights':
                $this->getSearchInsights();
                break;
            default:
                $this->ajaxDie(json_encode(array(
                    'error' => true,
                    'message' => 'Invalid action'
                )));
        }
    }
    
    private function validateToken($token)
    {
        // Simple token validation - enhance for production
        return $token === md5(_COOKIE_KEY_.'customersearchtracker');
    }
    
    private function getTopSearches()
    {
        $days = (int)Tools::getValue('days', 30);
        $limit = (int)Tools::getValue('limit', 20);
        
        $sql = 'SELECT 
                    search_query,
                    COUNT(*) as search_count,
                    AVG(results_count) as avg_results,
                    SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as no_results_count,
                    MAX(date_add) as last_searched
                FROM '._DB_PREFIX_.'search_tracker
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL '.$days.' DAY)
                GROUP BY search_query
                ORDER BY search_count DESC
                LIMIT '.$limit;
        
        $results = Db::getInstance()->executeS($sql);
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'data' => $results
        )));
    }
    
    private function getSearchTrends()
    {
        $days = (int)Tools::getValue('days', 30);
        $groupBy = Tools::getValue('group_by', 'day'); // day, week, month
        
        $dateFormat = '%Y-%m-%d';
        if ($groupBy == 'week') {
            $dateFormat = '%Y-%u';
        } elseif ($groupBy == 'month') {
            $dateFormat = '%Y-%m';
        }
        
        $sql = 'SELECT 
                    DATE_FORMAT(date_add, "'.$dateFormat.'") as period,
                    COUNT(*) as total_searches,
                    COUNT(DISTINCT search_query) as unique_searches,
                    COUNT(DISTINCT COALESCE(id_customer, ip_address)) as unique_users
                FROM '._DB_PREFIX_.'search_tracker
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL '.$days.' DAY)
                GROUP BY period
                ORDER BY period ASC';
        
        $results = Db::getInstance()->executeS($sql);
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'data' => $results
        )));
    }
    
    private function getNoResultsSearches()
    {
        $days = (int)Tools::getValue('days', 30);
        
        $sql = 'SELECT 
                    search_query,
                    COUNT(*) as attempts,
                    MAX(date_add) as last_attempted
                FROM '._DB_PREFIX_.'search_tracker
                WHERE results_count = 0
                    AND date_add >= DATE_SUB(NOW(), INTERVAL '.$days.' DAY)
                GROUP BY search_query
                ORDER BY attempts DESC
                LIMIT 50';
        
        $results = Db::getInstance()->executeS($sql);
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'data' => $results
        )));
    }
    
    private function getCustomerSearchHistory()
    {
        $customerId = (int)Tools::getValue('customer_id');
        $limit = (int)Tools::getValue('limit', 50);
        
        $sql = 'SELECT 
                    search_query,
                    results_count,
                    date_add
                FROM '._DB_PREFIX_.'search_tracker
                WHERE id_customer = '.$customerId.'
                ORDER BY date_add DESC
                LIMIT '.$limit;
        
        $results = Db::getInstance()->executeS($sql);
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'data' => $results
        )));
    }
    
    private function getSearchInsights()
    {
        $insights = array();
        
        // Peak search times
        $peakTimes = Db::getInstance()->executeS('
            SELECT 
                HOUR(date_add) as hour,
                COUNT(*) as searches
            FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY hour
            ORDER BY searches DESC
            LIMIT 5
        ');
        
        $insights['peak_hours'] = $peakTimes;
        
        // Search complexity
        $avgWordCount = Db::getInstance()->getValue('
            SELECT AVG(LENGTH(search_query) - LENGTH(REPLACE(search_query, " ", "")) + 1)
            FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        
        $insights['avg_word_count'] = round($avgWordCount, 2);
        
        // Mobile vs Desktop (based on user agent)
        $deviceStats = Db::getInstance()->executeS('
            SELECT 
                CASE 
                    WHEN user_agent LIKE "%Mobile%" THEN "Mobile"
                    ELSE "Desktop"
                END as device_type,
                COUNT(*) as count
            FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY device_type
        ');
        
        $insights['device_distribution'] = $deviceStats;
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'data' => $insights
        )));
    }
}