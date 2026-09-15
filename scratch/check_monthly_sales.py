import sqlite3

con = sqlite3.connect("data/sales_bi.db")
cur = con.cursor()

cur.execute("SELECT MIN(invoice_date), MAX(invoice_date), COUNT(*), COUNT(DISTINCT invoice_number) FROM sales")
print("Sales range:", cur.fetchone())

cur.execute("""
    SELECT strftime('%Y-%m', invoice_date) as ym, 
           COUNT(DISTINCT invoice_number) as inv_count,
           SUM(base_value) as base,
           SUM(vat_component) as vat,
           SUM(total_amount) as gross
    FROM sales 
    WHERE invoice_date >= '2025-01-01' 
    GROUP BY ym 
    ORDER BY ym
""")
print("\nRecent monthly sales summary:")
for r in cur.fetchall():
    print(f"{r[0]} | Invoices: {r[1]:4d} | Base: {r[2]:12,.2f} | VAT: {r[3]:10,.2f} | Gross: {r[4]:12,.2f}")
