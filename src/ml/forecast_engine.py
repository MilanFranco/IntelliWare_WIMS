#!/usr/bin/env python

"""
Warehouse Forecasting & Recommendation Engine
Called by: run_forecast.php
Connects to the database, analyzes transaction history, and populates
the `product_recommendations` table with monthly demand stats.
"""

import sys
import pandas as pd
import mysql.connector
import json
from datetime import datetime

def get_db_connection(host, user, password, database):
    """Establishes database connection."""
    try:
        conn = mysql.connector.connect(
            host=host,
            user=user,
            password=password,
            database=database,
            # Adding a timeout is good practice
            connection_timeout=10 
        )
        return conn
    except mysql.connector.Error as err:
        # If connection fails, print error as JSON and exit
        print(json.dumps({"success": False, "error": f"DB Connection Error: {err}"}))
        sys.exit(1) # Exit with a non-zero code to signal failure to PHP

def analyze_demand(conn):
    """
    Analyzes product demand and populates the product_recommendations table.
    """
    try:
        # 1. Load all 'stock_out' transactions into a pandas DataFrame
        sql_query = """
        SELECT product_id, quantity, transaction_date
        FROM transactions
        WHERE transaction_type = 'stock_out'
        """
        # Using index_col and parse_dates is efficient
        transactions_df = pd.read_sql(sql_query, conn, 
                                      index_col='transaction_date', 
                                      parse_dates=True)

        if transactions_df.empty:
            return 0 # No data to process

        # 2. Get a list of all products
        products_df = pd.read_sql("SELECT product_id, quantity, reorder_point FROM products", conn)
        
        all_recommendations = []
        
        # 3. Analyze each product
        for product in products_df.itertuples():
            product_id = product.product_id
            current_stock = product.quantity
            reorder_point = product.reorder_point

            # Filter transactions for this product
            product_tx = transactions_df[transactions_df['product_id'] == product_id]

            if product_tx.empty:
                continue # No sales data for this product

            # 4. Resample sales data to monthly totals
            # 'M' groups by calendar month end
            monthly_sales = product_tx['quantity'].resample('M').sum()
            
            # Remove months with zero sales to get a clean average
            monthly_sales = monthly_sales[monthly_sales > 0]
            
            if monthly_sales.empty:
                continue

            # 5. Calculate key demand statistics
            avg_monthly_demand = monthly_sales.mean()
            demand_std = monthly_sales.std()
            # Handle cases with only one month of data (std would be NaN)
            if pd.isna(demand_std):
                demand_std = 0

            # 6. Generate 12 months of recommendations
            current_month = datetime.now().month
            
            for i in range(12):
                month = (current_month + i - 1) % 12 + 1 # 1=Jan, 2=Feb...
                
                # --- Simple Recommendation Logic ---
                # A more advanced model (e.g., ARIMA) would go here.
                # For now, we use a "Safety Stock" formula.
                # Recommended Stock = Avg. Demand + (2 * Standard Deviation)
                # This covers ~95% of demand variance.
                recommended_stock = int(round(avg_monthly_demand + (2 * demand_std), 0))
                
                # Check if restock is needed
                # We'll set a simple flag for now
                restock_needed = 1 if current_stock < reorder_point else 0

                all_recommendations.append((
                    product_id,
                    month,
                    avg_monthly_demand,
                    demand_std,
                    0, # seasonal_strength (placeholder)
                    recommended_stock,
                    restock_needed
                ))

        # 7. Batch insert/update recommendations into the database
        if all_recommendations:
            cursor = conn.cursor()
            
            # Clear old recommendations for the products we analyzed
            processed_ids = tuple(products_df['product_id'].unique())
            # Use a tuple for the IN clause, handling single-item case
            if len(processed_ids) == 1:
                cursor.execute(f"DELETE FROM product_recommendations WHERE product_id = {processed_ids[0]}")
            else:
                cursor.execute(f"DELETE FROM product_recommendations WHERE product_id IN {processed_ids}")

            # Insert new recommendations
            insert_sql = """
            INSERT INTO product_recommendations 
            (product_id, `month`, avg_monthly_demand, demand_std, seasonal_strength, recommended_stock, restock_recommended)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            cursor.executemany(insert_sql, all_recommendations)
            conn.commit() # Commit the transaction
            cursor.close()
            
        return len(products_df)

    except Exception as e:
        # Print error as JSON and exit
        print(json.dumps({"success": False, "error": f"Analysis Error: {e}"}))
        sys.exit(1)


def main():
    # 1. Get database credentials from PHP
    if len(sys.argv) != 5:
        print(json.dumps({"success": False, "error": "Invalid arguments. Expected 4, got " + str(len(sys.argv)-1)}))
        sys.exit(1)
    
    host, user, password, database = sys.argv[1:]

    # 2. Connect to the database
    conn = get_db_connection(host, user, password, database)
    
    # 3. Run the analysis
    products_processed = analyze_demand(conn)
    
    # 4. Close connection
    conn.close()

    # 5. Print a JSON success message for PHP to read
    # This is the "output" that PHP's exec() will capture
    print(json.dumps({
        "success": True,
        "products_processed": products_processed,
        "anomalies_detected": 0 # Placeholder
    }))
    sys.exit(0) # Success!

if __name__ == "__main__":
    main()