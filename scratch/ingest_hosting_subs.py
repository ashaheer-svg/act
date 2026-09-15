import sqlite3
from datetime import datetime, timedelta
import calendar

def add_months(sourcedate, months):
    month = sourcedate.month - 1 + months
    year = sourcedate.year + month // 12
    month = month % 12 + 1
    day = min(sourcedate.day, calendar.monthrange(year, month)[1])
    return sourcedate.replace(year=year, month=month, day=day)

con = sqlite3.connect('data/sales_bi.db')
cur = con.cursor()

rows = cur.execute("""
    SELECT s.invoice_number, s.customer_name, s.invoice_date, s.item_description, s.total_amount, s.end_customer
    FROM sales s
    LEFT JOIN software_subscriptions ss ON s.invoice_number = ss.invoice_number
    WHERE (LOWER(s.item_description) LIKE '%hosting%' OR LOWER(s.item_description) LIKE '%domain%')
      AND s.invoice_date >= '2025-01-01'
      AND ss.id IS NULL
    ORDER BY s.invoice_date ASC
""").fetchall()

print(f"Found {len(rows)} hosting lines to register.")

inserted = 0
for r in rows:
    inv_num, cust, inv_date_str, desc, amount, end_cust = r
    inv_date = datetime.strptime(inv_date_str, "%Y-%m-%d")
    
    # Check if monthly or annual
    is_monthly = ('month' in desc.lower() or 'email account' in desc.lower() or amount < 10000)
    term_months = 1 if is_monthly else 12
    
    end_date = add_months(inv_date, term_months)
    end_date_str = end_date.strftime("%Y-%m-%d")
    
    today_str = "2026-09-13" # reference date
    if end_date_str < today_str:
        status = "EXPIRED"
    elif (end_date - datetime.strptime(today_str, "%Y-%m-%d")).days <= 60:
        status = "DUE_SOON"
    else:
        status = "ACTIVE"
        
    tier = "Web & Email Hosting"
    seats = 1
    if "10 email" in desc.lower() or "10 e-mail" in desc.lower():
        seats = 10
    elif "20 email" in desc.lower():
        seats = 20
        
    cur.execute("""
        INSERT INTO software_subscriptions (
            invoice_number, customer_name, software_name, edition_tier,
            license_seats, period_start_date, period_end_date, term_months,
            renewal_status, renewal_opportunity_value, end_customer
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        inv_num, cust, desc, tier,
        seats, inv_date_str, end_date_str, term_months,
        status, amount, end_cust
    ))
    inserted += 1

con.commit()
print(f"Successfully inserted {inserted} hosting subscriptions.")

new_count = cur.execute("SELECT count(*) FROM software_subscriptions").fetchone()[0]
print(f"Total software_subscriptions count now: {new_count}")

con.close()
