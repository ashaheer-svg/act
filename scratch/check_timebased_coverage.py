import sqlite3

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

# Check how many sales rows match time-based keywords and whether their invoices are in software_subscriptions
keywords = ['hosting', 'domain', 'acronis', 'maintenance', 'backup', 'sla', 'cloud', 'office 365', 'subscription']
for kw in keywords:
    total_sales = cur.execute(f"SELECT COUNT(*) FROM sales WHERE LOWER(item_description) LIKE '%{kw}%'").fetchone()[0]
    distinct_invs = cur.execute(f"SELECT COUNT(DISTINCT invoice_number) FROM sales WHERE LOWER(item_description) LIKE '%{kw}%'").fetchone()[0]
    matched_in_sub = cur.execute(f"""
        SELECT COUNT(DISTINCT s.invoice_number) 
        FROM sales s 
        JOIN software_subscriptions ss ON s.invoice_number = ss.invoice_number 
        WHERE LOWER(s.item_description) LIKE '%{kw}%'
    """).fetchone()[0]
    print(f"Keyword '{kw}': {total_sales} lines, {distinct_invs} invoices, {matched_in_sub} matched in software_subscriptions")

print("\nSample sales items with 'hosting':")
for r in cur.execute("SELECT invoice_date, invoice_number, customer_name, item_description, total_amount FROM sales WHERE LOWER(item_description) LIKE '%hosting%' ORDER BY invoice_date DESC LIMIT 5").fetchall():
    print(r)

print("\nSample sales items with 'acronis':")
for r in cur.execute("SELECT invoice_date, invoice_number, customer_name, item_description, total_amount FROM sales WHERE LOWER(item_description) LIKE '%acronis%' ORDER BY invoice_date DESC LIMIT 5").fetchall():
    print(r)

con.close()
