import pandas as pd
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.cluster import KMeans
import mysql.connector
import json
from datetime import datetime, timedelta

class SearchPredictor:
    def __init__(self, db_config):
        self.db_config = db_config
        self.vectorizer = TfidfVectorizer(max_features=1000)
        
    def connect_db(self):
        return mysql.connector.connect(**self.db_config)
    
    def fetch_search_data(self, days=30):
        conn = self.connect_db()
        query = f"""
            SELECT search_query, results_count, date_add
            FROM ps_search_tracker
            WHERE date_add >= DATE_SUB(NOW(), INTERVAL {days} DAY)
        """
        df = pd.read_sql(query, conn)
        conn.close()
        return df
    
    def analyze_search_patterns(self):
        df = self.fetch_search_data()
        
        # Vectorize search queries
        tfidf_matrix = self.vectorizer.fit_transform(df['search_query'])
        
        # Cluster similar searches
        kmeans = KMeans(n_clusters=10, random_state=42)
        df['cluster'] = kmeans.fit_predict(tfidf_matrix)
        
        # Analyze patterns
        patterns = {
            'clusters': self._analyze_clusters(df),
            'temporal_patterns': self._analyze_temporal(df),
            'zero_results_prediction': self._predict_zero_results(df)
        }
        
        return patterns
    
    def _analyze_clusters(self, df):
        cluster_analysis = []
        for cluster_id in df['cluster'].unique():
            cluster_data = df[df['cluster'] == cluster_id]
            
            # Get representative terms
            cluster_queries = cluster_data['search_query'].values
            tfidf_cluster = self.vectorizer.transform(cluster_queries)
            avg_tfidf = tfidf_cluster.mean(axis=0).A1
            top_indices = avg_tfidf.argsort()[-5:][::-1]
            feature_names = self.vectorizer.get_feature_names_out()
            top_terms = [feature_names[i] for i in top_indices]
            
            cluster_analysis.append({
                'cluster_id': int(cluster_id),
                'size': len(cluster_data),
                'avg_results': float(cluster_data['results_count'].mean()),
                'top_terms': top_terms,
                'sample_queries': cluster_data['search_query'].head(5).tolist()
            })
        
        return cluster_analysis
    
    def _analyze_temporal(self, df):
        df['hour'] = pd.to_datetime(df['date_add']).dt.hour
        df['day_of_week'] = pd.to_datetime(df['date_add']).dt.dayofweek
        
        hourly_pattern = df.groupby('hour').size().to_dict()
        daily_pattern = df.groupby('day_of_week').size().to_dict()
        
        return {
            'hourly': hourly_pattern,
            'daily': daily_pattern
        }
    
    def _predict_zero_results(self, df):
        # Simple prediction based on term similarity
        zero_results = df[df['results_count'] == 0]['search_query'].unique()
        
        predictions = []
        for query in zero_results[:20]:  # Top 20 problematic queries
            similar = self.find_similar_successful_searches(query, df)
            predictions.append({
                'query': query,
                'suggestions': similar
            })
        
        return predictions
    
    def find_similar_successful_searches(self, query, df, top_n=3):
        successful_searches = df[df['results_count'] > 0]['search_query'].unique()
        
        if len(successful_searches) == 0:
            return []
        
        # Vectorize the query and successful searches
        all_queries = np.append(successful_searches, query)
        tfidf_matrix = self.vectorizer.fit_transform(all_queries)
        
        # Calculate similarity
        query_vector = tfidf_matrix[-1]
        similarities = cosine_similarity(query_vector, tfidf_matrix[:-1]).flatten()
        
        # Get top similar searches
        top_indices = similarities.argsort()[-top_n:][::-1]
        
        return [
            {
                'query': successful_searches[i],
                'similarity': float(similarities[i])
            }
            for i in top_indices if similarities[i] > 0.3
        ]
    
    def generate_recommendations(self):
        patterns = self.analyze_search_patterns()
        
        recommendations = []
        
        # Cluster-based recommendations
        for cluster in patterns['clusters']:
            if cluster['avg_results'] < 5:
                recommendations.append({
                    'type': 'low_results_cluster',
                    'priority': 'high',
                    'message': f"Cluster with terms {', '.join(cluster['top_terms'])} has low average results ({cluster['avg_results']:.1f})",
                    'action': 'Consider adding more products matching these terms'
                })
        
        # Zero results recommendations
        for prediction in patterns['zero_results_prediction'][:5]:
            if prediction['suggestions']:
                recommendations.append({
                    'type': 'zero_results_suggestion',
                    'priority': 'medium',
                    'message': f"'{prediction['query']}' returns no results",
                    'action': f"Consider redirecting to similar search: '{prediction['suggestions'][0]['query']}'"
                })
        
        return recommendations
    
    def export_insights(self):
        insights = {
            'generated_at': datetime.now().isoformat(),
            'patterns': self.analyze_search_patterns(),
            'recommendations': self.generate_recommendations()
        }
        
        return json.dumps(insights, indent=2)

# Usage example
if __name__ == "__main__":
    db_config = {
        'host': 'localhost',
        'user': 'prestashop',
        'password': 'your_password',
        'database': 'prestashop'
    }
    
    predictor = SearchPredictor(db_config)
    insights = predictor.export_insights()
    print(insights)