import sqlite3

conn = sqlite3.connect('data/sales_bi.db')
cursor = conn.cursor()
cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
tables = [t[0] for t in cursor.fetchall()]
print("Tables:", tables)

check_tables = ['invoices', 'invoice_items', 'sales', 'profit_margins', 'product_mappings', 'master_brands', 'master_categories', 'settings']
for t in check_tables:
    if t in tables:
        cursor.execute(f"PRAGMA table_info({t})")
        cols = [f"{c[1]} ({c[2]})" for c in cursor.fetchall()]
        print(f"\n[{t}] columns ({len(cols)}):\n  " + "\n  ".join(cols))
