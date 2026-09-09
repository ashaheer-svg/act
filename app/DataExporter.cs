using System.Diagnostics;
using System.Text;
using System.Text.Json;

namespace SalesBISync;

public static class DataExporter
{
    private static readonly JsonSerializerOptions IndentedJson = new()
    {
        WriteIndented = true,
        Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping
    };

    public class ExportResult
    {
        public string ExportDirectory { get; set; } = "";
        public string JsonFile { get; set; } = "";
        public string? InvoiceCsvFile { get; set; }
        public string? PaymentCsvFile { get; set; }
        public string? CustomerCsvFile { get; set; }
        public int InvoiceCount { get; set; }
        public int PaymentCount { get; set; }
        public int CustomerCount { get; set; }
    }

    /// <summary>
    /// Saves the extracted invoices, payments, and customers to local JSON and CSV files for analysis.
    /// </summary>
    public static ExportResult SaveExport(
        List<InvoiceRecord> invoices,
        List<PaymentRecord> payments,
        List<CustomerRecord>? customers = null,
        string? customDirectory = null,
        string prefix = "qb_data")
    {
        customers ??= new List<CustomerRecord>();

        string baseDir = AppDomain.CurrentDomain.BaseDirectory;
        string exportDir = !string.IsNullOrWhiteSpace(customDirectory)
            ? (Path.IsPathRooted(customDirectory) ? customDirectory : Path.Combine(baseDir, customDirectory))
            : Path.Combine(baseDir, "exports");

        if (!Directory.Exists(exportDir))
        {
            Directory.CreateDirectory(exportDir);
        }

        string timestamp = DateTime.Now.ToString("yyyy-MM-dd_HHmmss");
        string jsonPath = Path.Combine(exportDir, $"{prefix}_{timestamp}.json");
        string invoiceCsvPath = Path.Combine(exportDir, $"{prefix}_invoices_{timestamp}.csv");
        string paymentCsvPath = Path.Combine(exportDir, $"{prefix}_payments_{timestamp}.csv");
        string customerCsvPath = Path.Combine(exportDir, $"{prefix}_customers_{timestamp}.csv");

        // 1. Write Comprehensive JSON
        var exportPayload = new
        {
            export_timestamp = DateTime.UtcNow.ToString("o"),
            local_time = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
            total_invoices = invoices.Count,
            total_payments = payments.Count,
            total_customers = customers.Count,
            invoices = invoices,
            payments = payments,
            customers = customers
        };

        string jsonContent = JsonSerializer.Serialize(exportPayload, IndentedJson);
        File.WriteAllText(jsonPath, jsonContent, Encoding.UTF8);

        // 2. Write Invoices CSV
        var invCsv = new StringBuilder();
        invCsv.AppendLine("Type,Date,Num,Name,Item,Description,Sales Tax Code,Qty,Amount,Product Category,Rep,PONumber,Memo,QBTxnID,Subtotal,SalesTaxTotal,SalesTaxRate,SalesTaxItem,CustomerTaxCode,AppliedAmount,BalanceRemaining,IsPaid,DueDate,ShipDate,Terms,UnitPrice");
        foreach (var inv in invoices)
        {
            invCsv.AppendLine(string.Join(",",
                EscapeCsv(inv.Type),
                EscapeCsv(inv.Date),
                EscapeCsv(inv.Num),
                EscapeCsv(inv.Name),
                EscapeCsv(inv.Item),
                EscapeCsv(inv.Description),
                EscapeCsv(inv.SalesTaxCode),
                inv.Qty.ToString(System.Globalization.CultureInfo.InvariantCulture),
                inv.Amount.ToString(System.Globalization.CultureInfo.InvariantCulture),
                EscapeCsv(inv.ProductCategory),
                EscapeCsv(inv.Rep),
                EscapeCsv(inv.PONumber),
                EscapeCsv(inv.Memo),
                EscapeCsv(inv.QBTxnID),
                inv.Subtotal.ToString(System.Globalization.CultureInfo.InvariantCulture),
                inv.SalesTaxTotal.ToString(System.Globalization.CultureInfo.InvariantCulture),
                inv.SalesTaxRate.ToString(System.Globalization.CultureInfo.InvariantCulture),
                EscapeCsv(inv.SalesTaxItem),
                EscapeCsv(inv.CustomerTaxCode),
                inv.AppliedAmount.ToString(System.Globalization.CultureInfo.InvariantCulture),
                inv.BalanceRemaining.ToString(System.Globalization.CultureInfo.InvariantCulture),
                inv.IsPaid ? "1" : "0",
                EscapeCsv(inv.DueDate),
                EscapeCsv(inv.ShipDate),
                EscapeCsv(inv.Terms),
                inv.UnitPrice.ToString(System.Globalization.CultureInfo.InvariantCulture)
            ));
        }
        File.WriteAllText(invoiceCsvPath, invCsv.ToString(), Encoding.UTF8);

        // 3. Write Payments CSV
        var payCsv = new StringBuilder();
        payCsv.AppendLine("CustomerName,InvoiceNum,PaymentDate,ReferenceNum,Amount,PaymentMethod,DepositToAccount,Memo,UnusedPayment");
        foreach (var pay in payments)
        {
            payCsv.AppendLine(string.Join(",",
                EscapeCsv(pay.CustomerName),
                EscapeCsv(pay.InvoiceNum),
                EscapeCsv(pay.PaymentDate),
                EscapeCsv(pay.ReferenceNum),
                pay.Amount.ToString(System.Globalization.CultureInfo.InvariantCulture),
                EscapeCsv(pay.PaymentMethod),
                EscapeCsv(pay.DepositToAccount),
                EscapeCsv(pay.Memo),
                pay.UnusedPayment.ToString(System.Globalization.CultureInfo.InvariantCulture)
            ));
        }
        File.WriteAllText(paymentCsvPath, payCsv.ToString(), Encoding.UTF8);

        // 4. Write Customers CSV
        var custCsv = new StringBuilder();
        custCsv.AppendLine("ListID,Name,FullName,CompanyName,ContactName,Email,Phone,AltPhone,Fax,BillAddress,BillCity,BillState,BillZip,BillCountry,ShipAddress,ShipCity,ShipState,ShipZip,ShipCountry,CustomerType,Terms,SalesRep,Balance,TotalBalance,CreditLimit,AccountNumber,ResaleNumber,VatNumber,TinNumber,IsVatRegistered,TaxItemRef,TaxCodeRef,IsActive,Notes");
        foreach (var cust in customers)
        {
            custCsv.AppendLine(string.Join(",",
                EscapeCsv(cust.ListID),
                EscapeCsv(cust.Name),
                EscapeCsv(cust.FullName),
                EscapeCsv(cust.CompanyName),
                EscapeCsv(cust.ContactName),
                EscapeCsv(cust.Email),
                EscapeCsv(cust.Phone),
                EscapeCsv(cust.AltPhone),
                EscapeCsv(cust.Fax),
                EscapeCsv(cust.BillAddress),
                EscapeCsv(cust.BillCity),
                EscapeCsv(cust.BillState),
                EscapeCsv(cust.BillZip),
                EscapeCsv(cust.BillCountry),
                EscapeCsv(cust.ShipAddress),
                EscapeCsv(cust.ShipCity),
                EscapeCsv(cust.ShipState),
                EscapeCsv(cust.ShipZip),
                EscapeCsv(cust.ShipCountry),
                EscapeCsv(cust.CustomerType),
                EscapeCsv(cust.Terms),
                EscapeCsv(cust.SalesRep),
                cust.Balance.ToString(System.Globalization.CultureInfo.InvariantCulture),
                cust.TotalBalance.ToString(System.Globalization.CultureInfo.InvariantCulture),
                cust.CreditLimit.ToString(System.Globalization.CultureInfo.InvariantCulture),
                EscapeCsv(cust.AccountNumber),
                EscapeCsv(cust.ResaleNumber),
                EscapeCsv(cust.VatNumber),
                EscapeCsv(cust.TinNumber),
                cust.IsVatRegistered ? "1" : "0",
                EscapeCsv(cust.TaxItemRef),
                EscapeCsv(cust.TaxCodeRef),
                cust.IsActive ? "1" : "0",
                EscapeCsv(cust.Notes)
            ));
        }
        File.WriteAllText(customerCsvPath, custCsv.ToString(), Encoding.UTF8);

        return new ExportResult
        {
            ExportDirectory = exportDir,
            JsonFile = jsonPath,
            InvoiceCsvFile = invoiceCsvPath,
            PaymentCsvFile = paymentCsvPath,
            CustomerCsvFile = customerCsvPath,
            InvoiceCount = invoices.Count,
            PaymentCount = payments.Count,
            CustomerCount = customers.Count
        };
    }

    public static bool OpenFolderInExplorer(string folderPath)
    {
        try
        {
            if (!Directory.Exists(folderPath))
            {
                Directory.CreateDirectory(folderPath);
            }

            Process.Start(new ProcessStartInfo
            {
                FileName = "explorer.exe",
                Arguments = $"\"{folderPath}\"",
                UseShellExecute = true
            });
            return true;
        }
        catch
        {
            return false;
        }
    }

    private static string EscapeCsv(string? value)
    {
        if (string.IsNullOrEmpty(value)) return "";
        if (value.Contains(',') || value.Contains('"') || value.Contains('\n') || value.Contains('\r'))
        {
            return $"\"{value.Replace("\"", "\"\"")}\"";
        }
        return value;
    }
}
