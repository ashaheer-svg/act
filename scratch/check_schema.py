import sqlite3

con = sqlite3.connect("data/sales_bi.db")
cur = con.cursor()

tables = cur.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()
print("Tables:", [t[0] for t in tables])

for t in ["sales", "invoice_items", "tax_rules", "customers"]:
    if (t,) in tables:
        cols = [col[1] for col in cur.execute(f"PRAGMA table_info({t})").fetchall()]
        print(f"\n{t} columns:", cols)
