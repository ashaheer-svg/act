using System.Diagnostics;

namespace SalesBISync;

class Program
{
    static async Task<int> Main(string[] args)
    {
        Console.OutputEncoding = System.Text.Encoding.UTF8;

        if (args.Contains("--help") || args.Contains("-h") || args.Contains("-?"))
        {
            PrintHelp();
            return 0;
        }

        // Parse command line arguments
        bool isSync = args.Contains("--sync") || args.Contains("-s");
        bool isQuiet = args.Contains("--quiet") || args.Contains("-q");
        bool isDryRun = args.Contains("--dry-run");
        bool isMock = args.Contains("--mock");
        bool isExportOnly = args.Contains("--export-only") || args.Contains("-e");
        bool noQbCheck = args.Contains("--no-qb-check");
        bool isFull = args.Contains("--full") || args.Contains("-a") || args.Contains("--all");
        bool isIncremental = args.Contains("--incremental") || args.Contains("-i");
        string? customConfig = null;
        string? fromTxnDate = null;
        string? toTxnDate = null;

        for (int i = 0; i < args.Length; i++)
        {
            if (args[i] == "--config" && i + 1 < args.Length)
            {
                customConfig = args[i + 1];
            }
            else if ((args[i] == "--from" || args[i] == "-f") && i + 1 < args.Length)
            {
                fromTxnDate = args[i + 1];
            }
            else if ((args[i] == "--to" || args[i] == "-t") && i + 1 < args.Length)
            {
                toTxnDate = args[i + 1];
            }
        }

        var config = ConfigManager.LoadConfig(customConfig);
        if (noQbCheck)
        {
            config.RequireQbRunning = false;
        }

        // Dedicated Task Scheduler CLI options
        if (args.Contains("--unschedule"))
        {
            var (ok, msg) = TaskSchedulerManager.UninstallTask();
            Console.WriteLine(msg);
            return ok ? 0 : 1;
        }

        if (args.Contains("--schedule-status"))
        {
            var (_, summary, details) = TaskSchedulerManager.GetTaskStatus();
            Console.WriteLine($"Task Scheduler Status: {summary}");
            Console.WriteLine();
            Console.WriteLine(details);
            return 0;
        }

        for (int i = 0; i < args.Length; i++)
        {
            if (args[i] == "--schedule")
            {
                int interval = config.SyncIntervalMinutes > 0 ? config.SyncIntervalMinutes : 60;
                if (i + 1 < args.Length && int.TryParse(args[i + 1], out int customInterval) && customInterval > 0)
                {
                    interval = customInterval;
                }

                var (ok, msg) = TaskSchedulerManager.InstallTask(interval, customConfig);
                Console.WriteLine(msg);
                if (ok)
                {
                    config.SyncIntervalMinutes = interval;
                    ConfigManager.SaveConfig(config, customConfig);
                }
                return ok ? 0 : 1;
            }
        }

        // Headless execution (Task Scheduler, CLI sync, or historical date range extraction)
        bool hasDateRange = !string.IsNullOrEmpty(fromTxnDate) || !string.IsNullOrEmpty(toTxnDate);
        if (isSync || isQuiet || isDryRun || isMock || isExportOnly || hasDateRange || isFull || isIncremental)
        {
            // If historical date range specified without explicit --sync, default to save local export
            bool exportOnly = isExportOnly || (hasDateRange && !isSync);
            bool? forceFull = isFull ? true : (isIncremental ? false : (bool?)null);
            return await RunHeadlessSyncAsync(config, isQuiet, isDryRun, isMock, exportOnly, customConfig, fromTxnDate, toTxnDate, forceFull);
        }

        // Interactive Console Menu
        await RunInteractiveMenuAsync(config, customConfig);
        return 0;
    }

    private static void SafeClear()
    {
        try
        {
            if (!Console.IsOutputRedirected && !Console.IsInputRedirected)
            {
                Console.Clear();
            }
        }
        catch
        {
            // Ignore if running without valid console window
        }
    }

    private static void PrintHelp()
    {
        PrintBanner();
        Console.WriteLine("Usage: SalesBISync.exe [options]");
        Console.WriteLine();
        Console.WriteLine("Sync & Extraction Options:");
        Console.WriteLine("  --sync, -s          Run automated sync (extract and upload to web dashboard)");
        Console.WriteLine("  --export-only, -e   Extract from QuickBooks and save local JSON/CSV exports without uploading");
        Console.WriteLine("  --full, -a, --all   Download FULL dataset (all records from the beginning, bypass LastSyncDate)");
        Console.WriteLine("  --incremental, -i   Download latest changes only (modified since LastSyncDate)");
        Console.WriteLine("  --mock              Run using simulated QuickBooks test data (no QB install needed)");
        Console.WriteLine("  --dry-run           Simulate the extraction and payload generation without uploading");
        Console.WriteLine("  --no-qb-check       Bypass check for running QuickBooks process");
        Console.WriteLine("  --quiet, -q         Suppress non-error output (recommended for Windows Task Scheduler)");
        Console.WriteLine("  --config <path>     Specify custom path to config.json");
        Console.WriteLine();
        Console.WriteLine("Historical & Date Range Extraction (e.g., 2009-2021 legacy QB):");
        Console.WriteLine("  --from, -f <date>   Extract transactions starting from date (YYYY-MM-DD), e.g. 2009-01-01");
        Console.WriteLine("  --to, -t <date>     Extract transactions up to date (YYYY-MM-DD), e.g. 2021-12-31");
        Console.WriteLine();
        Console.WriteLine("Automated Scheduling Options:");
        Console.WriteLine("  --schedule [min]    Register Windows Scheduled Task (default: 60 min, or specify custom minutes)");
        Console.WriteLine("  --unschedule        Remove the registered Windows Scheduled Task");
        Console.WriteLine("  --schedule-status   Display status of the registered Windows Scheduled Task");
        Console.WriteLine();
        Console.WriteLine("Help:");
        Console.WriteLine("  --help, -h          Show this help screen");
        Console.WriteLine();
    }

