namespace SalesBISync;

class Program
{
    static async Task<int> Main(string[] args)
    {
        Console.OutputEncoding = System.Text.Encoding.UTF8;

        // Parse command line arguments
        bool isSync = args.Contains("--sync") || args.Contains("-s");
        bool isQuiet = args.Contains("--quiet") || args.Contains("-q");
        bool isDryRun = args.Contains("--dry-run");
        bool isMock = args.Contains("--mock");
        string? customConfig = null;

        for (int i = 0; i < args.Length; i++)
        {
            if (args[i] == "--config" && i + 1 < args.Length)
            {
                customConfig = args[i + 1];
            }
        }

        var config = ConfigManager.LoadConfig(customConfig);

        // Headless execution (Task Scheduler or command line)
        if (isSync || isQuiet || isDryRun || isMock)
        {
            return await RunHeadlessSyncAsync(config, isQuiet, isDryRun, isMock, customConfig);
        }

        // Interactive Console Menu
        await RunInteractiveMenuAsync(config, customConfig);
        return 0;
    }

    private static async Task RunInteractiveMenuAsync(SyncConfig config, string? configPath)
    {
        while (true)
        {
            Console.Clear();
            PrintBanner();

            Console.WriteLine(" Current Configuration:");
            Console.WriteLine($"   Server URL     : {config.ServerUrl}");
            Console.WriteLine($"   API Key        : {(string.IsNullOrEmpty(config.ApiKey) ? "[NOT CONFIGURED]" : MaskKey(config.ApiKey))}");
            Console.WriteLine($"   Last Sync      : {(string.IsNullOrEmpty(config.LastSyncDate) ? "Never" : config.LastSyncDate)}");
            Console.WriteLine($"   Serial Numbers : {(config.IncludeSerialNumbers ? "Enabled (Extracted in description)" : "Disabled")}");
            Console.WriteLine($"   Config File    : {ConfigManager.GetConfigPath(configPath)}");
            Console.WriteLine(new string('-', 72));
            Console.WriteLine();

            Console.WriteLine(" Select an Action:");
            Console.WriteLine("   [1] Run Sync Now (QuickBooks -> Web Dashboard)");
            Console.WriteLine("   [2] Test QuickBooks Desktop Connection (Read-Only)");
            Console.WriteLine("   [3] Test Web Dashboard API Connection");
            Console.WriteLine("   [4] Run Mock Sync (Upload sample invoice with serial numbers)");
            Console.WriteLine("   [5] Edit Configuration");
            Console.WriteLine("   [6] Exit");
            Console.WriteLine();
            Console.Write(" Enter your choice [1-6]: ");

            string? choice = Console.ReadLine()?.Trim();
            Console.WriteLine();

            switch (choice)
            {
                case "1":
                    await ExecuteSyncAsync(config, isMock: false, isDryRun: false, configPath);
                    break;
                case "2":
                    ExecuteTestQuickBooks(config);
                    break;
                case "3":
                    await ExecuteTestApiAsync(config);
                    break;
                case "4":
                    await ExecuteSyncAsync(config, isMock: true, isDryRun: false, configPath);
                    break;
                case "5":
                    EditConfigInteractive(config, configPath);
                    break;
                case "6":
                    Console.WriteLine("Exiting. Have a great day!");
                    return;
                default:
                    Console.ForegroundColor = ConsoleColor.Yellow;
                    Console.WriteLine("Invalid selection. Press any key to continue...");
                    Console.ResetColor();
                    break;
            }

            Console.WriteLine();
            Console.Write("Press [Enter] to return to the main menu...");
            Console.ReadLine();
        }
    }

    private static async Task<int> RunHeadlessSyncAsync(
        SyncConfig config,
        bool isQuiet,
        bool isDryRun,
        bool isMock,
        string? configPath)
    {
        if (!isQuiet)
        {
            Console.WriteLine($"[INFO] Starting {(isMock ? "Mock " : "")}Sync: {DateTime.Now}");
        }

        var result = await ExecuteSyncAsync(config, isMock, isDryRun, configPath, isQuiet);
        return result ? 0 : 1;
    }

