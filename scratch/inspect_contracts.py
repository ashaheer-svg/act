import sqlite3

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

print("Columns in software_subscriptions:")
cols = [c[1] for c in cur.execute("PRAGMA table_info(software_subscriptions)")]
print(cols)

print("\nSample records:")
for r in cur.execute("""
    SELECT id, invoice_number, customer_name, end_customer, software_name, 
           edition_tier, license_seats, period_start_date, period_end_date, 
           term_months, renewal_status, renewal_opportunity_value 
    FROM software_subscriptions 
    ORDER BY period_end_date DESC 
    LIMIT 10
"""):
    print(r)

print("\nDate range of period_end_date:")
min_d, max_d = cur.execute("SELECT MIN(period_end_date), MAX(period_end_date) FROM software_subscriptions WHERE period_end_date IS NOT NULL AND period_end_date != ''").fetchone()
print(f"Min: {min_d}, Max: {max_d}")

print("\nNull or empty period_end_date count:")
empty_cnt = cur.execute("SELECT count(*) FROM software_subscriptions WHERE period_end_date IS NULL OR period_end_date = ''").fetchone()[0]
print(f"Empty/Null: {empty_cnt}")

print("\nRecent and future contracts (2025-2027):")
for r in cur.execute("""
    SELECT invoice_number, customer_name, software_name, period_start_date, period_end_date, renewal_opportunity_value
    FROM software_subscriptions 
    WHERE period_end_date >= '2025-01-01'
    ORDER BY period_end_date ASC
    LIMIT 20
"""):
    print(r)

print("\nCount of contracts grouped by year of period_end_date:")
for r in cur.execute("""
    SELECT SUBSTR(period_end_date, 1, 4) as yr, COUNT(*), SUM(renewal_opportunity_value)
    FROM software_subscriptions
    GROUP BY yr
    ORDER BY yr ASC
"""):
    print(r)

con.close()