    private static async Task RunInteractiveMenuAsync(SyncConfig config, string? configPath)
    {
        while (true)
        {
            SafeClear();
            PrintBanner();

            string exportDir = Path.IsPathRooted(config.ExportFolder)
                ? config.ExportFolder
                : Path.Combine(AppDomain.CurrentDomain.BaseDirectory, config.ExportFolder);

            var (isScheduled, schedSummary, _) = TaskSchedulerManager.GetTaskStatus();

            Console.WriteLine(" Current Configuration & System Status:");
            Console.WriteLine($"   Server URL     : {(string.IsNullOrEmpty(config.ServerUrl) ? "[NOT CONFIGURED]" : config.ServerUrl)}");
            Console.WriteLine($"   API Key        : {(string.IsNullOrEmpty(config.ApiKey) ? "[NOT CONFIGURED]" : MaskKey(config.ApiKey))}");
            Console.WriteLine($"   Last Sync      : {(string.IsNullOrEmpty(config.LastSyncDate) ? "Never" : config.LastSyncDate)}");
            Console.WriteLine($"   Scheduler      : {(isScheduled ? $"Active ({schedSummary})" : "Not Scheduled (Manual sync only)")}");
            Console.WriteLine($"   QB Active Guard: {(config.RequireQbRunning ? "Enabled (Export aborted if QB closed)" : "Disabled")}");
            Console.WriteLine($"   Sync Log File  : {Logger.ResolvePath(config.LogFile)}");
            Console.WriteLine($"   Save Local Copy: {(config.SaveLocalCopy ? $"Enabled (Saves to {config.ExportFolder}/)" : "Disabled")}");
            Console.WriteLine($"   Config File    : {ConfigManager.GetConfigPath(configPath)}");
            Console.WriteLine(new string('-', 72));
            Console.WriteLine();

            Console.WriteLine(" Select an Action:");
            Console.WriteLine("   [1] Sync to Web Dashboard (Choose: Latest Changes Only or Full Dataset)");
            Console.WriteLine("   [2] Extract & Save Locally ONLY (Choose: Latest Changes Only or Full Dataset for Analysis)");
            Console.WriteLine("   [F] Run Quick FULL Sync (Download ALL records -> Web Dashboard)");
            Console.WriteLine("   [H] Extract Historical Archive Range (e.g. 2009-2021 to Local JSON & CSV)");
            Console.WriteLine("   [3] Test QuickBooks Desktop Connection (Read-Only)");
            Console.WriteLine("   [4] Test Web Dashboard API Connection");
            Console.WriteLine("   [5] Run Mock Sync (Upload sample invoice with serial numbers)");
            Console.WriteLine("   [6] Export Mock Data Locally (Sample JSON & CSV for testing)");
            Console.WriteLine("   [7] Open Exports Folder in File Explorer");
            Console.WriteLine("   [8] Edit Configuration Settings");
            Console.WriteLine("   [R] Reset Last Sync Cursor (Clears sync timestamp so next extraction is Full)");
            Console.WriteLine("   [S] Configure Automated Sync Schedule (Windows Task Scheduler)");
            Console.WriteLine("   [L] View Recent Sync Log Entries (logs/sync_log.txt)");
            Console.WriteLine("   [9] Exit");
            Console.WriteLine();
            Console.Write(" Enter your choice [1-9, F, H, R, S, L]: ");

            string? choice = Console.ReadLine()?.Trim().ToUpperInvariant();
            Console.WriteLine();

            switch (choice)
            {
                case "1":
                    await ExecuteSyncAsync(config, isMock: false, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: false);
                    break;
                case "2":
                    await ExecuteSyncAsync(config, isMock: false, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: true);
                    break;
                case "F":
                    await ExecuteSyncAsync(config, isMock: false, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: false, forceFull: true);
                    break;
                case "H":
                    await PromptHistoricalExtractionAsync(config, configPath);
                    break;
                case "3":
                    ExecuteTestQuickBooks(config);
                    break;
                case "4":
                    await ExecuteTestApiAsync(config);
                    break;
                case "5":
                    await ExecuteSyncAsync(config, isMock: true, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: false);
                    break;
                case "6":
                    await ExecuteSyncAsync(config, isMock: true, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: true);
                    break;
                case "7":
                    Console.WriteLine($"Opening exports folder: {exportDir}");
                    DataExporter.OpenFolderInExplorer(exportDir);
                    break;
                case "8":
                    EditConfigInteractive(config, configPath);
                    break;
                case "R":
                    config.LastSyncDate = "";
                    ConfigManager.SaveConfig(config, configPath);
                    PrintSuccess("Last sync date cleared! Next extraction will download the FULL dataset from the beginning.", false);
                    break;
                case "S":
                    ConfigureSchedulerInteractive(config, configPath);
                    break;
                case "L":
                    ViewRecentLogsInteractive(config);
                    break;
                case "9":
                    Console.WriteLine("Exiting. Have a great day!");
                    return;
                default:
                    Console.ForegroundColor = ConsoleColor.Yellow;
                    Console.WriteLine("Invalid selection. Press [Enter] to continue...");
                    Console.ResetColor();
                    break;
            }

            Console.WriteLine();
            Console.Write("Press [Enter] to return to the main menu...");
            Console.ReadLine();
        }
    }

