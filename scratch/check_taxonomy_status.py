import sqlite3

conn = sqlite3.connect('data/sales_bi.db')
cursor = conn.cursor()

cursor.execute("""
    SELECT clean_product_name, product_type, COUNT(*) as c, SUM(base_value) as val
    FROM invoice_items
    WHERE (brand IS NULL OR brand = '' OR brand = 'Other')
       OR (category IS NULL OR category = '' OR category = 'Other / Unassigned')
    GROUP BY clean_product_name
    ORDER BY val DESC
    LIMIT 25
""")
print("\nTop Unassigned/Other Product Groups by Value:")
for r in cursor.fetchall():
    val = f"{r[3]:,.0f}" if r[3] else "0"
    print(f"  - [{r[2]}x] {r[0]} | Type: {r[1]} | LKR {val}")
