import sqlite3
import re

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("Hosting items in 2025-2026:")
rows = cur.execute("""
    SELECT s.invoice_number, s.customer_name, s.invoice_date, s.item_description, s.total_amount
    FROM sales s
    LEFT JOIN software_subscriptions ss ON s.invoice_number = ss.invoice_number
    WHERE (LOWER(s.item_description) LIKE '%hosting%' OR LOWER(s.item_description) LIKE '%domain%')
      AND s.invoice_date >= '2025-01-01'
      AND ss.id IS NULL
    ORDER BY s.invoice_date DESC
""").fetchall()

print(f"Total unextracted hosting/domain in 2025-2026: {len(rows)}")
for r in rows[:15]:
    print(r)

con.close()
