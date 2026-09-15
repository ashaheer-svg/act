import sqlite3

con = sqlite3.connect("data/sales_bi.db")
con.row_factory = sqlite3.Row
cur = con.cursor()

# Test 1: Rolling 12 months from max date in database
cur.execute("SELECT MAX(invoice_date) FROM sales")
max_date = cur.fetchone()[0]
print("Max date in sales:", max_date)

# If max_date is 2026-09-09, rolling 12 months is 2025-10 to 2026-09 (or 12 months up to current month)
# Let's get the list of 12 distinct months up to max_date
cur.execute("""
    SELECT DISTINCT strftime('%Y-%m', invoice_date) as ym 
    FROM sales 
    WHERE invoice_date <= ? 
    ORDER BY ym DESC 
    LIMIT 12
""", (max_date,))
months = [r['ym'] for r in cur.fetchall()][::-1]
print("Rolling 12 months:", months)

# Let's write a single query or per-month aggregation for these months
placeholders = ','.join(['?'] * len(months))
sql = f"""
    SELECT 
        strftime('%Y-%m', invoice_date) as ym,
        COUNT(DISTINCT invoice_number) as invoice_count,
        COUNT(DISTINCT customer_name) as customer_count,
        SUM(quantity) as total_units,
        SUM(base_value) as net_base,
        SUM(vat_component) as vat_component,
        SUM(total_amount) as gross_sales,
        SUM(CASE WHEN (is_paid = 1 OR (paid_date IS NOT NULL AND paid_date != '')) THEN total_amount ELSE (applied_amount) END) as collected_amount,
        SUM(CASE WHEN balance_remaining > 0 THEN balance_remaining WHEN (is_paid = 0 AND (paid_date IS NULL OR paid_date = '')) THEN total_amount ELSE 0 END) as outstanding_amount
    FROM sales
    WHERE strftime('%Y-%m', invoice_date) IN ({placeholders})
    GROUP BY ym
    ORDER BY ym ASC
"""
cur.execute(sql, months)
rows = {r['ym']: dict(r) for r in cur.fetchall()}

print("\nAggregated Results:")
prev_gross = None
for ym in months:
    data = rows.get(ym, {})
    gross = data.get('gross_sales', 0) or 0
    base = data.get('net_base', 0) or 0
    vat = data.get('vat_component', 0) or 0
    invs = data.get('invoice_count', 0) or 0
    custs = data.get('customer_count', 0) or 0
    units = data.get('total_units', 0) or 0
    collected = data.get('collected_amount', 0) or 0
    outstanding = gross - collected
    aov = (gross / invs) if invs > 0 else 0
    col_rate = (collected / gross * 100) if gross > 0 else 0
    
    mom_pct = None
    if prev_gross is not None and prev_gross > 0:
        mom_pct = ((gross - prev_gross) / prev_gross) * 100
    prev_gross = gross
    
    mom_str = f"{mom_pct:+6.1f}%" if mom_pct is not None else "  N/A "
    print(f"{ym} | Invs: {invs:3d} | Gross: {gross:12,.0f} | Base: {base:12,.0f} | VAT: {vat:10,.0f} | Units: {units:5.0f} | Coll: {collected:12,.0f} ({col_rate:5.1f}%) | MoM: {mom_str}")
