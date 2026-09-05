using System.Text.Json;

namespace SalesBISync;

public static class ConfigManager
{
    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        WriteIndented = true
    };

    public static string GetConfigPath(string? customPath = null)
    {
        if (!string.IsNullOrWhiteSpace(customPath) && File.Exists(customPath))
        {
            return Path.GetFullPath(customPath);
        }

        string exeDir = AppDomain.CurrentDomain.BaseDirectory;
        return Path.Combine(exeDir, "config.json");
    }

    public static SyncConfig LoadConfig(string? customPath = null)
    {
        string path = GetConfigPath(customPath);

        if (!File.Exists(path))
        {
            var defaultConfig = new SyncConfig();
            SaveConfig(defaultConfig, path);
            return defaultConfig;
        }

        try
        {
            string json = File.ReadAllText(path);
            var config = JsonSerializer.Deserialize<SyncConfig>(json);
            return config ?? new SyncConfig();
        }
        catch (Exception ex)
        {
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine($"[Warning] Failed to read config.json: {ex.Message}. Using defaults.");
            Console.ResetColor();
            return new SyncConfig();
        }
    }

    public static bool SaveConfig(SyncConfig config, string? customPath = null)
    {
        string path = GetConfigPath(customPath);

        try
        {
            string json = JsonSerializer.Serialize(config, JsonOptions);
            File.WriteAllText(path, json);
            return true;
        }
        catch (Exception ex)
        {
            Console.ForegroundColor = ConsoleColor.Red;
            Console.WriteLine($"[Error] Could not save config to {path}: {ex.Message}");
            Console.ResetColor();
            return false;
        }
    }
}
