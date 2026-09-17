"""
Load historical inventory transactions from MySQL for ML forecasting.
"""

import pandas as pd
import mysql.connector
from typing import Optional

from config_loader import load_db_config


def get_connection(config: Optional[dict] = None):
    """Establish a MySQL connection using shared config."""
    cfg = config or load_db_config()
    return mysql.connector.connect(
        host=cfg["host"],
        user=cfg["user"],
        password=cfg["password"],
        database=cfg["database"],
        connection_timeout=30,
    )


def load_transactions(conn) -> pd.DataFrame:
    """
    Load stock_out (sales) and stock_in transactions with product metadata.
    Returns one row per transaction.
    """
    query = """
        SELECT
            t.transaction_id,
            t.product_id,
            p.product_name,
            COALESCE(c.name, p.category, 'Uncategorized') AS category,
            t.transaction_type,
            t.quantity,
            t.transaction_date,
            p.quantity AS current_stock
        FROM transactions t
        INNER JOIN products p ON t.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY t.transaction_date ASC
    """
    df = pd.read_sql(query, conn, parse_dates=["transaction_date"])
    return df


def load_sales_only(conn) -> pd.DataFrame:
    """Load only stock_out (demand) transactions."""
    df = load_transactions(conn)
    if df.empty:
        return df
    return df[df["transaction_type"] == "stock_out"].copy()


def load_product_catalog(conn) -> pd.DataFrame:
    """Load product list with current stock levels."""
    query = """
        SELECT
            p.product_id,
            p.product_name,
            COALESCE(c.name, p.category, 'Uncategorized') AS category,
            p.quantity AS current_stock,
            p.reorder_point
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
    """
    return pd.read_sql(query, conn)