    private static void ConfigureSchedulerInteractive(SyncConfig config, string? configPath)
    {
        SafeClear();
        PrintBanner();
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine(" ╔══════════════════════════════════════════════════════════════════╗");
        Console.WriteLine(" ║ ⏱️  WINDOWS TASK SCHEDULER AUTOMATION SETUP                       ║");
        Console.WriteLine(" ╚══════════════════════════════════════════════════════════════════╝");
        Console.ResetColor();
        Console.WriteLine();

        var (isInstalled, statusSummary, details) = TaskSchedulerManager.GetTaskStatus();
        Console.WriteLine($" Current Status: {statusSummary}");
        Console.WriteLine();
        Console.WriteLine(" Select Sync Frequency:");
        Console.WriteLine("   [1] Every 15 Minutes");
        Console.WriteLine("   [2] Every 30 Minutes");
        Console.WriteLine("   [3] Every 1 Hour (Recommended default)");
        Console.WriteLine("   [4] Every 2 Hours");
        Console.WriteLine("   [5] Every 4 Hours");
        Console.WriteLine("   [6] Every 12 Hours");
        Console.WriteLine("   [7] Once Daily (Every morning at 08:30 AM)");
        Console.WriteLine("   [8] Custom Interval (Specify minutes)");
        Console.WriteLine("   [9] View Full Task Scheduler Status & Diagnostic Details");
        Console.WriteLine("   [0] Disable / Remove Scheduled Task");
        Console.WriteLine("   [B] Back to Main Menu");
        Console.WriteLine();
        Console.Write(" Enter your choice: ");

        string? choice = Console.ReadLine()?.Trim().ToLowerInvariant();
        int intervalMinutes = 0;

        switch (choice)
        {
            case "1": intervalMinutes = 15; break;
            case "2": intervalMinutes = 30; break;
            case "3": intervalMinutes = 60; break;
            case "4": intervalMinutes = 120; break;
            case "5": intervalMinutes = 240; break;
            case "6": intervalMinutes = 720; break;
            case "7": intervalMinutes = 1440; break;
            case "8":
                Console.Write(" Enter custom sync interval in minutes (e.g. 45): ");
                if (int.TryParse(Console.ReadLine()?.Trim(), out int customVal) && customVal > 0)
                {
                    intervalMinutes = customVal;
                }
                else
                {
                    Console.WriteLine("Invalid interval. Must be a positive integer.");
                    return;
                }
                break;
            case "9":
                Console.WriteLine("\n--- Full Windows Task Scheduler Output ---");
                Console.WriteLine(details);
                return;
            case "0":
                var (rmOk, rmMsg) = TaskSchedulerManager.UninstallTask();
                if (rmOk) PrintSuccess(rmMsg, false);
                else PrintError(rmMsg, false);
                return;
            case "b":
            case "":
                return;
            default:
                Console.WriteLine("Invalid selection.");
                return;
        }

        if (intervalMinutes > 0)
        {
            Console.WriteLine($"\nRegistering Windows Scheduled Task for every {intervalMinutes} minutes...");
            var (ok, msg) = TaskSchedulerManager.InstallTask(intervalMinutes, configPath);
            if (ok)
            {
                config.SyncIntervalMinutes = intervalMinutes;
                ConfigManager.SaveConfig(config, configPath);
                PrintSuccess(msg, false);
                Console.WriteLine($"\nNote: The scheduled task runs: '{TaskSchedulerManager.GetExecutablePath()} --sync --quiet'");
                Console.WriteLine($"Activity and transfer counts will be logged to: {Logger.ResolvePath(config.LogFile)}");
            }
            else
            {
                PrintError(msg, false);
                Console.WriteLine("\nTip: Run this application as Administrator if permission was denied by Windows.");
            }
        }
    }

