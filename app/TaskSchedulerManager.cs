using System.Diagnostics;

namespace SalesBISync;

public static class TaskSchedulerManager
{
    public const string TaskName = "SalesBI_QuickBooks_Sync";

    public static string GetExecutablePath()
    {
        string? exe = Environment.ProcessPath;
        if (!string.IsNullOrEmpty(exe) && File.Exists(exe))
        {
            return Path.GetFullPath(exe);
        }

        string baseDir = AppDomain.CurrentDomain.BaseDirectory;
        return Path.Combine(baseDir, "SalesBISync.exe");
    }

    public static (bool Success, string Message) InstallTask(int intervalMinutes, string? customConfig = null)
    {
        string exePath = GetExecutablePath();
        if (!File.Exists(exePath))
        {
            return (false, $"Application executable not found at: {exePath}");
        }

        string args = "--sync --quiet";
        if (!string.IsNullOrWhiteSpace(customConfig))
        {
            args += $" --config \"{customConfig}\"";
        }

        string taskRun = $"\\\"{exePath}\\\" {args}";

        // Build schtasks arguments based on interval
        string schArgs;
        string frequencyDesc;

        if (intervalMinutes <= 0)
        {
            intervalMinutes = 60;
        }

        if (intervalMinutes == 1440) // Daily
        {
            schArgs = $"/create /tn \"{TaskName}\" /tr \"{taskRun}\" /sc daily /st 08:30 /f";
            frequencyDesc = "Once Daily at 08:30 AM";
        }
        else if (intervalMinutes >= 60 && intervalMinutes % 60 == 0 && intervalMinutes / 60 <= 24)
        {
            int hours = intervalMinutes / 60;
            schArgs = $"/create /tn \"{TaskName}\" /tr \"{taskRun}\" /sc hourly /mo {hours} /f";
            frequencyDesc = hours == 1 ? "Every 1 Hour" : $"Every {hours} Hours";
        }
        else
        {
            schArgs = $"/create /tn \"{TaskName}\" /tr \"{taskRun}\" /sc minute /mo {intervalMinutes} /f";
            frequencyDesc = $"Every {intervalMinutes} Minutes";
        }

        var (exitCode, stdout, stderr) = RunSchtasks(schArgs);

        if (exitCode == 0)
        {
            return (true, $"Scheduled task '{TaskName}' registered successfully! Running {frequencyDesc}.");
        }
        else
        {
            string err = !string.IsNullOrWhiteSpace(stderr) ? stderr : stdout;
            return (false, $"Failed to create scheduled task (exit code {exitCode}): {err.Trim()}");
        }
    }

    public static (bool Success, string Message) UninstallTask()
    {
        var (exitCode, stdout, stderr) = RunSchtasks($"/delete /tn \"{TaskName}\" /f");
        if (exitCode == 0)
        {
            return (true, $"Scheduled task '{TaskName}' has been successfully removed.");
        }
        else
        {
            if (stdout.Contains("cannot find") || stderr.Contains("cannot find") || stdout.Contains("ERROR: The system cannot find the file specified"))
            {
                return (true, $"No scheduled task named '{TaskName}' is currently registered.");
            }
            string err = !string.IsNullOrWhiteSpace(stderr) ? stderr : stdout;
            return (false, $"Failed to remove scheduled task: {err.Trim()}");
        }
    }

    public static (bool IsInstalled, string StatusSummary, string RawDetails) GetTaskStatus()
    {
        var (exitCode, stdout, _) = RunSchtasks($"/query /tn \"{TaskName}\" /fo list /v");

        if (exitCode != 0 || stdout.Contains("ERROR: The system cannot find the file specified") || stdout.Contains("cannot find"))
        {
            return (false, "Not Scheduled (Manual execution only)", "Task is not installed in Windows Task Scheduler.");
        }

        // Parse key fields from /fo list
        string status = "Active";
        string nextRun = "";
        string lastRun = "";
        string schedule = "";

        var lines = stdout.Split(new[] { '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries);
        foreach (var line in lines)
        {
            int colonIdx = line.IndexOf(':');
            if (colonIdx < 0) continue;

            string key = line.Substring(0, colonIdx).Trim().ToLowerInvariant();
            string val = line.Substring(colonIdx + 1).Trim();

            if (key == "status") status = val;
            else if (key == "next run time") nextRun = val;
            else if (key == "last run time") lastRun = val;
            else if (key == "schedule type" || key == "recurrence") schedule += val + " ";
        }

        string summary = $"Active ({status})";
        if (!string.IsNullOrEmpty(nextRun) && nextRun != "N/A")
        {
            summary += $" | Next Run: {nextRun}";
        }
        if (!string.IsNullOrEmpty(lastRun) && lastRun != "N/A")
        {
            summary += $" | Last Run: {lastRun}";
        }

        return (true, summary, stdout.Trim());
    }

    private static (int ExitCode, string StdOut, string StdErr) RunSchtasks(string arguments)
    {
        try
        {
            var psi = new ProcessStartInfo
            {
                FileName = "schtasks.exe",
                Arguments = arguments,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };

            using var proc = Process.Start(psi);
            if (proc == null)
            {
                return (-1, "", "Failed to start schtasks.exe process.");
            }

            string stdout = proc.StandardOutput.ReadToEnd();
            string stderr = proc.StandardError.ReadToEnd();
            proc.WaitForExit(10000);

            return (proc.ExitCode, stdout, stderr);
        }
        catch (Exception ex)
        {
            return (-1, "", ex.Message);
        }
    }
}
