<?php
/**
 * Cron job for search data cleanup and optimization
 * Run daily via crontab: 0 2 * * * /usr/bin/php /path/to/cleanup.php
 */

require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../../../init.php';

class SearchTrackerCleanup
{
    private $retentionDays;
    private $db;
    
    public function __construct()
    {
        $this->retentionDays = (int)Configuration::get('SEARCH_TRACKER_RETENTION', 90);
        $this->db = Db::getInstance();
    }
    
    public function run()
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting search tracker cleanup...\n";
        
        $this->cleanOldSearches();
        $this->aggregateAnalytics();
        $this->optimizeTables();
        $this->generateDailyReport();
        
        echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed successfully!\n";
    }
    
    private function cleanOldSearches()
    {
        $sql = 'DELETE FROM '._DB_PREFIX_.'search_tracker 
                WHERE date_add < DATE_SUB(NOW(), INTERVAL '.$this->retentionDays.' DAY)';
        
        $result = $this->db->execute($sql);
        $affected = $this->db->Affected_Rows();
        
        echo "- Deleted $affected old search records\n";
    }
    
    private function aggregateAnalytics()
    {
        // Update search analytics summary
        $sql = 'INSERT INTO '._DB_PREFIX_.'search_analytics 
                (search_term, search_count, no_results_count, date_updated)
                SELECT 
                    search_query,
                    COUNT(*) as count,
                    SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END),
                    NOW()
                FROM '._DB_PREFIX_.'search_tracker
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                GROUP BY search_query
                ON DUPLICATE KEY UPDATE
                    search_count = search_count + VALUES(search_count),
                    no_results_count = no_results_count + VALUES(no_results_count),
                    date_updated = NOW()';
        
        $this->db->execute($sql);
        echo "- Updated search analytics\n";
    }
    
    private function optimizeTables()
    {
        $tables = array(
            'search_tracker',
            'search_analytics',
            'search_clicks',
            'search_intents'
        );
        
        foreach ($tables as $table) {
            $this->db->execute('OPTIMIZE TABLE '._DB_PREFIX_.$table);
        }
        
        echo "- Optimized database tables\n";
    }
    
    private function generateDailyReport()
    {
        $stats = array();
        
        // Get yesterday's stats
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $stats['total_searches'] = $this->db->getValue('
            SELECT COUNT(*) FROM '._DB_PREFIX_.'search_tracker
            WHERE DATE(date_add) = "'.$yesterday.'"
        ');
        
        $stats['unique_terms'] = $this->db->getValue('
            SELECT COUNT(DISTINCT search_query) FROM '._DB_PREFIX_.'search_tracker
            WHERE DATE(date_add) = "'.$yesterday.'"
        ');
        
        $stats['no_results'] = $this->db->getValue('
            SELECT COUNT(*) FROM '._DB_PREFIX_.'search_tracker
            WHERE DATE(date_add) = "'.$yesterday.'" AND results_count = 0
        ');
        
        // Top searches
        $stats['top_searches'] = $this->db->executeS('
            SELECT search_query, COUNT(*) as count
            FROM '._DB_PREFIX_.'search_tracker
            WHERE DATE(date_add) = "'.$yesterday.'"
            GROUP BY search_query
            ORDER BY count DESC
            LIMIT 10
        ');
        
        // Save report
        $reportPath = dirname(__FILE__).'/../reports/';
        if (!is_dir($reportPath)) {
            mkdir($reportPath, 0755, true);
        }
        
        $reportFile = $reportPath.'daily_report_'.$yesterday.'.json';
        file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT));
        
        echo "- Generated daily report: $reportFile\n";
        
        // Send email if configured
        $this->sendReportEmail($stats, $yesterday);
    }
    
    private function sendReportEmail($stats, $date)
    {
        $adminEmail = Configuration::get('PS_SHOP_EMAIL');
        if (!$adminEmail) {
            return;
        }
        
        $subject = 'Search Tracker Daily Report - '.$date;
        
        $html = '<h2>Search Analytics Report for '.$date.'</h2>';
        $html .= '<p><strong>Total Searches:</strong> '.$stats['total_searches'].'</p>';
        $html .= '<p><strong>Unique Terms:</strong> '.$stats['unique_terms'].'</p>';
        $html .= '<p><strong>No Results:</strong> '.$stats['no_results'].'</p>';
        
        $html .= '<h3>Top 10 Searches</h3><ol>';
        foreach ($stats['top_searches'] as $search) {
            $html .= '<li>'.$search['search_query'].' ('.$search['count'].' times)</li>';
        }
        $html .= '</ol>';
        
        Mail::Send(
            Configuration::get('PS_LANG_DEFAULT'),
            'contact',
            $subject,
            array('{message}' => $html),
            $adminEmail,
            null,
            null,
            null,
            null,
            null,
            dirname(__FILE__).'/../mails/',
            false,
            null,
            null,
            null,
            null,
            null,
            true
        );
        
        echo "- Sent report email to $adminEmail\n";
    }
}

// Run cleanup
$cleanup = new SearchTrackerCleanup();
$cleanup->run();