    private static void ViewRecentLogsInteractive(SyncConfig config)
    {
        SafeClear();
        PrintBanner();
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine(" ╔══════════════════════════════════════════════════════════════════╗");
        Console.WriteLine(" ║ 📋 RECENT SYNC LOG ENTRIES                                       ║");
        Console.WriteLine(" ╚══════════════════════════════════════════════════════════════════╝");
        Console.ResetColor();
        Console.WriteLine($" Log file: {Logger.ResolvePath(config.LogFile)}\n");

        var entries = Logger.ReadRecentEntries(config.LogFile, 25);
        if (entries.Length == 0)
        {
            Console.WriteLine(" No log entries recorded yet.");
        }
        else
        {
            foreach (var line in entries)
            {
                if (line.Contains("[SUCCESS]"))
                {
                    Console.ForegroundColor = ConsoleColor.Green;
                }
                else if (line.Contains("[SKIPPED]"))
                {
                    Console.ForegroundColor = ConsoleColor.Yellow;
                }
                else if (line.Contains("[FAILURE]"))
                {
                    Console.ForegroundColor = ConsoleColor.Red;
                }
                else
                {
                    Console.ResetColor();
                }
                Console.WriteLine(line);
            }
            Console.ResetColor();
        }
    }

    private static async Task PromptHistoricalExtractionAsync(SyncConfig config, string? configPath)
    {
        SafeClear();
        PrintBanner();
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine(" ╔══════════════════════════════════════════════════════════════════╗");
        Console.WriteLine(" ║ 📜 HISTORICAL ARCHIVE DATA EXTRACTION (2009 - 2021)              ║");
        Console.WriteLine(" ╚══════════════════════════════════════════════════════════════════╝");
        Console.ResetColor();
        Console.WriteLine();
        Console.WriteLine(" This extracts all sales transactions within a specific date range");
        Console.WriteLine(" from QuickBooks Desktop and saves clean JSON & CSV exports locally.");
        Console.WriteLine();
        Console.Write(" Enter Start Date (YYYY-MM-DD) [Default: 2009-01-01]: ");
        string? fromInput = Console.ReadLine()?.Trim();
        string fromDate = string.IsNullOrEmpty(fromInput) ? "2009-01-01" : fromInput;

        Console.Write(" Enter End Date (YYYY-MM-DD) [Default: 2021-12-31]: ");
        string? toInput = Console.ReadLine()?.Trim();
        string toDate = string.IsNullOrEmpty(toInput) ? "2021-12-31" : toInput;

        Console.WriteLine();
        Console.Write($" Extract from {fromDate} to {toDate}? [Y/n]: ");
        string? confirm = Console.ReadLine()?.Trim().ToLowerInvariant();
        if (confirm == "n" || confirm == "no")
        {
            Console.WriteLine("Extraction cancelled.");
            return;
        }

        Console.WriteLine();
        await ExecuteSyncAsync(config, isMock: false, isDryRun: false, configPath, isQuiet: false, saveLocalOnly: true, fromTxnDate: fromDate, toTxnDate: toDate);
    }

    private static async Task<int> RunHeadlessSyncAsync(
        SyncConfig config,
        bool isQuiet,
        bool isDryRun,
        bool isMock,
        bool isExportOnly,
        string? configPath,
        string? fromTxnDate = null,
        string? toTxnDate = null,
        bool? forceFull = null)
    {
        if (!isQuiet)
        {
            string scopeText = (!string.IsNullOrEmpty(fromTxnDate) || !string.IsNullOrEmpty(toTxnDate))
                ? $" [Date Range: {fromTxnDate ?? "Earliest"} to {toTxnDate ?? "Latest"}]"
                : (forceFull == true ? " [Scope: FULL DATASET]" : (!string.IsNullOrEmpty(config.LastSyncDate) ? $" [Scope: INCREMENTAL since {config.LastSyncDate}]" : " [Scope: FULL DATASET]"));
            Console.WriteLine($"[INFO] Starting {(isMock ? "Mock " : "")}{(isExportOnly ? "Local Export" : "Sync")}{scopeText}: {DateTime.Now}");
        }

        return await ExecuteSyncAsync(config, isMock, isDryRun, configPath, isQuiet, saveLocalOnly: isExportOnly, isHeadless: true, fromTxnDate: fromTxnDate, toTxnDate: toTxnDate, forceFull: forceFull);
    }

