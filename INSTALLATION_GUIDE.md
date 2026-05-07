# Sales BI Dashboard - Installation & Setup Guide

## 🎯 For Complete Beginners

This guide explains everything step-by-step. Don't worry if you don't understand the technical terms - we'll explain them!

---

## 📋 What is This Application?

A **Business Intelligence Dashboard** that:
- ✅ Uploads your sales data from QuickBooks
- ✅ Automatically calculates VAT (18%)
- ✅ Shows monthly/quarterly/yearly reports
- ✅ Identifies your best customers and products
- ✅ Tracks important business metrics
- ✅ Has secure login (Admin & Viewer roles)

---

## 🚀 Installation Instructions

### Step 1: What You Need

- cPanel access to your web hosting account
- PHP 7.0+ (usually already installed)
- SQLite support (usually already installed)
- Approximately 50MB free disk space

### Step 2: Download & Upload Files

1. **Create a folder** in your cPanel:
   - Go to your cPanel → File Manager
   - Navigate to `public_html` folder
   - Create new folder: `sales-bi` or `dashboard`

2. **Upload all files** to this folder:
   - `config.php`
   - `login.php`, `index.php`, `reports.php`, `upload.php`, `users.php`, `logout.php`
   - Folder `classes/` with all class files:
     - `Database.php`
     - `Auth.php`
     - `DataImporter.php`
     - `Reports.php`

3. **Create data folder**:
   - In the `sales-bi` folder, create a subfolder named `data`
   - Make sure this folder is writable (chmod 755)

### Step 3: Set File Permissions

In cPanel File Manager:
1. Right-click the `data` folder → Change Permissions
2. Set to `755` (Read, Write, Execute for Owner)
3. Make sure files are readable (`644`)

### Step 4: Access the Application

1. Open your browser
2. Go to: `https://yourdomain.com/sales-bi/login.php`
3. You should see the login screen

### Step 5: First Login

**Default Credentials:**
- Username: `admin`
- Password: `admin123`

⚠️ **IMPORTANT:** Change this password immediately!

---

## 📚 How to Use - Beginner's Guide

### First Time Setup

1. **Login** with default credentials
2. **Change password**:
   - Click Admin → Manage Users
   - Scroll down to "Change My Password"
   - Enter current password: `admin123`
   - Enter new secure password
   - Click "Change Password"

3. **Create viewer accounts** (optional):
   - Go to Admin → Manage Users
   - Fill in "Create New User" form
   - Select role: "Viewer" (read-only) or "Admin" (full access)
   - Click "Create User"

### Upload Sales Data

1. **Prepare your file**:
   - Export from QuickBooks as Excel (.xlsx) or CSV (.csv)
   - File should have columns: Type, Date, Num, Name, Item, Sales Tax Code, Qty, Amount
   - Maximum file size: 5MB

2. **Upload process**:
   - Go to Admin → Upload Data
   - Click "Choose File" or drag & drop
   - Click "Upload and Import"
   - System will:
     - Check for duplicates
     - Calculate VAT automatically
     - Store in database
     - Show import summary

3. **What happens to VAT**:
   - **Taxable Sales**: Amount shown is base value → 18% VAT added
   - **Non-Taxable Sales**: Amount shown includes VAT → VAT extracted

### View Dashboard

- **Go to Home → Dashboard**
- See:
  - Total revenue
  - Total VAT owed
  - Number of customers
  - Top customers (who spent the most)
  - Top products (what sold the most)
  - Risk indicators

### Generate Reports

**Monthly Report:**
- Admin → Reports → Monthly
- Select month and year
- See all transactions for that month
- Download or print

**Quarterly Report:**
- Shows 3-month summary
- Monthly breakdown included

**Yearly Report:**
- Full year overview
- Monthly comparison
- Trends analysis

---

## 🔒 Security Best Practices

### Passwords
- Change default password immediately
- Use strong passwords (mix of letters, numbers, symbols)
- Don't share passwords

### File Permissions
- Keep `data` folder permission at `755`
- Keep files at `644`
- Never give `777` permission to anything

### Access Control
- **Admin role**: Can upload data, manage users, see all reports
- **Viewer role**: Can only view reports, no uploads or changes

### Backup
- Regularly backup your `data/sales_bi.db` file
- Download it from cPanel File Manager
- Store safely on your computer

---

## 🆘 Troubleshooting

### "Error: Database Connection Failed"

**Problem**: Database can't be created
**Solution**:
1. Check if `data` folder exists
2. Check folder permissions (should be 755)
3. Check disk space available
4. Contact hosting support if persists

### "Error: Login Failed"

**Problem**: Can't login with admin123
**Solution**:
1. Make sure you're using correct username: `admin`
2. Try resetting password:
   - Delete `data/sales_bi.db` file
   - Reload login page
   - Default credentials will work again