    private static async Task<bool> ExecuteSyncAsync(
        SyncConfig config,
        bool isMock,
        bool isDryRun,
        string? configPath,
        bool isQuiet = false)
    {
        if (string.IsNullOrWhiteSpace(config.ServerUrl))
        {
            PrintError("Server URL is missing in config.json.", isQuiet);
            return false;
        }

        if (string.IsNullOrWhiteSpace(config.ApiKey))
        {
            PrintError("API Key is missing in config.json.", isQuiet);
            return false;
        }

        PrintInfo("Extracting data from QuickBooks Desktop in read-only mode...", isQuiet);

        List<InvoiceRecord> invoices;
        List<PaymentRecord> payments;

        if (isMock)
        {
            PrintInfo("Generating mock data (including serial numbers)...", isQuiet);
            (invoices, payments) = QuickBooksConnector.GetMockData();
        }
        else
        {
            var connector = new QuickBooksConnector();
            string? fromDate = string.IsNullOrWhiteSpace(config.LastSyncDate) ? null : config.LastSyncDate;

            var (invList, payList, error) = connector.ExtractData(
                config.QbCompanyFile,
                fromDate,
                config.IncludeSerialNumbers);

            if (!string.IsNullOrEmpty(error))
            {
                PrintError($"Extraction failed: {error}", isQuiet);
                return false;
            }

            invoices = invList;
            payments = payList;
        }

        PrintSuccess($"Extracted {invoices.Count} invoice line items and {payments.Count} payment records.", isQuiet);

        // Display sample extracted serial numbers if available
        if (!isQuiet)
        {
            foreach (var inv in invoices.Take(3))
            {
                Console.ForegroundColor = ConsoleColor.Cyan;
                Console.WriteLine($"   * {inv.Num} | {inv.Name} | {inv.Item}");
                Console.WriteLine($"     Desc: {inv.Description}");
                Console.ResetColor();
            }
            if (invoices.Count > 3)
            {
                Console.WriteLine($"   ... and {invoices.Count - 3} more items.");
            }
        }

        if (isDryRun)
        {
            PrintInfo("[DRY RUN] Completed. No records were posted to the server.", isQuiet);
            return true;
        }

        if (invoices.Count == 0 && payments.Count == 0)
        {
            PrintInfo("No new or modified records found since last sync.", isQuiet);
            return true;
        }

        PrintInfo($"Uploading payload to {config.ServerUrl}...", isQuiet);

        var payload = new SyncPayload
        {
            Source = isMock ? "qb_mock_sync" : "qb_desktop_sync",
            Timestamp = DateTime.UtcNow.ToString("o"),
            Invoices = invoices,
            Payments = payments
        };

        var apiClient = new ApiClient();
        var (success, message, response) = await apiClient.PostSyncDataAsync(config.ServerUrl, config.ApiKey, payload);

        if (!success)
        {
            PrintError($"Upload failed: {message}", isQuiet);
            return false;
        }

        PrintSuccess($"Sync succeeded! Server response: {message}", isQuiet);
        if (response != null && !isQuiet)
        {
            Console.WriteLine($"   - Imported Invoices : {response.ImportedInvoices}");
            Console.WriteLine($"   - Skipped (Duplicates): {response.SkippedInvoices}");
            Console.WriteLine($"   - Imported Payments : {response.ImportedPayments}");
            Console.WriteLine($"   - Server Timestamp  : {response.SyncTimestamp}");
        }

        // Update local sync date
        config.LastSyncDate = DateTime.Now.ToString("yyyy-MM-ddTHH:mm:ss");
        ConfigManager.SaveConfig(config, configPath);
        PrintInfo($"Updated last_sync_date to {config.LastSyncDate}", isQuiet);

        return true;
    }

    private static void ExecuteTestQuickBooks(SyncConfig config)
    {
        Console.WriteLine("Connecting to QuickBooks Desktop COM API...");
        var connector = new QuickBooksConnector();
        var (success, message) = connector.TestConnection(config.QbCompanyFile);

        if (success)
        {
            PrintSuccess($"[SUCCESS] {message}", false);
        }
        else
        {
            PrintError($"[FAILED] {message}", false);
            Console.WriteLine();
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine("Troubleshooting Tips:");
            Console.WriteLine(" 1. Ensure QuickBooks Desktop is open on this computer with a company file open.");
            Console.WriteLine(" 2. If a QuickBooks authorization prompt popped up, select 'Yes, always allow access'.");
            Console.WriteLine(" 3. Check Edit > Preferences > Integrated Applications in QuickBooks.");
            Console.ResetColor();
        }
    }

    private static async Task ExecuteTestApiAsync(SyncConfig config)
    {
        if (string.IsNullOrWhiteSpace(config.ServerUrl) || string.IsNullOrWhiteSpace(config.ApiKey))
        {
            PrintError("Server URL and API Key must both be configured in config.json.", false);
            return;
        }

        Console.WriteLine($"Testing connection to {config.ServerUrl} with API Key...");
        var apiClient = new ApiClient();
        var (success, message) = await apiClient.TestApiConnectivityAsync(config.ServerUrl, config.ApiKey);

        if (success)
        {
            PrintSuccess($"[SUCCESS] API endpoint is active and authenticated: {message}", false);
        }
        else
        {
            PrintError($"[FAILED] {message}", false);
        }
    }

    private static void EditConfigInteractive(SyncConfig config, string? configPath)
    {
        Console.WriteLine("Edit Configuration (Press Enter to keep current value):");

        Console.Write($"Server URL [{config.ServerUrl}]: ");
        string? newUrl = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(newUrl)) config.ServerUrl = newUrl;

        Console.Write($"API Key [{(string.IsNullOrEmpty(config.ApiKey) ? "None" : MaskKey(config.ApiKey))}]: ");
        string? newKey = Console.ReadLine()?.Trim();
        if (!string.IsNullOrEmpty(newKey)) config.ApiKey = newKey;

        Console.Write($"QuickBooks Company File Path (leave empty for currently open file) [{config.QbCompanyFile}]: ");
        string? newFile = Console.ReadLine()?.Trim();
        if (newFile != null) config.QbCompanyFile = newFile;

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
