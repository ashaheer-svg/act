# 📋 Deployment Checklist

Complete these steps to deploy the Sales BI Dashboard on your cPanel server.

---

## ✅ Pre-Deployment

- [ ] Read INSTALLATION_GUIDE.md completely
- [ ] Backup your current public_html folder
- [ ] Ensure you have cPanel access
- [ ] Check hosting support for PHP version (need 7.0+)
- [ ] Download all application files

---

## 📂 Step 1: Create Folder Structure

In cPanel File Manager:

- [ ] Navigate to `public_html` folder
- [ ] Create new folder: `sales-bi`
- [ ] Create subfolder inside `sales-bi`: `data`
- [ ] Create subfolder inside `sales-bi`: `classes`

---

## 📤 Step 2: Upload Files

Upload to `public_html/sales-bi/`:

**Root level files:**
- [ ] `config.php`
- [ ] `login.php`
- [ ] `index.php`
- [ ] `upload.php`
- [ ] `reports.php`
- [ ] `users.php`
- [ ] `logout.php`
- [ ] `.htaccess`
- [ ] `README.md`
- [ ] `INSTALLATION_GUIDE.md`

**In `classes/` folder:**
- [ ] `Database.php`
- [ ] `Auth.php`
- [ ] `DataImporter.php`
- [ ] `Reports.php`

---

## 🔐 Step 3: Set File Permissions

In cPanel File Manager:

**Folders (chmod 755):**
- [ ] sales-bi folder → Right-click → Change Permissions → 755
- [ ] classes folder → 755
- [ ] data folder → 755 (most important)

**Files (chmod 644):**
- [ ] All .php files → 644
- [ ] All markdown files → 644
- [ ] .htaccess → 644

**Verify:**
- [ ] data folder is NOT accessible via browser
- [ ] classes folder is NOT accessible via browser

---

## 🧪 Step 4: Test Installation

1. [ ] Open browser
2. [ ] Go to: `https://yourdomain.com/sales-bi/login.php`
3. [ ] You should see login page (no errors)
4. [ ] If blank page → Check permissions, contact hosting support
5. [ ] If "Error" page → Check error message, see INSTALLATION_GUIDE.md

---

## 🔑 Step 5: First Login & Setup

- [ ] Login with: admin / admin123
- [ ] You see the dashboard (if not → check Step 4)
- [ ] Go to Admin → Manage Users
- [ ] Go to "Change My Password" section
- [ ] Enter current password: `admin123`
- [ ] Enter new strong password (min 12 characters)
- [ ] Click "Change Password"
- [ ] **SAVE NEW PASSWORD SOMEWHERE SAFE**

---

## 👥 Step 6: Create Users (Optional)

If you want other people to access:

- [ ] Go to Admin → Manage Users
- [ ] Fill "Create New User" form:
  - [ ] Username: (give them a username)
  - [ ] Email: (their email)
  - [ ] Password: (temporary password)
  - [ ] Role: Select "Admin" or "Viewer"
- [ ] Click "Create User"
- [ ] Give username and password to user
- [ ] Tell them to change password on first login

---

## 📤 Step 7: Upload First Data File

- [ ] Prepare your QuickBooks export (Excel or CSV)
- [ ] Go to Admin → Upload Data
- [ ] Click "Choose File" and select your file
- [ ] Click "Upload and Import"
- [ ] Wait for upload to complete
- [ ] You should see success message
- [ ] Check "Import History" table below

---

## 📊 Step 8: Verify Dashboard

- [ ] Click Home → Dashboard
- [ ] You should see data on the dashboard:
  - [ ] Total Revenue shows a number
  - [ ] Total Invoices shows a number
  - [ ] Unique Customers shows a number
  - [ ] Top Customers table is populated
  - [ ] Top Products table is populated

If dashboard is empty:
- [ ] Did you upload data in Step 7?
- [ ] Check the import history shows successful import
- [ ] Try uploading again

---

## 🔄 Step 9: Test All Features

- [ ] **Dashboard** → All metrics load correctly
- [ ] **Monthly Report** → Select month, see data
- [ ] **Quarterly Report** → Select quarter, see data
- [ ] **Yearly Report** → Select year, see data
- [ ] **Upload Data** (Admin) → Upload another file
- [ ] **Manage Users** (Admin) → See user list
- [ ] **Logout** → Click logout, redirects to login
- [ ] **Login Again** → With your new password