    private static async Task<int> ExecuteSyncAsync(
        SyncConfig config,
        bool isMock,
        bool isDryRun,
        string? configPath,
        bool isQuiet = false,
        bool saveLocalOnly = false,
        bool isHeadless = false,
        string? fromTxnDate = null,
        string? toTxnDate = null,
        bool? forceFull = null)
    {
        var stopwatch = Stopwatch.StartNew();

        if (!saveLocalOnly)
        {
            if (string.IsNullOrWhiteSpace(config.ServerUrl))
            {
                string msg = "Server URL is missing in config.json.";
                Logger.LogFailure(config.LogFile, msg);
                PrintError(msg, isQuiet);
                return 1;
            }

            if (string.IsNullOrWhiteSpace(config.ApiKey))
            {
                string msg = "API Key is missing in config.json.";
                Logger.LogFailure(config.LogFile, msg);
                PrintError(msg, isQuiet);
                return 1;
            }
        }

        // Pre-flight check: Verify if QuickBooks process is running before starting extraction
        if (config.RequireQbRunning && !isMock)
        {
            if (!QuickBooksConnector.IsQuickBooksRunning())
            {
                string skipMsg = "QuickBooks Desktop is not running (qbw32.exe or qbw.exe process not found). Data export did not start.";
                Logger.LogSkip(config.LogFile, skipMsg);
                PrintWarning(skipMsg, isQuiet);
                return 2; // Exit code 2 indicates skipped due to QB not running
            }
        }

        if (!isQuiet)
        {
            Console.ForegroundColor = ConsoleColor.Cyan;
            Console.WriteLine(@" ╔══════════════════════════════════════════════════════════════════╗");
            Console.WriteLine(isMock 
                ? @" ║ 🧪 GENERATING MOCK DATA FOR TESTING                              ║" 
                : @" ║ 🔄 EXTRACTING DATA FROM QUICKBOOKS DESKTOP (READ-ONLY)           ║");
            Console.WriteLine(@" ╚══════════════════════════════════════════════════════════════════╝");
            Console.ResetColor();
        }

        List<InvoiceRecord> invoices;
        List<PaymentRecord> payments;
        List<CustomerRecord> customers;

        if (isMock)
        {
            using var progress = new ProgressIndicator("Generating mock invoices, payments, and customers...", isQuiet);
            await Task.Delay(350);
            (invoices, payments, customers) = QuickBooksConnector.GetMockData();
            progress.Complete($"Mock data ready: {invoices.Count} invoice lines, {payments.Count} payments, {customers.Count} customers.");
        }
        else
        {
            bool runFull = forceFull ?? false;

            // In interactive mode, if scope was not pre-determined and no custom date range was provided:
            if (!isHeadless && forceFull == null && string.IsNullOrEmpty(fromTxnDate) && string.IsNullOrEmpty(toTxnDate))
            {
                if (!string.IsNullOrWhiteSpace(config.LastSyncDate))
                {
                    Console.ForegroundColor = ConsoleColor.Yellow;
                    Console.WriteLine(" Select Extraction Scope:");
                    Console.ResetColor();
                    Console.WriteLine($"   [1] Latest Changes Only (Modified since {config.LastSyncDate}) [Recommended - Fast]");
                    Console.WriteLine("   [2] Full Dataset (Download all historical records from the beginning) [Complete]");
                    Console.Write("\n Enter choice [1/2, default: 1]: ");
                    string? scopeChoice = Console.ReadLine()?.Trim();
                    if (scopeChoice == "2")
                    {
                        runFull = true;
                        PrintInfo("Extraction scope set to: FULL DATASET (all records will be retrieved).", isQuiet);
                    }
                    else
                    {
                        runFull = false;
                        PrintInfo($"Extraction scope set to: INCREMENTAL (records modified since {config.LastSyncDate}).", isQuiet);
                    }
                    Console.WriteLine();
                }
                else
                {
                    PrintInfo("No previous sync timestamp found. Performing FULL dataset extraction.\n", isQuiet);
                    runFull = true;
                }
            }

            var connector = new QuickBooksConnector();
            string? fromModifiedDate = (string.IsNullOrEmpty(fromTxnDate) && string.IsNullOrEmpty(toTxnDate))
                ? (runFull || string.IsNullOrWhiteSpace(config.LastSyncDate) ? null : config.LastSyncDate)
                : null; // If custom transaction date range is requested, bypass modified date filter

            ProgressIndicator? currentStep = null;
            void OnProgress(string stage, string? detail)
            {
                switch (stage)
                {
                    case "connect":
                        currentStep?.Dispose();
                        currentStep = new ProgressIndicator("Connecting to QuickBooks Desktop COM API...", isQuiet);
                        break;
                    case "session_ready":
                        currentStep?.Complete("Connected & QuickBooks read-only session established.");
                        currentStep = null;
                        break;
                    case "invoices_query":
                        currentStep?.Dispose();
                        currentStep = new ProgressIndicator("[1/3] Querying QuickBooks Invoices & line items (QBXML)...", isQuiet);
                        break;
                    case "invoices_parse":
                        currentStep?.Update("[1/3] Parsing invoice line items and serial numbers...");
                        break;
                    case "invoices_done":
                        currentStep?.Complete($"[1/3] Invoices: {detail}");
                        currentStep = null;
                        break;
                    case "payments_query":
                        currentStep?.Dispose();
                        currentStep = new ProgressIndicator("[2/3] Querying QuickBooks Received Payments (QBXML)...", isQuiet);
                        break;
                    case "payments_parse":
                        currentStep?.Update("[2/3] Parsing received payment records...");
                        break;
                    case "payments_done":
                        currentStep?.Complete($"[2/3] Payments: {detail}");
                        currentStep = null;
                        break;
                    case "customers_query":
                        currentStep?.Dispose();
                        currentStep = new ProgressIndicator("[3/3] Querying QuickBooks Customer Directory (QBXML)...", isQuiet);
                        break;
                    case "customers_parse":
                        currentStep?.Update("[3/3] Parsing customer directory and contact records...");
                        break;
                    case "customers_done":
                        currentStep?.Complete($"[3/3] Customers: {detail}");
                        currentStep = null;
                        break;
                }
            }

            var (invList, payList, custList, error) = connector.ExtractData(
                config.QbCompanyFile,
                fromModifiedDate,
                config.IncludeSerialNumbers,
                OnProgress,
                fromTxnDate,
                toTxnDate);

            currentStep?.Dispose();

            if (!string.IsNullOrEmpty(error))
            {
                string failMsg = $"Extraction failed: {error}";
                Logger.LogFailure(config.LogFile, failMsg);
                PrintError(failMsg, isQuiet);
                return 1;
            }

            invoices = invList;
            payments = payList;
            customers = custList;
        }

        if (!isQuiet)
        {
            Console.WriteLine();
            PrintSuccess($"Extraction complete: {invoices.Count} invoice line items, {payments.Count} payments, {customers.Count} customers.", isQuiet);
        }

        // SAVE LOCAL COPY (If configured, or if running Extract & Save Locally Only)
        if (config.SaveLocalCopy || saveLocalOnly)
        {
            string prefix = isMock 
                ? "mock_export" 
                : (!string.IsNullOrEmpty(fromTxnDate) ? "legacy_qb_export" : "qb_export");
            var export = DataExporter.SaveExport(invoices, payments, customers, config.ExportFolder, prefix);

            PrintSuccess($"Saved local copy for analysis to exports directory!", isQuiet);
            if (!isQuiet)
            {
                Console.ForegroundColor = ConsoleColor.Green;
                Console.WriteLine($"   📁 Folder     : {export.ExportDirectory}");
                Console.WriteLine($"   📄 Master JSON: {Path.GetFileName(export.JsonFile)}");
                Console.WriteLine($"   📊 CSV 1 (Inv): {Path.GetFileName(export.InvoiceCsvFile)} ({export.InvoiceCount} lines)");
                Console.WriteLine($"   📊 CSV 2 (Pay): {Path.GetFileName(export.PaymentCsvFile)} ({export.PaymentCount} records)");
                Console.WriteLine($"   📊 CSV 3 (Cust): {Path.GetFileName(export.CustomerCsvFile)} ({export.CustomerCount} profiles)");
                Console.ResetColor();
            }

            if (saveLocalOnly)
            {
                stopwatch.Stop();
                string localMsg = $"Local extraction completed. Extracted: {invoices.Count} Invoices, {payments.Count} Payments, {customers.Count} Customers. Duration: {stopwatch.Elapsed.TotalSeconds:F2}s.";
                Logger.LogSuccess(config.LogFile, localMsg);

                if (!isQuiet && !isHeadless && !Console.IsInputRedirected)
                {
                    Console.WriteLine();
                    Console.Write(" Would you like to open the exports folder in File Explorer now? (y/n) [y]: ");
                    string? openChoice = Console.ReadLine()?.Trim().ToLowerInvariant();
                    if (string.IsNullOrEmpty(openChoice) || openChoice == "y" || openChoice == "yes")
                    {
                        DataExporter.OpenFolderInExplorer(export.ExportDirectory);
                    }
                }
                return 0;
            }
        }

        if (isDryRun)
        {
            stopwatch.Stop();
            PrintInfo("[DRY RUN] Completed. No records were posted to the server.", isQuiet);
            Logger.LogSuccess(config.LogFile, $"[DRY RUN] Completed. Extracted: {invoices.Count} Invoices, {payments.Count} Payments, {customers.Count} Customers. Duration: {stopwatch.Elapsed.TotalSeconds:F2}s.");
            return 0;
        }

        if (invoices.Count == 0 && payments.Count == 0 && customers.Count == 0)
        {
            stopwatch.Stop();
            PrintInfo("No new or modified records found to sync.", isQuiet);
            Logger.LogSuccess(config.LogFile, $"Sync completed. 0 new records found since {config.LastSyncDate}. Duration: {stopwatch.Elapsed.TotalSeconds:F2}s.");
            return 0;
        }

        var payload = new SyncPayload
        {
            Source = isMock ? "qb_mock_sync" : "qb_desktop_sync",
            Timestamp = DateTime.UtcNow.ToString("o"),
            Invoices = invoices,
            Payments = payments,
            Customers = customers
        };

        using (var uploadProgress = new ProgressIndicator($"Uploading payload to {config.ServerUrl} ({invoices.Count} invoices, {payments.Count} payments, {customers.Count} customers)...", isQuiet))
        {
            var apiClient = new ApiClient();
            var (success, message, response) = await apiClient.PostSyncDataAsync(config.ServerUrl, config.ApiKey, payload);

            if (!success)
            {
                string failMsg = $"Upload failed: {message}";
                Logger.LogFailure(config.LogFile, failMsg);
                uploadProgress.Fail(failMsg);
                return 1;
            }

            uploadProgress.Complete($"Sync succeeded! Server response: {message}");
            stopwatch.Stop();

            string successSummary = $"Sync completed successfully. Extracted & Transferred: {invoices.Count} Invoices, {payments.Count} Payments, {customers.Count} Customers. Server: {response?.ImportedInvoices ?? invoices.Count} imported, {response?.SkippedInvoices ?? 0} skipped. Duration: {stopwatch.Elapsed.TotalSeconds:F2}s.";
            Logger.LogSuccess(config.LogFile, successSummary);

            if (response != null && !isQuiet)
            {
                Console.WriteLine($"   - Imported Invoices   : {response.ImportedInvoices}");
                Console.WriteLine($"   - Skipped (Duplicates): {response.SkippedInvoices}");
                Console.WriteLine($"   - Imported Payments   : {response.ImportedPayments}");
                Console.WriteLine($"   - Imported Customers  : {response.ImportedCustomers}");
                Console.WriteLine($"   - Server Timestamp    : {response.SyncTimestamp}");
            }
        }

        // Update local sync date (only for incremental / current syncs, not historical ranges)
        if (string.IsNullOrEmpty(fromTxnDate) && string.IsNullOrEmpty(toTxnDate))
        {
            config.LastSyncDate = DateTime.Now.ToString("yyyy-MM-ddTHH:mm:ss");
            ConfigManager.SaveConfig(config, configPath);
            PrintInfo($"Updated last_sync_date to {config.LastSyncDate}", isQuiet);
        }

        return 0;
    }

