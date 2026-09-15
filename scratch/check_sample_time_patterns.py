import sqlite3
import re

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("--- Testing period extraction from description on sample invoices ---")
for kw in ['hosting', 'maintenance', 'acronis']:
    rows = cur.execute(f"""
        SELECT invoice_date, invoice_number, customer_name, item_description, total_amount 
        FROM sales 
        WHERE LOWER(item_description) LIKE '%{kw}%' 
        ORDER BY invoice_date DESC 
        LIMIT 10
    """).fetchall()
    print(f"\n--- KW: {kw} ---")
    for r in rows:
        print(f"[{r[0]}] {r[1]} | {r[2][:30]} | {r[3][:60]} | {r[4]}")

con.close()
