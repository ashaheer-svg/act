using System.Text;

namespace SalesBISync;

public static class Logger
{
    private static readonly object _lock = new();

    public static void Log(string logFilePath, string status, string message)
    {
        try
        {
            string resolvedPath = ResolvePath(logFilePath);
            string? dir = Path.GetDirectoryName(resolvedPath);
            if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
            {
                Directory.CreateDirectory(dir);
            }

            string timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            string entry = $"[{timestamp}] [{status.ToUpperInvariant()}] {message}{Environment.NewLine}";

            lock (_lock)
            {
                File.AppendAllText(resolvedPath, entry, Encoding.UTF8);
            }
        }
        catch (Exception ex)
        {
            // Fallback console warning if file logging fails
            try
            {
                Console.ForegroundColor = ConsoleColor.Yellow;
                Console.WriteLine($"[Warning] Failed to write to log file: {ex.Message}");
                Console.ResetColor();
            }
            catch
            {
                // Ignore console errors if headless
            }
        }
    }

    public static void LogSuccess(string logFilePath, string message)
    {
        Log(logFilePath, "SUCCESS", message);
    }

    public static void LogSkip(string logFilePath, string message)
    {
        Log(logFilePath, "SKIPPED", message);
    }

    public static void LogFailure(string logFilePath, string message)
    {
        Log(logFilePath, "FAILURE", message);
    }

    public static string ResolvePath(string logFilePath)
    {
        if (Path.IsPathRooted(logFilePath))
        {
            return logFilePath;
        }

        string baseDir = AppDomain.CurrentDomain.BaseDirectory;
        return Path.Combine(baseDir, logFilePath);
    }

    public static string[] ReadRecentEntries(string logFilePath, int maxLines = 15)
    {
        try
        {
            string resolved = ResolvePath(logFilePath);
            if (!File.Exists(resolved))
            {
                return Array.Empty<string>();
            }

            lock (_lock)
            {
                var allLines = File.ReadAllLines(resolved, Encoding.UTF8);
                return allLines.TakeLast(maxLines).ToArray();
            }
        }
        catch
        {
            return Array.Empty<string>();
        }
    }
}