    private static void ExecuteTestQuickBooks(SyncConfig config)
    {
        using var progress = new ProgressIndicator("Connecting to QuickBooks Desktop COM API...", false);
        var connector = new QuickBooksConnector();
        var (success, message) = connector.TestConnection(config.QbCompanyFile);

        if (success)
        {
            progress.Complete($"[SUCCESS] {message}");
        }
        else
        {
            progress.Fail($"[FAILED] {message}");
            Console.WriteLine();
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine("Troubleshooting Tips:");
            if (message.Contains("0x80040154") || message.Contains("CLASSNOTREG") || message.Contains("not registered"))
            {
                Console.WriteLine(" 1. Architecture Mismatch: QuickBooks Desktop COM library (QBXMLRP2) is 32-bit (x86).");
                Console.WriteLine("    Make sure to run the 32-bit (win-x86) build of SalesBISync, not the 64-bit build.");
                Console.WriteLine(" 2. Re-register QuickBooks COM DLL: Open Command Prompt as Administrator and run:");
                Console.WriteLine(@"    regsvr32 ""C:\Program Files (x86)\Common Files\Intuit\QuickBooks\QBXMLRP2.dll""");
            }
            else
            {
                Console.WriteLine(" 1. Ensure QuickBooks Desktop is open on this computer with a company file open.");
                Console.WriteLine(" 2. If a QuickBooks authorization prompt popped up, select 'Yes, always allow access'.");
                Console.WriteLine(" 3. Check Edit > Preferences > Integrated Applications in QuickBooks.");
            }
            Console.ResetColor();
        }
    }

