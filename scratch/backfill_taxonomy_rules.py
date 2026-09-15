import sqlite3

conn = sqlite3.connect('data/sales_bi.db')
cursor = conn.cursor()

# Get unassigned or Other items
cursor.execute("""
    SELECT id, clean_product_name, product_type, brand, category, base_value
    FROM invoice_items
    WHERE brand = 'Other' OR brand IS NULL OR brand = ''
       OR category = 'Other / Unassigned' OR category IS NULL OR category = ''
""")
rows = cursor.fetchall()
print(f"Total items evaluated for refinement: {len(rows)}")

updates = []
for r in rows:
    item_id, name, ptype, brand, cat, val = r
    name_lower = (name or '').lower()
    new_brand = brand
    new_cat = cat

    # 1. Detect Brand if Other or missing
    if new_brand in ('Other', '', None):
        if any(w in name_lower for w in ['synology', 'diskstation', 'rackstation', 'plus hdd', 'hat33', 'hat53', 'sat52', 'rx12', 'ds4', 'ds9', 'ds18', 'rs36', 'rc18']):
            new_brand = 'Synology'
        elif any(w in name_lower for w in ['seagate', 'ironwolf', 'barracuda', 'skyhawk', 'exos']):
            new_brand = 'Seagate'
        elif any(w in name_lower for w in ['western digital', 'wd red', 'wd purple', 'ultrastar']):
            new_brand = 'Western Digital'
        elif any(w in name_lower for w in ['toshiba']):
            new_brand = 'Toshiba'
        elif any(w in name_lower for w in ['bdcom']):
            new_brand = 'BDCOM'
        elif any(w in name_lower for w in ['draytek', 'vigor']):
            new_brand = 'DrayTek'
        elif any(w in name_lower for w in ['acronis']):
            new_brand = 'Acronis'
        elif any(w in name_lower for w in ['eset']):
            new_brand = 'ESET'
        elif any(w in name_lower for w in ['maintenance', 'hospital network', 'annual maintenance', 'amc', 'service charge', 'configuration']):
            new_brand = 'Active Solutions'

    # 2. Detect Category
    if any(w in name_lower for w in ['hdd', 'hard drive', 'enterprise sata', 'plus hdd', 'sata hdd', 'nas hard drive']):
        new_cat = 'Enterprise Hard Drives'
    elif any(w in name_lower for w in ['nas', 'bay', 'diskstation', 'rackstation', 'expansion unit', 'expansion', 'ds4', 'ds9', 'ds18', 'ds2', 'rs12', 'rs36', 'rc18']):
        new_cat = 'NAS & Storage Servers'
    elif any(w in name_lower for w in ['maintenance', 'amc', 'sla', 'annual maintenance']):
        new_cat = 'SLA & Maintenance Contracts'
    elif any(w in name_lower for w in ['switch', 'router', 'access point', 'poe']):
        new_cat = 'Network Switches & Routers'
    elif any(w in name_lower for w in ['service', 'installation', 'configuration', 'troubleshooting', 'cpanel', 'hosting']):
        new_cat = 'Professional Services & Deployments'
    elif any(w in name_lower for w in ['license', 'licence', 'subscription']):
        new_cat = 'Software Licenses & SaaS'
    elif any(w in name_lower for w in ['ram', 'cable', 'cord', 'adapter', 'rail kit', 'bracket', 'transceiver']):
        new_cat = 'Accessories & Peripherals'

    if new_brand != brand or new_cat != cat:
        updates.append((new_brand, new_cat, item_id))

print(f"Items to update: {len(updates)}")

# Apply updates in batch
cursor.executemany("UPDATE invoice_items SET brand = ?, category = ? WHERE id = ?", updates)
conn.commit()

# Check new statistics
cursor.execute("SELECT COUNT(*) FROM invoice_items WHERE brand = 'Other' OR brand IS NULL OR brand = ''")
remaining_other_brand = cursor.fetchone()[0]

cursor.execute("SELECT COUNT(*) FROM invoice_items WHERE category = 'Other / Unassigned' OR category IS NULL OR category = ''")
remaining_other_cat = cursor.fetchone()[0]

print(f"Remaining 'Other' Brand: {remaining_other_brand}")
print(f"Remaining 'Other / Unassigned' Category: {remaining_other_cat}")

cursor.execute("SELECT brand, COUNT(*), SUM(base_value) FROM invoice_items GROUP BY brand ORDER BY COUNT(*) DESC LIMIT 8")
print("\nNew Brand Distribution:")
for r in cursor.fetchall():
    v = f"{r[2]:,.0f}" if r[2] else "0"
    print(f"  {r[0]}: {r[1]} items (LKR {v})")

cursor.execute("SELECT category, COUNT(*), SUM(base_value) FROM invoice_items GROUP BY category ORDER BY COUNT(*) DESC LIMIT 8")
print("\nNew Category Distribution:")
for r in cursor.fetchall():
    v = f"{r[2]:,.0f}" if r[2] else "0"
    print(f"  {r[0]}: {r[1]} items (LKR {v})")

conn.close()
