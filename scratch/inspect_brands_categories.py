import sqlite3

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("--- TABLES ---")
for t in cur.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall():
    print(t[0])

print("\n--- PRODUCT_MAPPINGS SCHEMA ---")
res = cur.execute("SELECT sql FROM sqlite_master WHERE name='product_mappings'").fetchone()
if res:
    print(res[0])
    print("Sample product_mappings:")
    for r in cur.execute("SELECT * FROM product_mappings LIMIT 5").fetchall():
        print(r)

print("\n--- INVOICE_ITEMS SCHEMA ---")
res = cur.execute("SELECT sql FROM sqlite_master WHERE name='invoice_items'").fetchone()
if res:
    print(res[0])
    print("Distinct brand_category in invoice_items:")
    for r in cur.execute("SELECT brand_category, COUNT(*) FROM invoice_items GROUP BY brand_category ORDER BY COUNT(*) DESC LIMIT 15").fetchall():
        print(r)
    print("Distinct product_type in invoice_items:")
    for r in cur.execute("SELECT product_type, COUNT(*) FROM invoice_items GROUP BY product_type ORDER BY COUNT(*) DESC LIMIT 15").fetchall():
        print(r)

print("\n--- SALES PRODUCT_CATEGORY ---")
for r in cur.execute("SELECT product_category, COUNT(*) FROM sales GROUP BY product_category ORDER BY COUNT(*) DESC LIMIT 15").fetchall():
    print(r)

con.close()
