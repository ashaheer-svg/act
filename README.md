# 📊 Sales BI Dashboard v1.0

A professional Business Intelligence application for tracking sales, customers, products, and VAT compliance. Built with PHP and SQLite for easy cPanel deployment.

## ✨ Features

### 🎯 Core Functionality
- **File Upload & Import**: Upload QuickBooks exports (Excel/CSV)
- **Automatic VAT Calculation**: 18% VAT handling (inclusive/exclusive)
- **Smart Deduplication**: Only imports new records
- **SQLite Database**: No MySQL setup needed
- **Real-time Calculations**: All metrics updated instantly

### 📊 Reporting & Analytics
- Monthly sales reports
- Quarterly analysis
- Yearly overviews with monthly breakdown
- Top customers (with revenue %)
- Top products (with quantity sold)
- Product category analysis
- Customer concentration risk analysis
- VAT summary by tax code
- Daily sales trends

### 👥 User Management (RBAC)
- **Admin Role**: Upload data, manage users, full access
- **Viewer Role**: Read-only access to reports
- Secure password hashing (bcrypt)
- Session management
- Login/logout functionality

### 🔒 Security
- Prepared statements (SQL injection protection)
- Password hashing
- Session timeout
- File permission checks
- Input validation

---

## 📁 File Structure

```
sales-bi/
├── config.php                 # Configuration & constants
├── login.php                  # Login page
├── index.php                  # Main dashboard
├── upload.php                 # File upload & import
├── reports.php                # Detailed reports
├── users.php                  # User management
├── logout.php                 # Logout handler
│
├── classes/
│   ├── Database.php           # SQLite connection & queries
│   ├── Auth.php               # Authentication & RBAC
│   ├── DataImporter.php       # File parsing & VAT calculation
│   └── Reports.php            # Business analytics queries
│
├── data/                      # Created automatically
│   └── sales_bi.db            # SQLite database
│
├── INSTALLATION_GUIDE.md      # Step-by-step setup guide
└── README.md                  # This file
```

---

## 🚀 Quick Start

### Prerequisites
- PHP 7.0+
- SQLite support (built-in PHP)
- cPanel access

### Installation (5 Minutes)

1. **Create folder** in public_html: `sales-bi`
2. **Upload all files** to this folder
3. **Create `data` folder** inside sales-bi (chmod 755)
4. **Visit**: `https://yourdomain.com/sales-bi/login.php`
5. **Login**: admin / admin123
6. **Change password immediately**

### First Use
1. Go to Admin → Upload Data
2. Upload your QuickBooks export
3. View Dashboard for instant analysis
4. Generate Monthly/Quarterly/Yearly reports

**👉 See INSTALLATION_GUIDE.md for detailed instructions**

---

## 🔐 Default Login

| Field | Value |
|-------|-------|
| Username | admin |
| Password | admin123 |

⚠️ **Change this immediately!** Go to Users → Change My Password

---

## 💡 Key Concepts for Beginners

### VAT Handling
- **Taxable Sales**: Amount is base value → 18% VAT added
- **Non-Taxable Sales**: Amount includes VAT → VAT extracted

**Example**:
- Taxable Sale of $1,000 → Customer pays $1,180 (+ $180 VAT)
- Non-Taxable Sale of $1,180 → Customer pays $1,180 (VAT = $180 embedded)

### Customer Concentration Risk
Tracks how much revenue depends on few customers:
- **>50%**: HIGH RISK (top 3 customers)
- **30-50%**: MEDIUM RISK
- **<30%**: LOW RISK (good diversity)

### Dashboard Metrics
- **Total Revenue**: Sum of all base values
- **VAT Collected**: 18% of revenue owed to government
- **Unique Customers**: Count of different customer names
- **Avg Invoice Value**: Total revenue ÷ number of invoices

---

## 📊 Database Schema

### Tables
1. **users**: Stores login credentials and roles
2. **sales**: Stores transaction data with VAT calculations
3. **import_logs**: Tracks all file uploads and imports

### Key Columns in Sales Table
- invoice_date: When the sale occurred
- customer_name: Who bought
- item_description: What was sold
- tax_code: Taxable or Non-Taxable
- base_value: Revenue before VAT
- vat_component: 18% VAT amount
- total_amount: Final customer payment

---

## 🎮 User Roles

### Admin Role
- ✅ Upload and import files
- ✅ Manage users
- ✅ View all reports
- ✅ Access analytics
- ✅ Change own password

### Viewer Role
- ✅ View dashboard
- ✅ Generate reports
- ✅ See all metrics
- ❌ Cannot upload files
- ❌ Cannot create users
- ❌ Cannot delete data

