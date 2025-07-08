/**
 * Customer Search Tracker - Frontend JS
 * Tracks additional search behavior and click patterns
 */

var SearchTracker = {
    init: function() {
        this.trackSearchFocus();
        this.trackSearchResultClicks();
        this.trackSearchSuggestions();
        this.trackSearchFilters();
    },

    trackSearchFocus: function() {
        $(document).on('focus', '#search_query_top, .search_query', function() {
            var startTime = new Date().getTime();
            
            $(this).one('blur', function() {
                var duration = new Date().getTime() - startTime;
                SearchTracker.sendEvent('search_focus', {
                    duration: duration,
                    field: $(this).attr('id')
                });
            });
        });
    },

    trackSearchResultClicks: function() {
        // Track clicks on search results
        $(document).on('click', '.product-miniature a', function() {
            var searchQuery = SearchTracker.getLastSearchQuery();
            if (searchQuery) {
                var position = $(this).closest('.product-miniature').index() + 1;
                var productId = $(this).data('id-product') || $(this).closest('.product-miniature').data('id-product');
                
                SearchTracker.sendEvent('result_click', {
                    query: searchQuery,
                    position: position,
                    product_id: productId
                });
            }
        });
    },

    trackSearchSuggestions: function() {
        // Track autocomplete/suggestion interactions
        $(document).on('click', '.ac_results li', function() {
            var suggestion = $(this).text();
            var originalQuery = $('#search_query_top').val();
            
            SearchTracker.sendEvent('suggestion_click', {
                original_query: originalQuery,
                suggestion: suggestion
            });
        });
    },

    trackSearchFilters: function() {
        // Track filter usage on search results page
        $(document).on('change', '.facet input[type="checkbox"]', function() {
            var searchQuery = SearchTracker.getLastSearchQuery();
            if (searchQuery) {
                var filterType = $(this).closest('.facet').find('.facet-title').text();
                var filterValue = $(this).next('label').text();
                
                SearchTracker.sendEvent('filter_applied', {
                    query: searchQuery,
                    filter_type: filterType,
                    filter_value: filterValue,
                    checked: $(this).is(':checked')
                });
            }
        });
    },

    getLastSearchQuery: function() {
        // Get search query from URL or session
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('s') || sessionStorage.getItem('last_search_query');
    },

    sendEvent: function(eventType, data) {
        $.ajax({
            url: prestashop.urls.base_url + 'module/customersearchtracker/track',
            type: 'POST',
            data: {
                event_type: eventType,
                event_data: JSON.stringify(data),
                ajax: 1
            },
            dataType: 'json'
        });
    },

    // Advanced tracking for search intent
    analyzeSearchIntent: function(query) {
        var patterns = {
            'price_sensitive': /cheap|affordable|budget|sale|discount|under \d+/i,
            'brand_focused': /nike|adidas|sony|samsung|apple/i,
            'feature_specific': /waterproof|wireless|organic|eco-friendly/i,
            'comparison': /vs|versus|compare|better|best/i,
            'urgent': /fast|quick|urgent|asap|today/i
        };

        var intents = [];
        for (var intent in patterns) {
            if (patterns[intent].test(query)) {
                intents.push(intent);
            }
        }

        return intents;
    },

    // Track search abandonment
    trackSearchAbandonment: function() {
        var searchInput = $('#search_query_top');
        var typingTimer;
        var doneTypingInterval = 3000;

        searchInput.on('keyup', function() {
            clearTimeout(typingTimer);
            var query = $(this).val();
            
            if (query.length > 2) {
                typingTimer = setTimeout(function() {
                    SearchTracker.sendEvent('search_abandonment', {
                        partial_query: query,
                        query_length: query.length
                    });
                }, doneTypingInterval);
            }
        });

        searchInput.on('keydown', function() {
            clearTimeout(typingTimer);
        });
    }
};

// Initialize on document ready
$(document).ready(function() {
    SearchTracker.init();
    SearchTracker.trackSearchAbandonment();
    
    // Store search queries in session
    $('form[action*="search"]').on('submit', function() {
        var query = $(this).find('input[name="s"]').val();
        sessionStorage.setItem('last_search_query', query);
        
        // Analyze intent
        var intents = SearchTracker.analyzeSearchIntent(query);
        if (intents.length > 0) {
            SearchTracker.sendEvent('search_intent', {
                query: query,
                intents: intents
            });
        }
    });
});