import numpy as np
import pandas as pd
from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
from datetime import datetime
import mysql.connector
import logging
from dotenv import load_dotenv
import os
import json

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ProductRecommender:
    def __init__(self):
        self.db_config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASSWORD', ''),
            'database': os.getenv('DB_NAME', 'warehouse_system')
        }

    def connect_to_db(self):
        try:
            connection = mysql.connector.connect(**self.db_config)
            return connection
        except Exception as e:
            logger.error(f"Error connecting to database: {e}")
            raise

    def get_seasonal_data(self):
        query = """
        SELECT 
            t.transaction_date,
            MONTH(t.transaction_date) as month,
            t.product_id,
            p.product_name,
            p.category,
            p.quantity as current_stock,
            SUM(CASE WHEN t.type = 'out' THEN t.quantity ELSE 0 END) as demand,
            COUNT(DISTINCT CASE WHEN t.type = 'out' THEN t.transaction_id END) as transaction_count
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
        GROUP BY 
            MONTH(t.transaction_date),
            t.product_id
        ORDER BY t.transaction_date
        """
        
        try:
            conn = self.connect_to_db()
            df = pd.read_sql(query, conn)
            conn.close()
            return df
        except Exception as e:
            logger.error(f"Error fetching seasonal data: {e}")
            raise

    def calculate_seasonal_scores(self, df):
        # Calculate seasonal metrics
        seasonal_metrics = df.groupby(['product_id', 'month']).agg({
            'demand': ['mean', 'std'],
            'transaction_count': 'mean',
            'product_name': 'first',
            'category': 'first',
            'current_stock': 'first'
        }).reset_index()

        # Flatten column names
        seasonal_metrics.columns = [
            'product_id', 'month', 'avg_demand', 'demand_std', 
            'avg_transactions', 'product_name', 'category', 'current_stock'
        ]

        # Calculate seasonal strength
        seasonal_metrics['seasonal_strength'] = (
            seasonal_metrics['avg_demand'] * 
            seasonal_metrics['avg_transactions']
        )

        return seasonal_metrics

    def get_top_products_for_month(self, month=None):
        """Get top products for a specific month based on historical data"""
        if month is None:
            month = datetime.now().month

        try:
            # Get seasonal data
            df = self.get_seasonal_data()
            
            # Calculate seasonal metrics
            seasonal_metrics = self.calculate_seasonal_scores(df)
            
            # Filter for current month
            current_month_products = seasonal_metrics[seasonal_metrics['month'] == month]
            
            # Sort by seasonal strength
            top_products = current_month_products.sort_values(
                by='seasonal_strength', 
                ascending=False
            ).head(10)

            # Calculate additional metrics
            recommendations = []
            for _, product in top_products.iterrows():
                # Calculate stock recommendation
                avg_monthly_demand = product['avg_demand']
                current_stock = product['current_stock']
                
                # Recommend restocking if current stock is less than 2x average monthly demand
                restock_recommended = current_stock < (avg_monthly_demand * 2)
                
                recommendations.append({
                    'product_id': int(product['product_id']),
                    'product_name': product['product_name'],
                    'category': product['category'],
                    'current_stock': int(product['current_stock']),
                    'avg_monthly_demand': round(float(avg_monthly_demand), 2),
                    'demand_std': round(float(product['demand_std']), 2),
                    'avg_transactions': round(float(product['avg_transactions']), 2),
                    'seasonal_strength': round(float(product['seasonal_strength']), 2),
                    'restock_recommended': bool(restock_recommended),
                    'recommended_stock': int(avg_monthly_demand * 2.5)  # 2.5x monthly demand as safety stock
                })

            return {
                'month': month,
                'recommendations': recommendations,
                'generated_at': datetime.now().isoformat()
            }

        except Exception as e:
            logger.error(f"Error generating recommendations: {e}")
            return {
                'error': str(e),
                'month': month,
                'generated_at': datetime.now().isoformat()
            }

    def save_recommendations(self, recommendations):
        """Save recommendations to database"""
        if 'error' in recommendations:
            return False

        query = """
        INSERT INTO product_recommendations 
        (product_id, month, avg_monthly_demand, demand_std, seasonal_strength,
         recommended_stock, restock_recommended, created_at)
        VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
        ON DUPLICATE KEY UPDATE
        avg_monthly_demand = VALUES(avg_monthly_demand),
        demand_std = VALUES(demand_std),
        seasonal_strength = VALUES(seasonal_strength),
        recommended_stock = VALUES(recommended_stock),
        restock_recommended = VALUES(restock_recommended),
        updated_at = NOW()
        """

        try:
            conn = self.connect_to_db()
            cursor = conn.cursor()

            for rec in recommendations['recommendations']:
                cursor.execute(query, (
                    rec['product_id'],
                    recommendations['month'],
                    rec['avg_monthly_demand'],
                    rec['demand_std'],
                    rec['seasonal_strength'],
                    rec['recommended_stock'],
                    rec['restock_recommended']
                ))

            conn.commit()
            cursor.close()
            conn.close()
            return True

        except Exception as e:
            logger.error(f"Error saving recommendations: {e}")
            return False

if __name__ == "__main__":
    recommender = ProductRecommender()
    result = recommender.get_top_products_for_month()
    recommender.save_recommendations(result)
    print(json.dumps(result, indent=2))