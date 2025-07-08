# Customer Search Tracker - Advanced Analytics for PrestaShop

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7+-red.svg)
![Version](https://img.shields.io/badge/version-1.0.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.2+-purple.svg)

<p align="center">
  <img src="customersearchtracker/logo.svg" alt="Customer Search Tracker Logo" width="200">
</p>

## 🚀 Overview

Customer Search Tracker is a powerful PrestaShop module that provides deep insights into customer search behavior. Track what your customers are searching for, identify product gaps, and optimize your catalog based on real user needs.

## ✨ Features

### Core Functionality
- **Real-time Search Tracking** - Capture every search query with detailed metadata
- **Advanced Analytics Dashboard** - Beautiful React-based visualizations
- **No-Results Detection** - Identify missing products customers want
- **Customer Journey Mapping** - Track individual customer search patterns
- **Search Intent Analysis** - AI-powered categorization of search queries

### Advanced Features
- **Machine Learning Insights** - Python-based pattern recognition
- **API Access** - RESTful endpoints for external integrations
- **Export Functionality** - CSV/JSON data exports
- **Email Reports** - Automated daily/weekly summaries
- **Multi-shop Support** - Track searches across all your stores
- **GDPR Compliant** - Built-in data retention policies

## 📊 Screenshots

<details>
<summary>Analytics Dashboard</summary>

![Dashboard](https://via.placeholder.com/800x400?text=Analytics+Dashboard)
</details>

<details>
<summary>Real-time Monitor</summary>

![Monitor](https://via.placeholder.com/800x400?text=Real-time+Search+Monitor)
</details>

<details>
<summary>Search Insights</summary>

![Insights](https://via.placeholder.com/800x400?text=Search+Insights)
</details>

## 🛠️ Installation

### Requirements
- PrestaShop 1.7.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher
- Node.js 14+ (for building assets)

### Quick Install

1. **Download the module**
   ```bash
   git clone https://github.com/yourusername/customersearchtracker.git
   ```

2. **Build frontend assets**
   ```bash
   cd customersearchtracker
   npm install
   npm run build
   ```

3. **Upload to PrestaShop**
   - Zip the module folder
   - Go to Modules > Module Manager in your PrestaShop admin
   - Click "Upload a module"
   - Select your zip file

4. **Configure the module**
   - Navigate to Modules > Customer Search Tracker
   - Set retention period and other preferences
   - Save configuration

## 🔧 Configuration

### Basic Settings
```php
// config/settings.php
define('SEARCH_TRACKER_ENABLED', true);
define('SEARCH_TRACKER_RETENTION', 90); // days
define('SEARCH_TRACKER_REALTIME', true);
```

### Database Configuration
The module automatically creates these tables:
- `ps_search_tracker` - Main search logs
- `ps_search_analytics` - Aggregated data
- `ps_search_clicks` - Click tracking
- `ps_search_intents` - AI-categorized intents

### API Token
Generate your API token for external access:
```php
$token = md5(_COOKIE_KEY_ . 'customersearchtracker');
```

## 📡 API Documentation

### Get Top Searches
```bash
GET /module/customersearchtracker/api?action=getTopSearches&days=30&token=YOUR_TOKEN
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "search_query": "wireless headphones",
      "search_count": 156,
      "avg_results": 23.5,
      "no_results_count": 0
    }
  ]
}
```

### Get Search Trends
```bash
GET /module/customersearchtracker/api?action=getSearchTrends&days=7&group_by=day&token=YOUR_TOKEN
```

### Get Customer Search History
```bash
GET /module/customersearchtracker/api?action=getCustomerSearchHistory&customer_id=123&token=YOUR_TOKEN
```

## 🤖 Machine Learning Setup

### Requirements
```bash
pip install pandas numpy scikit-learn mysql-connector-python
```

### Running ML Analysis
```bash
python ml/search_predictor.py --config ml/config.json
```

### Scheduling ML Jobs
```cron
0 3 * * * /usr/bin/python3 /path/to/module/ml/search_predictor.py
```

## 📅 Cron Jobs

Add to your crontab:
```bash
# Daily cleanup and optimization
0 2 * * * /usr/bin/php /path/to/module/cron/cleanup.php

# Hourly analytics aggregation
0 * * * * /usr/bin/php /path/to/module/cron/aggregate.php

# Weekly ML analysis
0 0 * * 0 /usr/bin/python3 /path/to/module/ml/search_predictor.py
```

## 🔌 Hooks

The module uses these PrestaShop hooks:
- `actionSearch` - Main search tracking
- `displayBackOfficeHeader` - Admin assets
- `actionFrontControllerSetMedia` - Frontend tracking
- `displayAdminStatsModules` - Statistics integration

### Custom Hook Usage
```php
// Track custom search implementations
Hook::exec('customSearchTrack', array(
    'query' => $searchTerm,
    'results' => $resultCount,
    'custom_data' => $additionalData
));
```

## 🚨 Troubleshooting

### Common Issues

**Issue: No searches being tracked**
```sql
-- Check if tracking is enabled
SELECT * FROM ps_configuration WHERE name = 'SEARCH_TRACKER_ENABLED';
```

**Issue: Dashboard not loading**
```bash
# Rebuild assets
npm run build

# Clear PrestaShop cache
rm -rf var/cache/*
```

**Issue: High database usage**
```sql
-- Check table sizes
SELECT 
    table_name,
    round(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.tables
WHERE table_schema = 'your_db_name'
    AND table_name LIKE 'ps_search_%';
```

## 🔒 Security

### Data Protection
- All search data is anonymized after retention period
- IP addresses are hashed for privacy
- GDPR export/delete functionality included

### API Security
```php
// Implement rate limiting
if ($this->isRateLimited($apiToken)) {
    http_response_code(429);
    exit('Rate limit exceeded');
}
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup
```bash
# Clone repo
git clone https://github.com/yourusername/customersearchtracker.git

# Install dependencies
npm install
composer install

# Run tests
npm test
./vendor/bin/phpunit

# Start development
npm run dev
```

## 📄 License

This module is released under the [MIT License](LICENSE).

## 🙏 Credits

Developed with ❤️ by ARNAUD-OLIVIER

### Special Thanks
- PrestaShop Community
- Chart.js Contributors
- React Team

## 🗺️ Roadmap

### Version 1.1.0 (Q2 2025)
- [ ] Voice search tracking
- [ ] Multi-language search analysis
- [ ] Advanced ML predictions
- [ ] Mobile app integration

### Version 1.2.0 (Q3 2025)
- [ ] Elasticsearch integration
- [ ] A/B testing for search results
- [ ] Behavioral targeting
- [ ] Advanced export formats

## ⚡ Performance

Optimized for high-traffic stores:
- Asynchronous tracking (no impact on search speed)
- Indexed database queries
- Cached analytics data
- CDN-ready assets

### Benchmarks
- Tracking overhead: <5ms per search
- Dashboard load time: <2s
- API response time: <100ms

---

<p align="center">
  Made with 💙 for the PrestaShop Community
</p>