    private static async Task ExecuteTestApiAsync(SyncConfig config)
    {
        if (string.IsNullOrWhiteSpace(config.ServerUrl))
        {
            PrintError("Server URL is not configured. Go to option 8 to configure.", false);
            return;
        }

        using var progress = new ProgressIndicator($"Testing connection to {config.ServerUrl}...", false);
        var apiClient = new ApiClient();
        var (success, message) = await apiClient.TestConnectionAsync(config.ServerUrl, config.ApiKey);

        if (success)
        {
            progress.Complete($"[SUCCESS] Server responded: {message}");
        }
        else
        {
            progress.Fail($"[FAILED] {message}");
        }
    }

    private static void EditConfigInteractive(SyncConfig config, string? configPath)
    {
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine(" Edit Configuration (Press [Enter] to keep existing value):");
        Console.ResetColor();

        Console.Write($"Server URL [{config.ServerUrl}]: ");
        string? newUrl = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(newUrl)) config.ServerUrl = newUrl;

        Console.Write($"API Key [{(string.IsNullOrEmpty(config.ApiKey) ? "None" : MaskKey(config.ApiKey))}]: ");
        string? newKey = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(newKey)) config.ApiKey = newKey;

        Console.Write($"QuickBooks Company File Path (leave empty for currently open file) [{config.QbCompanyFile}]: ");
        string? newFile = Console.ReadLine()?.Trim();
        if (newFile != null) config.QbCompanyFile = newFile;

        Console.Write($"Require QuickBooks Running before Export? (y/n) [{(config.RequireQbRunning ? "y" : "n")}]: ");
        string? reqQb = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(reqQb))
        {
            config.RequireQbRunning = reqQb.ToLowerInvariant() == "y" || reqQb.ToLowerInvariant() == "yes";
        }

        Console.Write($"Log File Path [{config.LogFile}]: ");
        string? newLog = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(newLog)) config.LogFile = newLog;

        Console.Write($"Auto-Save Local Copy of Extracted Data for Analysis? (y/n) [{(config.SaveLocalCopy ? "y" : "n")}]: ");
        string? saveCopy = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(saveCopy))
        {
            config.SaveLocalCopy = saveCopy.ToLowerInvariant() == "y" || saveCopy.ToLowerInvariant() == "yes";
        }

        Console.Write($"Reset Last Sync Date? (y/n) [n]: ");
        string? resetDate = Console.ReadLine()?.Trim();
        if (resetDate?.ToLowerInvariant() == "y") config.LastSyncDate = "";

        if (ConfigManager.SaveConfig(config, configPath))
        {
            PrintSuccess("Configuration saved successfully!", false);
        }
    }

    private static void PrintBanner()
    {
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine(@"  ==================================================================");
        Console.WriteLine(@"       📊 Sales BI Dashboard - QuickBooks Desktop Sync Utility       ");
        Console.WriteLine(@"               100% Read-Only Automated Data Ingestion             ");
        Console.WriteLine(@"  ==================================================================");
        Console.ResetColor();
        Console.WriteLine();
    }

    private static string MaskKey(string key)
    {
        if (key.Length <= 8) return "********";
        return key[..4] + "..." + key[^4..];
    }

    private static void PrintInfo(string msg, bool quiet)
    {
        if (!quiet)
        {
            Console.ForegroundColor = ConsoleColor.White;
            Console.WriteLine($"[INFO] {msg}");
            Console.ResetColor();
        }
    }

    private static void PrintSuccess(string msg, bool quiet)
    {
        if (!quiet)
        {
            Console.ForegroundColor = ConsoleColor.Green;
            Console.WriteLine($"[SUCCESS] {msg}");
            Console.ResetColor();
        }
    }

    private static void PrintWarning(string msg, bool quiet = false)
    {
        if (!quiet)
        {
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine($"[WARNING] {msg}");
            Console.ResetColor();
        }
    }

    private static void PrintError(string msg, bool quiet)
    {
        if (!quiet)
        {
            Console.ForegroundColor = ConsoleColor.Red;
            Console.WriteLine($"[ERROR] {msg}");
            Console.ResetColor();
        }
    }
}

