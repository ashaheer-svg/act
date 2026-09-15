<?php
/**
 * Migration: Create master_brands, master_categories, and update invoice_items
 */
$db = new PDO('sqlite:data/sales_bi.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Running migrations...\n";

// 1. master_brands
$db->exec("
    CREATE TABLE IF NOT EXISTS master_brands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        code TEXT,
        color TEXT DEFAULT '#2563eb',
        description TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");
echo "- master_brands table created/verified\n";

// 2. master_categories
$db->exec("
    CREATE TABLE IF NOT EXISTS master_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        code TEXT,
        color TEXT DEFAULT '#059669',
        description TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");
echo "- master_categories table created/verified\n";

// 3. Check and add columns to invoice_items
$itemCols = [];
foreach ($db->query("PRAGMA table_info(invoice_items)") as $col) {
    $itemCols[] = $col['name'];
}

if (!in_array('brand', $itemCols)) {
    $db->exec("ALTER TABLE invoice_items ADD COLUMN brand TEXT;");
    echo "- Added column 'brand' to invoice_items\n";
} else {
    echo "- Column 'brand' already exists in invoice_items\n";
}

if (!in_array('category', $itemCols)) {
    $db->exec("ALTER TABLE invoice_items ADD COLUMN category TEXT;");
    echo "- Added column 'category' to invoice_items\n";
} else {
    echo "- Column 'category' already exists in invoice_items\n";
}

// Add indices for performance
$db->exec("CREATE INDEX IF NOT EXISTS idx_invoice_items_brand ON invoice_items(brand);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_invoice_items_category ON invoice_items(category);");
echo "- Indices created on invoice_items(brand, category)\n";

// 4. Seed master_brands
$brands = [
    ['Synology', 'SYN', '#2563eb', 'NAS storage, surveillance and networking systems'],
    ['Seagate', 'ST', '#10b981', 'Enterprise and NAS storage drives (IronWolf, Exos)'],
    ['BDCOM', 'BDC', '#0284c7', 'Enterprise managed switches, PoE, and fiber networks'],
    ['DrayTek', 'DRY', '#0d9488', 'Vigor multi-WAN routers and security firewalls'],
    ['Acronis', 'ACR', '#7c3aed', 'Cyber Protect cloud backup and disaster recovery'],
    ['ESET', 'EST', '#059669', 'Endpoint security, antivirus and encryption solutions'],
    ['Toshiba', 'TOS', '#dc2626', 'Enterprise surveillance and capacity hard drives'],
    ['Western Digital', 'WD', '#ea580c', 'WD Red / Gold storage and SSD modules'],
    ['Microsoft', 'MSFT', '#4f46e5', 'Office 365, Windows Server & CAL licenses'],
    ['Active Solutions', 'ASL', '#64748b', 'In-house professional services, cabling & deployment'],
    ['APC', 'APC', '#16a34a', 'Smart-UPS, power backup & server rack infrastructure'],
    ['Ubiquiti', 'UBQ', '#0ea5e9', 'UniFi wireless access points and network controllers'],
    ['MailStore', 'MLS', '#3b82f6', 'Email archiving server licenses and support'],
    ['QNAP', 'QNP', '#8b5cf6', 'Turbo NAS hardware and expansion enclosures'],
    ['Innodisk', 'INN', '#d97706', 'Industrial-grade memory modules and flash storage'],
    ['Other', 'OTH', '#94a3b8', 'Unclassified / third-party accessories and materials']
];

$insertBrand = $db->prepare("INSERT OR IGNORE INTO master_brands (name, code, color, description) VALUES (?, ?, ?, ?)");
foreach ($brands as $b) {
    $insertBrand->execute($b);
}
echo "- Seeded " . count($brands) . " master brands\n";

// 5. Seed master_categories
$categories = [
    ['NAS & Storage Servers', 'NAS', '#2563eb', 'Network Attached Storage, expansion units & flash storage'],
    ['Enterprise Hard Drives', 'HDD', '#10b981', 'Enterprise SATA/SAS HDDs & enterprise NVMe/SATA SSDs'],
    ['Network Switches & Routers', 'NET', '#0284c7', 'Managed PoE+ switches, fiber aggregation & routers'],
    ['Firewalls & Security Appliances', 'SEC', '#0d9488', 'Multi-WAN security gateways, VPN routers & firewalls'],
    ['Cloud Backup & Cyber Protect', 'BCK', '#7c3aed', 'Acronis Cloud backup, endpoint protection & DRaaS'],
    ['Antivirus & Endpoint Security', 'AV', '#059669', 'ESET antivirus, endpoint encryption & cloud security'],
    ['SLA & Maintenance Contracts', 'SLA', '#ea580c', 'Annual maintenance contracts (AMC), hardware support SLAs'],
    ['Hardware Rental Fleet', 'RNT', '#9333ea', 'Monthly rental NAS systems, rented drives & leased firewalls'],
    ['Professional Services & Deployments', 'SVC', '#475569', 'Installation, configuration, network audit & consulting'],
    ['Accessories & Peripherals', 'ACC', '#64748b', 'RAM modules, PCIe cards, rails, cables & transceivers'],
    ['Software Licenses & SaaS', 'LIC', '#0891b2', 'Operating system licenses, Office 365, MailStore CALs'],
    ['Commercial Adjustments & Levies', 'ADJ', '#94a3b8', 'Discounts, roundings, and statutory tax levies'],
    ['Other / Unassigned', 'OTH', '#94a3b8', 'Uncategorized products requiring classification']
];

$insertCat = $db->prepare("INSERT OR IGNORE INTO master_categories (name, code, color, description) VALUES (?, ?, ?, ?)");
foreach ($categories as $c) {
    $insertCat->execute($c);
}
echo "- Seeded " . count($categories) . " master categories\n";

// 6. Backfill existing invoice_items
echo "- Backfilling brand and category in invoice_items...\n";

// Backfill brand from brand_category
$knownBrands = ['Synology', 'BDCOM', 'Seagate', 'DrayTek', 'Acronis', 'ESET', 'Toshiba', 'Western Digital', 'Microsoft', 'Active Solutions', 'Innodisk', 'QNAP'];
foreach ($knownBrands as $kb) {
    $db->prepare("UPDATE invoice_items SET brand = ? WHERE (brand IS NULL OR brand = '' OR brand = 'Other') AND brand_category = ?")->execute([$kb, $kb]);
}

// Backfill based on product name text matching for items still unassigned or 'Other'
$brandRules = [
    'Synology' => '%synology%',
    'BDCOM' => '%bdcom%',
    'Seagate' => '%seagate%',
    'DrayTek' => '%draytek%',
    'Acronis' => '%acronis%',
    'ESET' => '%eset%',
    'Toshiba' => '%toshiba%',
    'Western Digital' => '%western digital%',
    'Microsoft' => '%microsoft%',
    'APC' => '%apc%ups%',
    'Ubiquiti' => '%unifi%'
];

foreach ($brandRules as $br => $pat) {
    $db->prepare("UPDATE invoice_items SET brand = ? WHERE (brand IS NULL OR brand = '' OR brand = 'Other') AND (clean_product_name LIKE ?)")->execute([$br, $pat]);
}

// Backfill category based on product_type, brand, and clean_product_name
$db->exec("UPDATE invoice_items SET category = 'SLA & Maintenance Contracts' WHERE (category IS NULL OR category = '') AND (product_type = 'MAINTENANCE' OR clean_product_name LIKE '%maintenance agreement%' OR clean_product_name LIKE '%service agreement%')");
$db->exec("UPDATE invoice_items SET category = 'Hardware Rental Fleet' WHERE (category IS NULL OR category = '') AND (product_type = 'RENTAL' OR clean_product_name LIKE '%rental%' OR clean_product_name LIKE '%rent%')");
$db->exec("UPDATE invoice_items SET category = 'Professional Services & Deployments' WHERE (category IS NULL OR category = '') AND (product_type = 'SERVICE' OR clean_product_name LIKE '%installation%' OR clean_product_name LIKE '%configuration%' OR clean_product_name LIKE '%labor%' OR clean_product_name LIKE '%service charges%')");
$db->exec("UPDATE invoice_items SET category = 'Cloud Backup & Cyber Protect' WHERE (category IS NULL OR category = '') AND (brand = 'Acronis' OR clean_product_name LIKE '%acronis%')");
$db->exec("UPDATE invoice_items SET category = 'Antivirus & Endpoint Security' WHERE (category IS NULL OR category = '') AND (brand = 'ESET' OR clean_product_name LIKE '%eset%')");
$db->exec("UPDATE invoice_items SET category = 'Commercial Adjustments & Levies' WHERE (category IS NULL OR category = '') AND (product_type IN ('DISCOUNT', 'TAX_LEVY') OR clean_product_name LIKE '%discount%' OR clean_product_name LIKE '%vat%levy%')");

// Storage vs Drives vs Networking
$db->exec("UPDATE invoice_items SET category = 'Enterprise Hard Drives' WHERE (category IS NULL OR category = '') AND (brand IN ('Seagate', 'Toshiba', 'Western Digital') OR clean_product_name LIKE '%ironwolf%' OR clean_product_name LIKE '%exos%' OR clean_product_name LIKE '%hat3300%' OR clean_product_name LIKE '%hat5300%' OR clean_product_name LIKE '%sata hdd%' OR clean_product_name LIKE '%hard drive%')");
$db->exec("UPDATE invoice_items SET category = 'NAS & Storage Servers' WHERE (category IS NULL OR category = '') AND (brand = 'Synology' AND (clean_product_name LIKE '%diskstation%' OR clean_product_name LIKE '%rackstation%' OR clean_product_name LIKE '%flashstation%' OR clean_product_name LIKE 'ds%' OR clean_product_name LIKE 'rs%'))");
$db->exec("UPDATE invoice_items SET category = 'Network Switches & Routers' WHERE (category IS NULL OR category = '') AND (brand IN ('BDCOM', 'DrayTek') OR clean_product_name LIKE '%switch%' OR clean_product_name LIKE '%poe%' OR clean_product_name LIKE '%router%' OR clean_product_name LIKE '%vigor%')");
$db->exec("UPDATE invoice_items SET category = 'Software Licenses & SaaS' WHERE (category IS NULL OR category = '') AND (product_type = 'SOFTWARE' OR brand = 'Microsoft' OR clean_product_name LIKE '%license%' OR clean_product_name LIKE '%subscription%')");
$db->exec("UPDATE invoice_items SET category = 'Accessories & Peripherals' WHERE (category IS NULL OR category = '') AND (product_type = 'ACCESSORY' OR clean_product_name LIKE '%ram%' OR clean_product_name LIKE '%module%' OR clean_product_name LIKE '%cable%' OR clean_product_name LIKE '%rail%')");

// Set default fallback for remaining nulls
$db->exec("UPDATE invoice_items SET brand = 'Other' WHERE brand IS NULL OR brand = ''");
$db->exec("UPDATE invoice_items SET category = 'Other / Unassigned' WHERE category IS NULL OR category = ''");

echo "Migration completed successfully!\n";

// Audit counts
echo "\n=== BRAND COUNTS IN INVOICE_ITEMS ===\n";
$stmt = $db->query("SELECT brand, COUNT(*) as c, SUM(total_amount) as total FROM invoice_items GROUP BY brand ORDER BY c DESC LIMIT 15");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-25s | %5d items | %15s\n", $r['brand'], $r['c'], number_format($r['total'], 2));
}

echo "\n=== CATEGORY COUNTS IN INVOICE_ITEMS ===\n";
$stmt = $db->query("SELECT category, COUNT(*) as c, SUM(total_amount) as total FROM invoice_items GROUP BY category ORDER BY c DESC LIMIT 15");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-35s | %5d items | %15s\n", $r['category'], $r['c'], number_format($r['total'], 2));
}
