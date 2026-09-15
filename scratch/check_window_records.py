import sqlite3

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("--- Records in software_subscriptions with end_date between 2026-06-13 and 2026-12-13 (±90 days of 2026-09-13) ---")
rows = cur.execute("""
    SELECT invoice_number, customer_name, end_customer, software_name, 
           period_start_date, period_end_date, renewal_opportunity_value,
           CAST(ROUND(julianday(period_end_date) - julianday('2026-09-13')) AS INTEGER) as days_remaining
    FROM software_subscriptions
    WHERE period_end_date BETWEEN '2026-06-13' AND '2026-12-13'
    ORDER BY period_end_date ASC
""").fetchall()

print(f"Total count: {len(rows)}")
for r in rows:
    print(f"[{r[5]}] ({r[7]:+3d}d) Inv: {r[0]} | Cust: {r[1][:25]} | Item: {r[3][:35]} | Val: {r[6]}")

print("\n--- Also check in all of 2026 ---")
cnt_2026 = cur.execute("SELECT count(*) FROM software_subscriptions WHERE period_end_date LIKE '2026%'").fetchone()[0]
print(f"2026 count: {cnt_2026}")

print("\n--- Also check 2027 ---")
cnt_2027 = cur.execute("SELECT count(*) FROM software_subscriptions WHERE period_end_date LIKE '2027%'").fetchone()[0]
print(f"2027 count: {cnt_2027}")

con.close()