public class ProgressIndicator : IDisposable
{
    private readonly bool _isQuiet;
    private readonly Stopwatch _sw = Stopwatch.StartNew();
    private readonly CancellationTokenSource _cts = new();
    private readonly Task? _spinnerTask;
    private string _currentStatus;
    private bool _isCompleted;
    private static readonly object _consoleLock = new();

    private static readonly string[] SpinnerFrames = { "⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏" };

    public ProgressIndicator(string initialStatus, bool isQuiet = false)
    {
        _currentStatus = initialStatus;
        _isQuiet = isQuiet;

        if (!_isQuiet)
        {
            if (!Console.IsOutputRedirected && !Console.IsInputRedirected)
            {
                _spinnerTask = Task.Run(AnimateAsync);
            }
            else
            {
                Console.WriteLine($" -> {_currentStatus}...");
            }
        }
    }

    public void Update(string newStatus)
    {
        _currentStatus = newStatus;
        if (!_isQuiet && (Console.IsOutputRedirected || Console.IsInputRedirected))
        {
            Console.WriteLine($" -> {_currentStatus}...");
        }
    }

    private async Task AnimateAsync()
    {
        int frame = 0;
        try
        {
            while (!_cts.Token.IsCancellationRequested)
            {
                lock (_consoleLock)
                {
                    double secs = _sw.Elapsed.TotalSeconds;
                    Console.ForegroundColor = ConsoleColor.Cyan;
                    Console.Write($"\r   {SpinnerFrames[frame]} {_currentStatus} ({secs:F1}s)   ");
                    Console.ResetColor();
                }
                frame = (frame + 1) % SpinnerFrames.Length;
                await Task.Delay(80, _cts.Token);
            }
        }
        catch (OperationCanceledException) { }
    }

    public void Complete(string message)
    {
        if (_isCompleted) return;
        _isCompleted = true;
        _cts.Cancel();
        try { _spinnerTask?.Wait(250); } catch { }
        _sw.Stop();

        if (!_isQuiet)
        {
            lock (_consoleLock)
            {
                if (!Console.IsOutputRedirected && !Console.IsInputRedirected)
                {
                    int width = 80;
                    try { if (Console.WindowWidth > 0) width = Console.WindowWidth - 1; } catch { }
                    Console.Write("\r" + new string(' ', Math.Min(width, 100)) + "\r");
                }
                Console.ForegroundColor = ConsoleColor.Green;
                Console.WriteLine($"   [✔] {message} ({_sw.Elapsed.TotalSeconds:F1}s)");
                Console.ResetColor();
            }
        }
    }

    public void Fail(string errorMessage)
    {
        if (_isCompleted) return;
        _isCompleted = true;
        _cts.Cancel();
        try { _spinnerTask?.Wait(250); } catch { }
        _sw.Stop();

        if (!_isQuiet)
        {
            lock (_consoleLock)
            {
                if (!Console.IsOutputRedirected && !Console.IsInputRedirected)
                {
                    int width = 80;
                    try { if (Console.WindowWidth > 0) width = Console.WindowWidth - 1; } catch { }
                    Console.Write("\r" + new string(' ', Math.Min(width, 100)) + "\r");
                }
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine($"   [✖] {errorMessage} ({_sw.Elapsed.TotalSeconds:F1}s)");
                Console.ResetColor();
            }
        }
    }

    public void Dispose()
    {
        if (!_isCompleted)
        {
            _cts.Cancel();
            try { _spinnerTask?.Wait(100); } catch { }
            _sw.Stop();
        }
    }
}
