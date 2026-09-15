import sqlite3

con = sqlite3.connect("data/sales_bi.db")
cur = con.cursor()

# Check date ranges in invoices and sales
cur.execute("SELECT MIN(invoice_date), MAX(invoice_date), COUNT(*) FROM invoices")
print("Invoices date range:", cur.fetchone())

cur.execute("SELECT strftime('%Y-%m', invoice_date) as ym, COUNT(*), SUM(total_amount), SUM(net_amount) FROM invoices WHERE invoice_date >= '2025-01-01' GROUP BY ym ORDER BY ym")
print("\nRecent monthly summary in invoices:")
for row in cur.fetchall():
    print(row)
