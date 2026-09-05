using System.Text.Json.Serialization;

namespace SalesBISync;

public class SyncConfig
{
    [JsonPropertyName("server_url")]
    public string ServerUrl { get; set; } = "http://127.0.0.1:8999/api/sync.php";

    [JsonPropertyName("api_key")]
    public string ApiKey { get; set; } = "";

    [JsonPropertyName("last_sync_date")]
    public string LastSyncDate { get; set; } = "";

    [JsonPropertyName("qb_company_file")]
    public string QbCompanyFile { get; set; } = "";

    [JsonPropertyName("batch_size")]
    public int BatchSize { get; set; } = 500;

    [JsonPropertyName("include_serial_numbers")]
    public bool IncludeSerialNumbers { get; set; } = true;
}

public class InvoiceRecord
{
    [JsonPropertyName("Type")]
    public string Type { get; set; } = "Invoice";

    [JsonPropertyName("Date")]
    public string Date { get; set; } = "";

    [JsonPropertyName("Num")]
    public string Num { get; set; } = "";

    [JsonPropertyName("Name")]
    public string Name { get; set; } = "";

    [JsonPropertyName("Item")]
    public string Item { get; set; } = "";

    [JsonPropertyName("Description")]
    public string Description { get; set; } = "";

    [JsonPropertyName("Sales Tax Code")]
    public string SalesTaxCode { get; set; } = "Taxable Sales";

    [JsonPropertyName("Qty")]
    public decimal Qty { get; set; } = 1;

    [JsonPropertyName("Amount")]
    public decimal Amount { get; set; } = 0;

    [JsonPropertyName("Product Category")]
    public string ProductCategory { get; set; } = "";

    [JsonPropertyName("Rep")]
    public string Rep { get; set; } = "";

    [JsonPropertyName("PONumber")]
    public string PONumber { get; set; } = "";

    [JsonPropertyName("Memo")]
    public string Memo { get; set; } = "";

    [JsonPropertyName("QBTxnID")]
    public string QBTxnID { get; set; } = "";
}

public class PaymentRecord
{
    [JsonPropertyName("customer_name")]
    public string CustomerName { get; set; } = "";

    [JsonPropertyName("invoice_num")]
    public string InvoiceNum { get; set; } = "";

    [JsonPropertyName("payment_date")]
    public string PaymentDate { get; set; } = "";

    [JsonPropertyName("reference_num")]
    public string ReferenceNum { get; set; } = "";

    [JsonPropertyName("amount")]
    public decimal Amount { get; set; } = 0;
}

public class SyncPayload
{
    [JsonPropertyName("source")]
    public string Source { get; set; } = "qb_desktop_sync";

    [JsonPropertyName("timestamp")]
    public string Timestamp { get; set; } = DateTime.UtcNow.ToString("o");

    [JsonPropertyName("invoices")]
    public List<InvoiceRecord> Invoices { get; set; } = new();

    [JsonPropertyName("payments")]
    public List<PaymentRecord> Payments { get; set; } = new();
}

public class SyncResponse
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("message")]
    public string Message { get; set; } = "";

    [JsonPropertyName("imported_invoices")]
    public int ImportedInvoices { get; set; }

    [JsonPropertyName("skipped_invoices")]
    public int SkippedInvoices { get; set; }

    [JsonPropertyName("imported_payments")]
    public int ImportedPayments { get; set; }

    [JsonPropertyName("sync_timestamp")]
    public string SyncTimestamp { get; set; } = "";
}
