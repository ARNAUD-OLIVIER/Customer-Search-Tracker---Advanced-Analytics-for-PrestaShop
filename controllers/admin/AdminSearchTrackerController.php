<?php

class AdminSearchTrackerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'search_tracker';
        $this->className = 'SearchTracker';
        $this->module = 'customersearchtracker';
        $this->bootstrap = true;
        $this->context = Context::getContext();
        
        parent::__construct();
        
        $this->fields_list = array(
            'id_search' => array(
                'title' => $this->l('ID'),
                'width' => 50,
                'type' => 'text'
            ),
            'search_query' => array(
                'title' => $this->l('Search Query'),
                'width' => 200,
                'type' => 'text'
            ),
            'results_count' => array(
                'title' => $this->l('Results'),
                'width' => 80,
                'type' => 'text',
                'badge_success' => true,
                'badge_danger' => 0
            ),
            'customer_name' => array(
                'title' => $this->l('Customer'),
                'width' => 150,
                'type' => 'text',
                'havingFilter' => true
            ),
            'date_add' => array(
                'title' => $this->l('Date'),
                'width' => 150,
                'type' => 'datetime'
            )
        );
        
        $this->_select = 'CONCAT(c.firstname, " ", c.lastname) as customer_name';
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'customer` c ON (a.`id_customer` = c.`id_customer`)';
        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';
    }

    public function renderList()
    {
        $this->addRowAction('view');
        return parent::renderList();
    }

    public function initContent()
    {
        parent::initContent();
        
        // Add dashboard before list
        $this->content = $this->renderDashboard() . $this->content;
    }

    private function renderDashboard()
    {
        $helper = new HelperList();
        
        // Get statistics
        $stats = $this->getSearchStatistics();
        
        $html = '<div class="panel">
            <h3><i class="icon-bar-chart"></i> '.$this->l('Search Analytics Dashboard').'</h3>
            <div class="row">
                <div class="col-lg-3">
                    <div class="panel panel-info">
                        <div class="panel-heading">'.$this->l('Total Searches').'</div>
                        <div class="panel-body text-center">
                            <h2>'.$stats['total_searches'].'</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel panel-success">
                        <div class="panel-heading">'.$this->l('Unique Terms').'</div>
                        <div class="panel-body text-center">
                            <h2>'.$stats['unique_terms'].'</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel panel-warning">
                        <div class="panel-heading">'.$this->l('No Results Rate').'</div>
                        <div class="panel-body text-center">
                            <h2>'.$stats['no_results_rate'].'%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel panel-danger">
                        <div class="panel-heading">'.$this->l('Active Users').'</div>
                        <div class="panel-body text-center">
                            <h2>'.$stats['active_users'].'</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        // Top searches
        $html .= $this->renderTopSearches();
        
        return $html;
    }

    private function getSearchStatistics()
    {
        $total = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        
        $unique = Db::getInstance()->getValue('
            SELECT COUNT(DISTINCT search_query) FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        
        $noResults = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM '._DB_PREFIX_.'search_tracker
            WHERE results_count = 0 AND date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        
        $activeUsers = Db::getInstance()->getValue('
            SELECT COUNT(DISTINCT COALESCE(id_customer, ip_address)) FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ');
        
        return array(
            'total_searches' => $total,
            'unique_terms' => $unique,
            'no_results_rate' => $total > 0 ? round(($noResults / $total) * 100, 1) : 0,
            'active_users' => $activeUsers
        );
    }

    private function renderTopSearches()
    {
        $topSearches = Db::getInstance()->executeS('
            SELECT search_query, COUNT(*) as count, 
                   AVG(results_count) as avg_results,
                   SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as no_results
            FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_query
            ORDER BY count DESC
            LIMIT 10
        ');
        
        $html = '<div class="panel">
            <h3><i class="icon-search"></i> '.$this->l('Top 10 Searches (Last 30 Days)').'</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>'.$this->l('Search Term').'</th>
                        <th>'.$this->l('Count').'</th>
                        <th>'.$this->l('Avg Results').'</th>
                        <th>'.$this->l('No Results Count').'</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($topSearches as $search) {
            $html .= '<tr>
                <td><strong>'.htmlspecialchars($search['search_query']).'</strong></td>
                <td>'.$search['count'].'</td>
                <td>'.round($search['avg_results'], 1).'</td>
                <td>'.$search['no_results'].'</td>
            </tr>';
        }
        
        $html .= '</tbody></table></div>';
        
        return $html;
    }

    public function ajaxProcessGetSearchTrends()
    {
        $days = Tools::getValue('days', 7);
        
        $data = Db::getInstance()->executeS('
            SELECT DATE(date_add) as date, COUNT(*) as searches
            FROM '._DB_PREFIX_.'search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL '.(int)$days.' DAY)
            GROUP BY DATE(date_add)
            ORDER BY date ASC
        ');
        
        die(json_encode($data));
    }

    public function postProcess()
    {
        if (Tools::isSubmit('exportSearchData')) {
            $this->exportSearchData();
        }
        
        parent::postProcess();
    }

    private function exportSearchData()
    {
        $sql = 'SELECT st.*, CONCAT(c.firstname, " ", c.lastname) as customer_name
                FROM '._DB_PREFIX_.'search_tracker st
                LEFT JOIN '._DB_PREFIX_.'customer c ON st.id_customer = c.id_customer
                ORDER BY st.date_add DESC';
        
        $data = Db::getInstance()->executeS($sql);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="search_data_'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Customer', 'Search Query', 'Results', 'IP', 'Date'));
        
        foreach ($data as $row) {
            fputcsv($output, array(
                $row['id_search'],
                $row['customer_name'] ?: 'Guest',
                $row['search_query'],
                $row['results_count'],
                $row['ip_address'],
                $row['date_add']
            ));
        }
        
        fclose($output);
        exit;
    }
}