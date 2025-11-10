import numpy as np
import pandas as pd
from prophet import Prophet
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import StandardScaler
import mysql.connector
from mysql.connector import Error
import joblib
import os
from datetime import datetime, timedelta
import logging
import json
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ForecastEngine:
    def __init__(self):
        self.db_config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASSWORD', ''),
            'database': os.getenv('DB_NAME', 'warehouse_system')
        }
        self.models = {}
        self.anomaly_detectors = {}
        
    def connect_to_db(self):
        try:
            connection = mysql.connector.connect(**self.db_config)
            return connection
        except Error as e:
            logger.error(f"Error connecting to database: {e}")
            raise
            
    def get_historical_data(self):
        query = """
        SELECT 
            t.transaction_date,
            t.product_id,
            p.product_name,
            p.category,
            t.quantity,
            t.type
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ORDER BY t.transaction_date
        """
        
        try:
            conn = self.connect_to_db()
            df = pd.read_sql(query, conn)
            conn.close()
            return df
        except Error as e:
            logger.error(f"Error fetching historical data: {e}")
            raise

    def preprocess_data(self, df):
        # Convert dates
        df['transaction_date'] = pd.to_datetime(df['transaction_date'])
        
        # Calculate net quantity (inflow - outflow)
        df['net_quantity'] = df.apply(
            lambda x: x['quantity'] if x['type'] == 'in' else -x['quantity'], 
            axis=1
        )
        
        # Group by date and product
        daily_data = df.groupby(
            ['transaction_date', 'product_id', 'product_name', 'category']
        )['net_quantity'].sum().reset_index()
        
        return daily_data

    def train_prophet_model(self, product_data):
        """Train Facebook Prophet model for a specific product"""
        # Prepare data for Prophet
        df = product_data[['transaction_date', 'net_quantity']].copy()
        df.columns = ['ds', 'y']
        
        # Initialize and train Prophet model
        model = Prophet(
            yearly_seasonality=True,
            weekly_seasonality=True,
            daily_seasonality=False,
            changepoint_prior_scale=0.05
        )
        model.fit(df)
        
        return model

    def train_anomaly_detector(self, product_data):
        """Train isolation forest for anomaly detection"""
        # Prepare features for anomaly detection
        features = product_data[['net_quantity']].copy()
        
        # Scale the features
        scaler = StandardScaler()
        scaled_features = scaler.fit_transform(features)
        
        # Train isolation forest
        iso_forest = IsolationForest(
            contamination=0.1,
            random_state=42
        )
        iso_forest.fit(scaled_features)
        
        return {'model': iso_forest, 'scaler': scaler}

    def generate_forecasts(self):
        """Generate forecasts for all products"""
        try:
            # Get and preprocess historical data
            df = self.get_historical_data()
            daily_data = self.preprocess_data(df)
            
            # Track forecast results
            forecasts = []
            anomalies = []
            
            # Process each product
            for product_id in daily_data['product_id'].unique():
                product_data = daily_data[daily_data['product_id'] == product_id].copy()
                
                if len(product_data) < 30:  # Skip products with insufficient data
                    continue
                
                # Train Prophet model
                prophet_model = self.train_prophet_model(product_data)
                
                # Generate forecast for next 30 days
                future_dates = prophet_model.make_future_dataframe(periods=30)
                forecast = prophet_model.predict(future_dates)
                
                # Train anomaly detector
                anomaly_detector = self.train_anomaly_detector(product_data)
                
                # Save models
                self.models[product_id] = prophet_model
                self.anomaly_detectors[product_id] = anomaly_detector
                
                # Get product details
                product_info = product_data.iloc[0]
                
                # Store forecast results
                for _, row in forecast.tail(30).iterrows():
                    forecasts.append({
                        'product_id': product_id,
                        'product_name': product_info['product_name'],
                        'category': product_info['category'],
                        'forecast_date': row['ds'].strftime('%Y-%m-%d'),
                        'predicted_quantity': round(row['yhat'], 2),
                        'lower_bound': round(row['yhat_lower'], 2),
                        'upper_bound': round(row['yhat_upper'], 2)
                    })
                
                # Check for anomalies in recent data
                recent_data = product_data.tail(30)
                scaled_data = anomaly_detector['scaler'].transform(
                    recent_data[['net_quantity']].values.reshape(-1, 1)
                )
                anomaly_scores = anomaly_detector['model'].score_samples(scaled_data)
                
                # Flag anomalies
                anomaly_threshold = np.percentile(anomaly_scores, 10)
                anomaly_dates = recent_data[anomaly_scores < anomaly_threshold]
                
                for _, anomaly in anomaly_dates.iterrows():
                    anomalies.append({
                        'product_id': product_id,
                        'product_name': product_info['product_name'],
                        'category': product_info['category'],
                        'date': anomaly['transaction_date'].strftime('%Y-%m-%d'),
                        'quantity': anomaly['net_quantity'],
                        'score': float(anomaly_scores[anomaly_dates.index.get_loc(anomaly.name)])
                    })
            
            # Save forecasts to database
            self.save_forecasts(forecasts)
            self.save_anomalies(anomalies)
            
            # Save models for later use
            self.save_models()
            
            return {
                'status': 'success',
                'forecasts_generated': len(forecasts),
                'anomalies_detected': len(anomalies),
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            logger.error(f"Error in forecast generation: {e}")
            return {
                'status': 'error',
                'message': str(e),
                'timestamp': datetime.now().isoformat()
            }

    def save_forecasts(self, forecasts):
        """Save forecasts to database"""
        if not forecasts:
            return
            
        query = """
        INSERT INTO forecast 
        (product_id, forecast_date, predicted_quantity, lower_bound, upper_bound)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
        predicted_quantity = VALUES(predicted_quantity),
        lower_bound = VALUES(lower_bound),
        upper_bound = VALUES(upper_bound)
        """
        
        try:
            conn = self.connect_to_db()
            cursor = conn.cursor()
            
            for forecast in forecasts:
                cursor.execute(query, (
                    forecast['product_id'],
                    forecast['forecast_date'],
                    forecast['predicted_quantity'],
                    forecast['lower_bound'],
                    forecast['upper_bound']
                ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
        except Error as e:
            logger.error(f"Error saving forecasts: {e}")
            raise

    def save_anomalies(self, anomalies):
        """Save detected anomalies to database"""
        if not anomalies:
            return
            
        query = """
        INSERT INTO anomalies 
        (product_id, detection_date, quantity, anomaly_score)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
        quantity = VALUES(quantity),
        anomaly_score = VALUES(anomaly_score)
        """
        
        try:
            conn = self.connect_to_db()
            cursor = conn.cursor()
            
            for anomaly in anomalies:
                cursor.execute(query, (
                    anomaly['product_id'],
                    anomaly['date'],
                    anomaly['quantity'],
                    anomaly['score']
                ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
        except Error as e:
            logger.error(f"Error saving anomalies: {e}")
            raise

    def save_models(self):
        """Save trained models to disk"""
        try:
            # Save Prophet models
            for product_id, model in self.models.items():
                model.save(f'models/prophet_{product_id}.json')
            
            # Save anomaly detectors
            for product_id, detector in self.anomaly_detectors.items():
                joblib.dump(detector, f'models/anomaly_{product_id}.joblib')
                
        except Exception as e:
            logger.error(f"Error saving models: {e}")
            raise

    def load_models(self):
        """Load trained models from disk"""
        try:
            # Load Prophet models
            for model_file in os.listdir('models'):
                if model_file.startswith('prophet_'):
                    product_id = int(model_file.split('_')[1].split('.')[0])
                    self.models[product_id] = Prophet.load(f'models/{model_file}')
            
            # Load anomaly detectors
            for model_file in os.listdir('models'):
                if model_file.startswith('anomaly_'):
                    product_id = int(model_file.split('_')[1].split('.')[0])
                    self.anomaly_detectors[product_id] = joblib.load(f'models/{model_file}')
                    
        except Exception as e:
            logger.error(f"Error loading models: {e}")
            raise

if __name__ == "__main__":
    engine = ForecastEngine()
    result = engine.generate_forecasts()
    print(json.dumps(result, indent=2))