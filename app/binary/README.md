# Sales BI Dashboard - QuickBooks Desktop Sync Utility

A lightweight, automated, and 100% **read-only** data synchronization utility designed to extract commercial invoices, received payments, and customer profiles from **QuickBooks Desktop** (Pro, Premier, Enterprise) and push them securely to the Sales BI Cloud Platform.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Extraction Scope (Latest Changes vs. Full Dataset)](#extraction-scope-latest-changes-vs-full-dataset)
3. [Prerequisites & System Requirements](#prerequisites--system-requirements)
4. [Quick Start (First-Time Setup)](#quick-start-first-time-setup)
5. [Automated Scheduling Guide](#automated-scheduling-guide)
   - [Method A: 1-Click Setup from Inside the App (Recommended)](#method-a-1-click-setup-from-inside-the-app-recommended)
   - [Method B: Command-Line Scheduling](#method-b-command-line-scheduling)
   - [Method C: Windows Task Scheduler GUI](#method-c-windows-task-scheduler-gui)
6. [QuickBooks Active Process Guard](#quickbooks-active-process-guard)
7. [Persistent File Logging (`logs/sync_log.txt`)](#persistent-file-logging-logssync_logtxt)
8. [Configuration Reference (`config.json`)](#configuration-reference-configjson)
9. [Historical & Legacy QuickBooks Extraction (2009 - 2021)](#historical--legacy-quickbooks-extraction-2009---2021)
10. [CLI Arguments Reference](#cli-arguments-reference)
11. [Troubleshooting & FAQ](#troubleshooting--faq)

---

## Key Features

- **100% Read-Only Safety**: Communicates through official QuickBooks Desktop QBXML APIs (`QBXMLRP2`). Zero write permissions or modifications to your company file.
- **Selectable Extraction Scope**: Choose between downloading **Latest Changes Only** (incremental sync since last sync date) or the **Full Dataset** (all historical records from the beginning).
- **QuickBooks Active Process Guard**: Detects whether QuickBooks (`qbw32.exe` / `qbw.exe`) is actively running. If closed, the export aborts gracefully without freezing or showing background task warnings in QuickBooks.
- **Flexible Scheduling**: Configure automated synchronization at your chosen frequency (every 15m, 30m, 1h, 2h, 4h, 12h, or Daily) directly from the application menu or command line.
- **Audit Logging**: Every sync attempt records timestamp, status (`SUCCESS`, `SKIPPED`, `FAILURE`), duration, and exact counts of transferred invoices, payments, and customers in `logs/sync_log.txt`.
- **Automatic Deduplication & Reconciliation**: Handles duplicate prevention, tax decomposition (18% statutory VAT vs. base revenue), and customer credit status calculations automatically on the server.
- **Local Data Export**: Automatically saves an offline copy of every sync payload in JSON and CSV formats under `exports/` for independent financial auditing.

---

## Extraction Scope (Latest Changes vs. Full Dataset)

When extracting data from QuickBooks Desktop, you can choose between two extraction modes depending on your objective:

### 1. Latest Changes Only (Incremental Sync)
- **What it does**: Queries QuickBooks for transactions created or modified on or after `last_sync_date` stored in `config.json`.
- **Best for**: Routine background syncs, scheduled tasks, and day-to-day operations.
- **Performance**: Extremely fast (typically completes in 2–5 seconds).
- **Usage**:
  - Interactive: Choose **`[1]`** or **`[2]`**, then select **`[1] Latest Changes Only`** at the prompt.
  - CLI: `SalesBISync.exe --sync --incremental` or `SalesBISync.exe --export-only --incremental`.

### 2. Full Dataset (Download All Information)
- **What it does**: Bypasses `last_sync_date` and queries QuickBooks for **all** invoices, payments, and customer accounts from the beginning of time for the open company file.
- **Best for**: Initial system setup, database rebuilds, periodic reconciliations, or comprehensive offline audit exports.
- **Performance**: Thorough and complete.
- **Usage**:
  - Interactive: Choose **`[1]`** or **`[2]`**, then select **`[2] Full Dataset`** at the prompt.
  - Quick 1-Key Shortcut: Press **`[F]`** in the main menu to immediately run a Full Sync to the Web Dashboard.
  - CLI: `SalesBISync.exe --sync --full` (or `-a`, `--all`).
  - Offline Export: `SalesBISync.exe --export-only --full`.

### Resetting the Sync Cursor
If you ever want future automated syncs to start fresh from the beginning, press **`[R]`** (*Reset Last Sync Cursor*) in the main menu. This clears the `last_sync_date` entry in `config.json`.

---

## Prerequisites & System Requirements

- **Operating System**: Windows 10, Windows 11, or Windows Server 2016+ (64-bit or 32-bit).
- **QuickBooks Desktop**: QuickBooks Desktop Pro, Premier, or Enterprise (Version 2014 or newer).
- **Network**: Outbound HTTPS access to `https://act.active.lk/api/sync.php`.
- **Permissions**: Standard user permissions for manual execution; Administrator privileges if installing a Windows Scheduled Task for all users.

---

## Quick Start (First-Time Setup)

1. **Extract the Application**:
   Extract `config.zip` into a dedicated folder on the PC where QuickBooks Desktop is installed (e.g., `C:\SalesBISync\`).
2. **Open QuickBooks Desktop**:
   Launch QuickBooks Desktop and open your company file (`.qbw`) as an **Administrator** for the initial handshake.
3. **Run `SalesBISync.exe`**:
   Double-click `SalesBISync.exe`.
4. **Authorize with QuickBooks**:
   QuickBooks will display an "Application Certificate" dialog:
   - Select: **"Yes, always; allow access even if QuickBooks is not running"** (or "Yes, whenever this QuickBooks company file is open").
   - Click **Continue** and **Done**.
5. **Verify Connection**:
   - Choose **`[3] Test QuickBooks Desktop Connection`** in the main menu to confirm read-only access.
   - Choose **`[4] Test Web Dashboard API Connection`** to verify your API connection to `https://act.active.lk/api/sync.php`.
6. **Run Initial Sync**:
   Choose **`[F] Run Quick FULL Sync`** (or **`[1]`** and select **`[2] Full Dataset`**) to perform the initial complete ingestion.

---

## Automated Scheduling Guide

You can configure SalesBISync to run automatically in the background on an hourly or customized schedule.

### Method A: 1-Click Setup from Inside the App (Recommended)
1. Run `SalesBISync.exe`.
2. In the main menu, press **`S`** (Configure Automated Sync Schedule).
3. Select your desired frequency:
   ```text
   [1] Every 15 Minutes
   [2] Every 30 Minutes
   [3] Every 1 Hour (Recommended default)
   [4] Every 2 Hours
   [5] Every 4 Hours
   [6] Every 12 Hours
   [7] Once Daily (Every morning at 08:30 AM)
   [8] Custom Interval (Specify minutes)
   [0] Disable / Remove Scheduled Task
   ```
4. The application automatically communicates with Windows Task Scheduler (`schtasks.exe`) and confirms task creation.
5. Once configured, the sync will run silently in the background at your chosen frequency.

### Method B: Command-Line Scheduling
You can also install or remove the schedule directly via command prompt or PowerShell:
```cmd
# Schedule to run every 1 hour (default):
SalesBISync.exe --schedule

# Schedule to run every 30 minutes:
SalesBISync.exe --schedule 30

# Schedule to run every 2 hours (120 minutes):
SalesBISync.exe --schedule 120

# Check current scheduler status:
SalesBISync.exe --schedule-status

# Disable / Remove scheduled task:
SalesBISync.exe --unschedule
```

### Method C: Windows Task Scheduler GUI (`taskschd.msc`)
If your IT policy requires manual configuration via the Windows GUI:
1. Press `Win + R`, type `taskschd.msc`, and press **Enter**.
2. Click **Create Task...** (not Basic Task).
3. **General Tab**:
   - Name: `SalesBI_QuickBooks_Sync`
   - Security options: Select **Run whether user is logged on or not** and check **Run with highest privileges**.
4. **Triggers Tab**:
   - Click **New...**
   - Begin the task: **On a schedule** (Daily).
   - Under Advanced settings: Check **Repeat task every:** `1 hour` for a duration of: `Indefinitely`.
5. **Actions Tab**:
   - Action: **Start a program**
   - Program/script: `C:\SalesBISync\SalesBISync.exe`
   - Add arguments: `--sync --quiet`
   - Start in (crucial!): `C:\SalesBISync\`
6. **Conditions Tab**:
   - Uncheck "Stop if the computer switches to battery power".
7. Click **OK** and enter your Windows credentials if prompted.

---

## QuickBooks Active Process Guard

By default, `require_qb_running` is set to `true` in `config.json`.

- **How It Works**: Before starting extraction, `SalesBISync.exe` inspects running system processes for `qbw32.exe` (32-bit QB) or `qbw.exe` (64-bit QB).
- **If QuickBooks is Closed**: The utility prevents COM invocation, writes a `[SKIPPED]` entry to `logs/sync_log.txt`, and exits with **code 2**.
- **Why This Matters**:
  - Eliminates modal dialog lockups.
  - Prevents QuickBooks from warning that "another background task is running" when attempting to close QuickBooks.
  - Leaves zero stranded COM sessions in Windows memory.

> If you want the sync to launch QuickBooks headless even when closed, set `"require_qb_running": false` in `config.json` or pass the `--no-qb-check` flag.

---

## Persistent File Logging (`logs/sync_log.txt`)

Every execution (manual or scheduled) appends a structured entry to `logs/sync_log.txt`.

### Sample Log Output:
```text
[2026-09-07 09:00:00] [SUCCESS] Sync completed successfully. Extracted & Transferred: 142 Invoices, 38 Payments, 12 Customers. Server: 142 imported, 0 skipped. Duration: 3.42s.
[2026-09-07 10:00:00] [SKIPPED] QuickBooks Desktop is not running (qbw32.exe or qbw.exe process not found). Data export did not start.
[2026-09-07 11:00:00] [SUCCESS] Sync completed. 0 new records found since 2026-09-07T09:00:00. Duration: 1.15s.
[2026-09-07 12:00:00] [FAILURE] Upload failed: HTTP 500 (Internal Server Error): Database lock timeout.
```

### Viewing Logs:
- **Inside the App**: Press **`L`** in the main menu to view the last 25 entries in colored terminal output.
- **In Windows**: Open `logs/sync_log.txt` directly with Notepad or any text editor.

---

## Configuration Reference (`config.json`)

```json
{
  "server_url": "https://act.active.lk/api/sync.php",
  "api_key": "5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867",
  "last_sync_date": "2026-09-07T09:00:00",
  "qb_company_file": "",
  "sync_interval_minutes": 60,
  "require_qb_running": true,
  "log_file": "logs/sync_log.txt",
  "include_serial_numbers": true,
  "save_local_copy": true,
  "export_folder": "exports",
  "batch_size": 500
}
```

| Setting | Type | Description |
|---|---|---|
| `server_url` | string | Target Web API ingestion endpoint (`https://act.active.lk/api/sync.php`). |
| `api_key` | string | Secure 48-character synchronization authentication token. |
| `last_sync_date` | string | Timestamp of last successful sync. Only newer/modified records are fetched. |
| `qb_company_file` | string | Absolute path to `.qbw` file. Leave blank to connect to whichever file is open. |
| `sync_interval_minutes`| integer| Repetition interval in minutes (default `60` = 1 hour). |
| `require_qb_running` | boolean | `true` prevents export unless QuickBooks is actively open. |
| `log_file` | string | Path to sync log file (default `logs/sync_log.txt`). |
| `include_serial_numbers`| boolean | Extracts hardware serial numbers from invoice line item descriptions. |
| `save_local_copy` | boolean | Saves offline JSON and CSV backups in `exports/` folder. |
| `export_folder` | string | Destination directory for offline backups. |

---

## Historical & Legacy QuickBooks Extraction (2009 - 2021)

If you have an older QuickBooks Desktop instance or company file holding historical records from **2009 to 2021**, you can extract the entire transaction archive safely to local JSON & CSV files without disturbing your active synchronization date cursor.

### Interactive Extraction:
1. Open your historical QuickBooks Desktop company file (2009–2021).
2. Launch `SalesBISync.exe`.
3. Choose **`[H] Extract Historical Archive Range (e.g. 2009-2021 to Local JSON & CSV)`**.
4. Enter Start Date (e.g., `2009-01-01`) and End Date (e.g., `2021-12-31`).
5. Confirm. The utility will query the historical invoices, received payments, and customer accounts, saving files directly into `exports/legacy_qb_export_YYYYMMDD_HHMMSS.json`.
6. Go to **Operations > Legacy QB (2009-2021)** on the web dashboard (`https://act.active.lk/import_legacy_qb.php`) and upload the file to ingest the historical archive.

### Command-Line Extraction:
```cmd
# Extract 2009-2021 archive directly to local exports folder:
SalesBISync.exe --from 2009-01-01 --to 2021-12-31 --export-only

# Extract and upload directly to web dashboard:
SalesBISync.exe --from 2009-01-01 --to 2021-12-31 --sync
```

---

## CLI Arguments Reference

| Argument | Description | Exit Codes |
|---|---|---|
| `--sync`, `-s` | Run automated headless sync (extract and upload). | `0` = Success, `1` = Failure, `2` = Skipped (QB not running) |
| `--export-only`, `-e` | Extract from QuickBooks and save local JSON/CSV exports without uploading. | `0` = Success, `1` = Error |
| `--full`, `-a`, `--all` | Download FULL dataset (all records from the beginning, bypasses `last_sync_date`). | `0` = Success, `1` = Error |
| `--incremental`, `-i` | Download latest changes only (modified since `last_sync_date`). | `0` = Success, `1` = Error |
| `--quiet`, `-q` | Suppress interactive/console output (recommended for background Task Scheduler). | Same as `--sync` |
| `--from`, `-f <date>` | Extract transactions starting from date (`YYYY-MM-DD`), e.g., `--from 2009-01-01`. | - |
| `--to`, `-t <date>` | Extract transactions up to date (`YYYY-MM-DD`), e.g., `--to 2021-12-31`. | - |
| `--mock` | Simulates sync with test data (does not connect to QuickBooks). | `0` = Success |
| `--dry-run` | Extracts from QuickBooks but skips uploading to the server. | `0` = Success |
| `--no-qb-check` | Disables the check for running `qbw32.exe` process. | - |
| `--schedule [min]` | Registers Windows Scheduled Task with the specified interval. | `0` = Success, `1` = Error |
| `--unschedule` | Deletes the registered Windows Scheduled Task. | `0` = Success, `1` = Error |
| `--schedule-status`| Outputs current state of the registered Windows Scheduled Task. | `0` = Success |
| `--config <path>` | Uses a specific configuration file instead of `./config.json`. | - |
| `--help`, `-h` | Displays the help manual. | `0` |

---

## Troubleshooting & FAQ

### 1. "QuickBooks XML Request Processor is not registered (0x80040154)"
- **Cause**: Architecture mismatch. QuickBooks Desktop COM DLLs are 32-bit (`x86`).
- **Solution**: Always run the 32-bit (`win-x86`) build of `SalesBISync.exe`. If you need to re-register the DLL, open Command Prompt as Administrator and run:
  ```cmd
  regsvr32 "C:\Program Files (x86)\Common Files\Intuit\QuickBooks\QBXMLRP2.dll"
  ```

### 2. "QuickBooks Desktop is not running (qbw32.exe not found)"
- **Normal Behavior**: By design, if QuickBooks is closed, the sync will log `[SKIPPED]` and wait for the next scheduled interval when the accounting team opens QuickBooks.
- **To Override**: Set `"require_qb_running": false` in `config.json` if you want QuickBooks to launch automatically in unattended mode.

### 3. "Task Scheduler Error 0x1 or Task Does Not Run"
- Ensure that the **"Start in"** field in Task Scheduler is configured to the directory where `SalesBISync.exe` lives (e.g. `C:\SalesBISync\`).
- If running under a system account, make sure the user account has access to the QuickBooks company file directory.

### 4. Support & Maintenance
For further assistance, reach out to your Sales BI administrative team or check the Data Explorer at `https://act.active.lk/explorer.php`.
