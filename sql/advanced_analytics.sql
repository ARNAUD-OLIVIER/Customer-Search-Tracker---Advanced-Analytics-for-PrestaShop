-- Stored procedures for advanced analytics

DELIMITER $$

-- Get search conversion funnel
CREATE PROCEDURE sp_search_conversion_funnel(IN days INT)
BEGIN
    SELECT 
        'Searches' as stage,
        COUNT(DISTINCT st.id_search) as count
    FROM ps_search_tracker st
    WHERE st.date_add >= DATE_SUB(NOW(), INTERVAL days DAY)
    
    UNION ALL
    
    SELECT 
        'Clicked Results' as stage,
        COUNT(DISTINCT st.id_search) as count
    FROM ps_search_tracker st
    INNER JOIN ps_search_clicks sc ON st.id_search = sc.id_search
    WHERE st.date_add >= DATE_SUB(NOW(), INTERVAL days DAY)
    
    UNION ALL
    
    SELECT 
        'Added to Cart' as stage,
        COUNT(DISTINCT st.id_search) as count
    FROM ps_search_tracker st
    INNER JOIN ps_search_clicks sc ON st.id_search = sc.id_search
    INNER JOIN ps_cart_product cp ON sc.id_product = cp.id_product
    WHERE st.date_add >= DATE_SUB(NOW(), INTERVAL days DAY)
        AND cp.date_add BETWEEN st.date_add AND DATE_ADD(st.date_add, INTERVAL 1 HOUR)
    
    UNION ALL
    
    SELECT 
        'Purchased' as stage,
        COUNT(DISTINCT st.id_search) as count
    FROM ps_search_tracker st
    INNER JOIN ps_search_clicks sc ON st.id_search = sc.id_search
    INNER JOIN ps_order_detail od ON sc.id_product = od.product_id
    INNER JOIN ps_orders o ON od.id_order = o.id_order
    WHERE st.date_add >= DATE_SUB(NOW(), INTERVAL days DAY)
        AND o.date_add BETWEEN st.date_add AND DATE_ADD(st.date_add, INTERVAL 7 DAY);
END$$

-- Get related search terms
CREATE PROCEDURE sp_get_related_searches(IN search_term VARCHAR(255))
BEGIN
    WITH user_searches AS (
        SELECT DISTINCT id_customer, ip_address
        FROM ps_search_tracker
        WHERE search_query = search_term
            AND id_customer IS NOT NULL OR ip_address IS NOT NULL
    )
    SELECT 
        st.search_query,
        COUNT(*) as frequency,
        AVG(st.results_count) as avg_results
    FROM ps_search_tracker st
    INNER JOIN user_searches us 
        ON (st.id_customer = us.id_customer OR st.ip_address = us.ip_address)
    WHERE st.search_query != search_term
    GROUP BY st.search_query
    ORDER BY frequency DESC
    LIMIT 20;
END$$

-- Get search seasonality patterns
CREATE PROCEDURE sp_search_seasonality(IN search_term VARCHAR(255))
BEGIN
    SELECT 
        MONTH(date_add) as month,
        MONTHNAME(date_add) as month_name,
        COUNT(*) as search_count,
        AVG(results_count) as avg_results
    FROM ps_search_tracker
    WHERE search_query LIKE CONCAT('%', search_term, '%')
    GROUP BY MONTH(date_add), MONTHNAME(date_add)
    ORDER BY month;
END$$

DELIMITER ;

-- Additional tables for enhanced tracking
CREATE TABLE IF NOT EXISTS `ps_search_clicks` (
    `id_click` int(11) NOT NULL AUTO_INCREMENT,
    `id_search` int(11) NOT NULL,
    `id_product` int(11) NOT NULL,
    `position` int(11) NOT NULL,
    `click_time` datetime NOT NULL,
    PRIMARY KEY (`id_click`),
    KEY `idx_search` (`id_search`),
    KEY `idx_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_search_intents` (
    `id_intent` int(11) NOT NULL AUTO_INCREMENT,
    `id_search` int(11) NOT NULL,
    `intent_type` varchar(50) NOT NULL,
    `confidence` float DEFAULT 0,
    PRIMARY KEY (`id_intent`),
    KEY `idx_search_intent` (`id_search`, `intent_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_search_suggestions` (
    `id_suggestion` int(11) NOT NULL AUTO_INCREMENT,
    `original_query` varchar(255) NOT NULL,
    `suggested_query` varchar(255) NOT NULL,
    `suggestion_type` varchar(50) DEFAULT 'spell_correction',
    `times_shown` int(11) DEFAULT 0,
    `times_clicked` int(11) DEFAULT 0,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_suggestion`),
    KEY `idx_original` (`original_query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;