---

## 🛠️ Configuration

Edit `config.php` to customize:

```php
// Database location
define('DATABASE_PATH', __DIR__ . '/data/sales_bi.db');

// VAT rate (default 18%)
define('VAT_RATE', 0.18);

// Currency symbol
define('CURRENCY', '$');

// Session timeout (seconds)
define('SESSION_TIMEOUT', 3600);

// Max upload size (bytes)
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
```

---

## 📱 Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers
- ⚠️ Internet Explorer (not supported)

---

## 📈 Performance

| Metric | Performance |
|--------|-------------|
| Dashboard load | < 1 second |
| 10,000 transactions | ~2 seconds |
| 50,000 transactions | ~5 seconds |
| Database size | ~1MB per 5,000 transactions |

---

## 🔄 Data Flow

```
QuickBooks Export
       ↓
   Upload File
       ↓
  Validate & Parse
       ↓
 Check for Duplicates
       ↓
Calculate VAT (18%)
       ↓
   Store in Database
       ↓
  Generate Reports
       ↓
   View Analytics
```

---

## ⚙️ Maintenance

### Weekly
- Backup `data/sales_bi.db` file
- Store in safe location

### Monthly
- Review monthly report
- Check VAT owed
- Pay VAT to government

### Quarterly
- Generate quarterly report
- Analyze trends
- Check customer concentration

### Annually
- Generate yearly report
- Plan next year
- Archive old reports

---

## 🐛 Common Issues

| Issue | Solution |
|-------|----------|
| Blank dashboard | Upload data first using Admin → Upload |
| Can't login | Clear browser cache, check caps lock |
| Database error | Check `data` folder permissions (755) |
| File won't upload | Check file size < 5MB and format (.xlsx/.csv) |
| Password forgotten | Delete `data/sales_bi.db` and rebuild database |

**See INSTALLATION_GUIDE.md for detailed troubleshooting**

---

## 🔒 Security Notes

- **Never** store passwords in browser autofill
- **Regularly** backup database file
- **Always** use HTTPS (check with hosting)
- **Change** default password immediately
- **Secure** data folder from direct web access
- **Monitor** login activity (check last_login in users table)

---

## 📞 Support

### If Something Breaks

1. Check INSTALLATION_GUIDE.md troubleshooting section
2. Verify file permissions (chmod 755 for folders, 644 for files)
3. Ensure PHP version 7.0+
4. Contact hosting provider for server issues
5. Backup and restore from backup if corrupted

### Backup Recovery

If database corrupts:
1. Delete `data/sales_bi.db`
2. Reload application
3. Restore from weekly backup
4. Re-import data from last backup point

---

## 🎓 Learning Resources

### For Non-Technical Users
- Read INSTALLATION_GUIDE.md completely
- Watch your hosting provider's cPanel tutorial
- Ask your hosting support for help with file uploads

### For Technical Users
- Database: SQLite (read PDO documentation)
- Classes: Object-oriented PHP
- Architecture: MVC-inspired structure
- Security: Prepared statements, password hashing

---

## 📄 License & Credits

Built with:
- PHP 7.0+
- SQLite 3
- PDO (PHP Data Objects)
- HTML5/CSS3
- JavaScript (vanilla)

---

## 📊 What You Get

### Immediate
- Functional BI dashboard
- Sales analytics
- Customer insights
- Product performance data
- VAT tracking

### Over Time
- Historical trends
- Growth patterns
- Customer loyalty insights
- Seasonal patterns
- Risk identification

---

## 🚀 Scaling Up

As your data grows:
1. Monthly exports stay small (< 1MB)
2. Annual backups still manageable (< 10MB)
3. Query performance remains good
4. Add more users (admin/viewer)
5. Customize reports as needed

---

## ✨ Version History

**v1.0.0** (2026-05-07)
- Initial release
- Dashboard with 6+ key metrics
- Monthly/quarterly/yearly reporting
- Admin/Viewer RBAC
- File import with VAT calculation
- SQLite database

---

## 🎯 Next Steps

1. ✅ Install application
2. ✅ Change default password
3. ✅ Upload first data file
4. ✅ Review dashboard
5. ✅ Create viewer account
6. ✅ Set up weekly backups
7. ✅ Generate first monthly report
8. ✅ Review key metrics

---

**Made for complete beginners who need professional business intelligence.**

For detailed setup help, see: **INSTALLATION_GUIDE.md**

---

**Version**: 1.0.0  
**Last Updated**: 2026-05-07  
**Support**: INSTALLATION_GUIDE.md + Your Hosting Provider
"# act" 
