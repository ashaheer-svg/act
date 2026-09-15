import sqlite3

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("Distinct hosting descriptions:")
for r in cur.execute("""
    SELECT item_description, count(*), min(invoice_date), max(invoice_date)
    FROM sales
    WHERE LOWER(item_description) LIKE '%hosting%'
    GROUP BY item_description
    ORDER BY count(*) DESC
    LIMIT 20
"""):
    print(r)

print("\nDistinct domain descriptions:")
for r in cur.execute("""
    SELECT item_description, count(*), min(invoice_date), max(invoice_date)
    FROM sales
    WHERE LOWER(item_description) LIKE '%domain%'
    GROUP BY item_description
    ORDER BY count(*) DESC
    LIMIT 10
"""):
    print(r)

con.close()
