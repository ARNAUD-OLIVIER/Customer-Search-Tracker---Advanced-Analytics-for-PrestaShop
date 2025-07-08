import React, { useState, useEffect } from 'react';
import { Line, Bar, Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, registerables } from 'chart.js';
import axios from 'axios';

ChartJS.register(...registerables);

const SearchDashboard = () => {
    const [searchData, setSearchData] = useState({
        topSearches: [],
        trends: [],
        noResults: [],
        insights: {}
    });
    const [loading, setLoading] = useState(true);
    const [dateRange, setDateRange] = useState(30);

    useEffect(() => {
        fetchDashboardData();
    }, [dateRange]);

    const fetchDashboardData = async () => {
        setLoading(true);
        try {
            const token = generateApiToken();
            const baseUrl = window.prestashop.urls.base_url;
            
            const [topSearches, trends, noResults, insights] = await Promise.all([
                axios.get(`${baseUrl}module/customersearchtracker/api?action=getTopSearches&days=${dateRange}&token=${token}`),
                axios.get(`${baseUrl}module/customersearchtracker/api?action=getSearchTrends&days=${dateRange}&token=${token}`),
                axios.get(`${baseUrl}module/customersearchtracker/api?action=getNoResultsSearches&days=${dateRange}&token=${token}`),
                axios.get(`${baseUrl}module/customersearchtracker/api?action=getSearchInsights&token=${token}`)
            ]);

            setSearchData({
                topSearches: topSearches.data.data,
                trends: trends.data.data,
                noResults: noResults.data.data,
                insights: insights.data.data
            });
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
        }
        setLoading(false);
    };

    const generateApiToken = () => {
        // Generate token based on your security requirements
        return md5(window.prestashop.static_token + 'customersearchtracker');
    };

    const trendChartData = {
        labels: searchData.trends.map(t => t.period),
        datasets: [
            {
                label: 'Total Searches',
                data: searchData.trends.map(t => t.total_searches),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                tension: 0.1
            },
            {
                label: 'Unique Searches',
                data: searchData.trends.map(t => t.unique_searches),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                tension: 0.1
            }
        ]
    };

    const topSearchesChartData = {
        labels: searchData.topSearches.slice(0, 10).map(s => s.search_query),
        datasets: [{
            label: 'Search Count',
            data: searchData.topSearches.slice(0, 10).map(s => s.search_count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.6)',
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)',
                'rgba(199, 199, 199, 0.6)',
                'rgba(83, 102, 255, 0.6)',
                'rgba(255, 99, 255, 0.6)',
                'rgba(99, 255, 132, 0.6)'
            ],
            borderWidth: 1
        }]
    };

    const deviceChartData = searchData.insights.device_distribution ? {
        labels: searchData.insights.device_distribution.map(d => d.device_type),
        datasets: [{
            data: searchData.insights.device_distribution.map(d => d.count),
            backgroundColor: ['#36A2EB', '#FF6384'],
            hoverBackgroundColor: ['#36A2EB', '#FF6384']
        }]
    } : null;

    if (loading) {
        return <div className="text-center p-5">
            <div className="spinner-border" role="status">
                <span className="sr-only">Loading...</span>
            </div>
        </div>;
    }

    return (
        <div className="search-dashboard">
            <div className="dashboard-header mb-4">
                <h2>Search Analytics Dashboard</h2>
                <div className="date-range-selector">
                    <select 
                        className="form-control" 
                        value={dateRange} 
                        onChange={(e) => setDateRange(e.target.value)}
                    >
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                    </select>
                </div>
            </div>

            <div className="row">
                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header">
                            <h5>Search Trends</h5>
                        </div>
                        <div className="card-body">
                            <Line data={trendChartData} options={{
                                responsive: true,
                                plugins: {
                                    legend: { position: 'top' },
                                    title: { display: false }
                                }
                            }} />
                        </div>
                    </div>
                </div>

                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header">
                            <h5>Top 10 Searches</h5>
                        </div>
                        <div className="card-body">
                            <Bar data={topSearchesChartData} options={{
                                responsive: true,
                                plugins: {
                                    legend: { display: false },
                                    title: { display: false }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            autoSkip: false,
                                            maxRotation: 45,
                                            minRotation: 45
                                        }
                                    }
                                }
                            }} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="row">
                <div className="col-lg-4 mb-4">
                    <div className="card">
                        <div className="card-header">
                            <h5>Device Distribution</h5>
                        </div>
                        <div className="card-body">
                            {deviceChartData && <Doughnut data={deviceChartData} />}
                        </div>
                    </div>
                </div>

                <div className="col-lg-4 mb-4">
                    <div className="card">
                        <div className="card-header">
                            <h5>Search Insights</h5>
                        </div>
                        <div className="card-body">
                            <div className="insight-item">
                                <strong>Avg Words per Search:</strong>
                                <span className="float-right">{searchData.insights.avg_word_count || 0}</span>
                            </div>
                            <hr />
                            <div className="insight-item">
                                <strong>Peak Search Hours:</strong>
                                <ul className="mt-2">
                                    {searchData.insights.peak_hours && searchData.insights.peak_hours.map((hour, idx) => (
                                        <li key={idx}>{hour.hour}:00 - {hour.searches} searches</li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="col-lg-4 mb-4">
                    <div className="card">
                        <div className="card-header bg-warning text-white">
                            <h5>No Results Searches</h5>
                        </div>
                        <div className="card-body">
                            <div className="no-results-list">
                                {searchData.noResults.slice(0, 10).map((item, idx) => (
                                    <div key={idx} className="no-result-item mb-2">
                                        <strong>{item.search_query}</strong>
                                        <span className="badge badge-danger float-right">{item.attempts} attempts</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <SearchRecommendations topSearches={searchData.topSearches} />
            <RealTimeMonitor />
        </div>
    );
};

const SearchRecommendations = ({ topSearches }) => {
    const [recommendations, setRecommendations] = useState([]);

    useEffect(() => {
        generateRecommendations();
    }, [topSearches]);

    const generateRecommendations = () => {
        const recs = [];
        
        // Find searches with no results
        const noResults = topSearches.filter(s => s.avg_results < 1);
        if (noResults.length > 0) {
            recs.push({
                type: 'warning',
                title: 'Missing Products',
                message: `${noResults.length} popular searches return no results. Consider adding products for: ${noResults.slice(0, 3).map(s => s.search_query).join(', ')}`
            });
        }

        // Find trending searches
        const trending = topSearches.filter(s => s.search_count > 50);
        if (trending.length > 0) {
            recs.push({
                type: 'info',
                title: 'Trending Searches',
                message: `Focus on optimizing results for: ${trending.slice(0, 3).map(s => s.search_query).join(', ')}`
            });
        }

        setRecommendations(recs);
    };

    return (
        <div className="recommendations-section mt-4">
            <h4>AI Recommendations</h4>
            <div className="row">
                {recommendations.map((rec, idx) => (
                    <div key={idx} className="col-md-6 mb-3">
                        <div className={`alert alert-${rec.type}`}>
                            <h5>{rec.title}</h5>
                            <p>{rec.message}</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

const RealTimeMonitor = () => {
    const [recentSearches, setRecentSearches] = useState([]);
    const [isMonitoring, setIsMonitoring] = useState(false);

    useEffect(() => {
        let interval;
        if (isMonitoring) {
            interval = setInterval(fetchRecentSearches, 5000);
        }
        return () => clearInterval(interval);
    }, [isMonitoring]);

    const fetchRecentSearches = async () => {
        try {
            const response = await axios.get('/module/customersearchtracker/realtime');
            setRecentSearches(response.data.searches);
        } catch (error) {
            console.error('Failed to fetch real-time data:', error);
        }
    };

    return (
        <div className="real-time-monitor mt-4">
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h4>Real-Time Search Monitor</h4>
                <button 
                    className={`btn btn-${isMonitoring ? 'danger' : 'success'}`}
                    onClick={() => setIsMonitoring(!isMonitoring)}
                >
                    {isMonitoring ? 'Stop Monitoring' : 'Start Monitoring'}
                </button>
            </div>
            
            {isMonitoring && (
                <div className="real-time-feed">
                    {recentSearches.map((search, idx) => (
                        <div key={idx} className="search-item animate-in">
                            <span className="timestamp">{search.time}</span>
                            <span className="query">{search.query}</span>
                            <span className="results badge badge-info">{search.results} results</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default SearchDashboard;