---

## 💾 Step 10: Setup Backup Routine

- [ ] In cPanel File Manager, find `sales-bi/data/sales_bi.db`
- [ ] Right-click → Download to your computer
- [ ] Save it: `sales_bi_backup_[date].db`
- [ ] Store in safe location (not on web server)
- [ ] **Do this weekly minimum**

---

## 🔒 Step 11: Security Hardening

- [ ] [ ] Change default admin password ✅ (Step 5)
- [ ] [ ] Verify .htaccess file is in place
- [ ] [ ] Verify data folder inaccessible via browser
  - Open: `https://yourdomain.com/sales-bi/data/`
  - Should show "Access Denied" or error
- [ ] [ ] Verify classes folder inaccessible
  - Open: `https://yourdomain.com/sales-bi/classes/`
  - Should show "Access Denied" or error
- [ ] [ ] Check HTTPS is enabled
  - URL should be: `https://yourdomain.com` (not http://)

---

## 📋 Step 12: Final Verification

Run through this checklist to confirm everything works:

| Item | Status | Notes |
|------|--------|-------|
| Login page loads | ✅ or ❌ | |
| Can login with new password | ✅ or ❌ | |
| Dashboard shows data | ✅ or ❌ | |
| Can upload files | ✅ or ❌ | |
| Reports generate correctly | ✅ or ❌ | |
| Users can be created | ✅ or ❌ | |
| data folder is protected | ✅ or ❌ | |
| classes folder is protected | ✅ or ❌ | |
| HTTPS is active | ✅ or ❌ | |
| Backup process works | ✅ or ❌ | |

If any are ❌:
- [ ] Review INSTALLATION_GUIDE.md troubleshooting
- [ ] Check file permissions
- [ ] Contact hosting support

---

## 🎯 Step 13: Ongoing Maintenance

**Daily:**
- [ ] No action needed (automatic operation)

**Weekly:**
- [ ] Backup `data/sales_bi.db` file
- [ ] Download to your computer
- [ ] Verify file size is reasonable

**Monthly:**
- [ ] Upload latest sales data
- [ ] Generate monthly report
- [ ] Review top customers
- [ ] Check VAT owed
- [ ] Plan next month

**Quarterly:**
- [ ] Generate quarterly report
- [ ] Analyze trends
- [ ] Review customer concentration

**Annually:**
- [ ] Generate yearly report
- [ ] Archive all data backups
- [ ] Plan next year

---

## 📝 Documentation

- [ ] Read `README.md` for feature overview
- [ ] Read `INSTALLATION_GUIDE.md` for detailed help
- [ ] Bookmark both files for future reference
- [ ] Save this checklist for next deployment

---

## 🆘 If Something Goes Wrong

**Step A:** Identify the problem
- [ ] What specific action caused the error?
- [ ] What error message did you see?
- [ ] Write down the error exactly

**Step B:** Consult documentation
- [ ] Check INSTALLATION_GUIDE.md troubleshooting section
- [ ] Check if permissions are correct
- [ ] Check if file names are spelled correctly

**Step C:** Verify basics
- [ ] Folder exists: `sales-bi` ✅
- [ ] Permissions are 755 for folders, 644 for files ✅
- [ ] Database folder `data` is writable ✅
- [ ] PHP version is 7.0+ ✅

**Step D:** Get help
- [ ] Contact your hosting provider
- [ ] Tell them exactly what error you see
- [ ] Tell them file permissions you set
- [ ] Ask if SQLite is enabled (usually is)

---

## ✨ You're Done!

Once all checkboxes are completed:

✅ Application is installed  
✅ Data is uploaded  
✅ Dashboard is working  
✅ Users are set up  
✅ Backups are scheduled  
✅ Security is configured  

🎉 **You now have a fully functional Sales BI Dashboard!**

---

## 📌 Important Reminders

1. **Change default password** - immediately after installation
2. **Backup database** - weekly, store securely
3. **Monitor VAT** - set aside money for government payments
4. **Upload regularly** - keep data current (monthly minimum)
5. **Review reports** - monthly to track business health
6. **Keep passwords** - write down and store safely

---

**You did it! 🎉**

Your business now has professional analytics. Next step: Upload your data and start making data-driven decisions!

---

**Need help?** See INSTALLATION_GUIDE.md → Troubleshooting section