3. Clear browser cache and try again

### "File Upload Failed"

**Problem**: Can't upload CSV/Excel file
**Solution**:
1. Check file size (max 5MB)
2. Check file format (.xlsx, .xls, or .csv only)
3. Check that `data` folder permission is 755
4. Try CSV format instead of Excel
5. Check file has required columns

### "No Records Imported"

**Problem**: File uploaded but no records imported
**Solution**:
1. Check if records already exist (duplicates skipped)
2. Verify file has correct columns
3. Check Sales Tax Code field is not empty
4. Check Amount column has values
5. Try with sample data first

### "Reports Show No Data"

**Problem**: Dashboard is empty
**Solution**:
1. Upload data first (Admin → Upload Data)
2. Check date range (select correct month/year)
3. Check if data was imported successfully
4. Try Monthly report first to test

---

## 📊 Understanding the Dashboard

### Key Metrics Explained

**Total Revenue (Base)**
- What you charged customers (before VAT)
- This is your actual revenue

**Total After VAT**
- What customers will pay (including VAT)
- The amount in invoices

**VAT Collected**
- 18% you must pay to government
- Do NOT spend this money!
- Set aside in separate account

**Customer Concentration**
- How much revenue comes from top customers
- **HIGH RISK** (>50%): Dangerous if top customer leaves
- **MEDIUM** (30-50%): Manageable but watch
- **LOW** (<30%): Good diversity

**Top Customers**
- Who are your biggest revenue sources
- Build strong relationships with them

**Top Products**
- What's selling the most
- Use to plan inventory

### Color Meanings
- 🔵 Blue: General info
- 🟡 Orange: Warning (needs attention)
- 🟢 Green: Good (all is well)
- 🔴 Red: Critical (immediate action needed)

---

## 🎓 Business Intelligence Basics

### What is BI (Business Intelligence)?

It means using data to make smart business decisions:
1. Collect data (sales transactions)
2. Organize it (database)
3. Analyze it (reports)
4. Make decisions (which customers to focus on)

### Key Questions This Answers

1. **How much am I making?**
   - Dashboard shows total revenue

2. **Who are my best customers?**
   - Top Customers table shows this

3. **What products sell best?**
   - Top Products table shows this

4. **Do I have enough diversity?**
   - Customer Concentration shows risk

5. **How much VAT do I owe government?**
   - VAT breakdown shows exact amount

6. **What are my trends?**
   - Monthly/Yearly reports show patterns

---

## 🔄 Regular Maintenance

### Daily
- Nothing needed
- Dashboard updates automatically

### Weekly
- Download backup of `data/sales_bi.db`
- Store on your computer

### Monthly
- Generate monthly report
- Check VAT owed
- Review top customers
- Pay VAT to government

### Every 3 Months
- Generate quarterly report
- Review trends
- Check customer concentration
- Plan next quarter

### Annually
- Generate yearly report
- Analyze full year performance
- Plan next year strategy
- Archive reports

---

## 📞 Support & Help

### If Something Goes Wrong

1. **Check the error message** - it usually tells you what's wrong
2. **Try logging out and back in**
3. **Clear browser cache** (sometimes helps)
4. **Check file permissions** in cPanel
5. **Contact your hosting provider** if server issues

### Contact Information
- Hosting support: Check your cPanel welcome email
- Your IT person: If you have one
- Security: Never share passwords via email

---

## 🎯 Next Steps

1. ✅ Upload your April 2026 sales file
2. ✅ Generate a monthly report
3. ✅ Review top customers
4. ✅ Check VAT owed
5. ✅ Create viewer account for your accountant
6. ✅ Set up monthly backup process

---

## 📝 Important Notes

- **Database Size**: Grows over time as you add more data. After 1 year of daily data: ~5-10MB
- **Performance**: Handles up to 50,000 transactions smoothly
- **Backup**: Always keep backups. Database corruption can be catastrophic
- **Password**: Once you change it, write it down and store safely (not in email)
- **VAT**: This is government revenue. Set aside completely separate from profit

---

## ✨ Tips for Success

1. **Upload regularly** - Monthly uploads keep data current
2. **Review monthly** - Check reports on same day each month
3. **Backup weekly** - One corrupt file = major headache
4. **Share with accountant** - Give them viewer access
5. **Watch VAT** - Track it to never miss payment
6. **Monitor customers** - If top customer leaves, you know immediately
7. **Track trends** - Spot patterns in sales

---

## 🚀 Advanced Tips (When You're Ready)

- Filter reports by date range
- Export transaction lists for Excel analysis
- Compare month-to-month for trends
- Use customer reports to plan marketing
- Use product reports to manage inventory
- Track new vs returning customers
- Monitor average order value over time

---

**Last Updated**: 2026-05-07
**Version**: 1.0.0
**Support**: Contact your hosting provider for technical issues
