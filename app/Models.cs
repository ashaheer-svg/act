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

    [JsonPropertyName("save_local_copy")]
    public bool SaveLocalCopy { get; set; } = true;

    [JsonPropertyName("export_folder")]
    public string ExportFolder { get; set; } = "exports";

    [JsonPropertyName("log_file")]
    public string LogFile { get; set; } = "logs/sync_log.txt";

    [JsonPropertyName("require_qb_running")]
    public bool RequireQbRunning { get; set; } = true;

    [JsonPropertyName("sync_interval_minutes")]
    public int SyncIntervalMinutes { get; set; } = 60;
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

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; } = 0;

    [JsonPropertyName("sales_tax_total")]
    public decimal SalesTaxTotal { get; set; } = 0;

    [JsonPropertyName("sales_tax_rate")]
    public decimal SalesTaxRate { get; set; } = 0;

    [JsonPropertyName("sales_tax_item")]
    public string SalesTaxItem { get; set; } = "";

    [JsonPropertyName("customer_tax_code")]
    public string CustomerTaxCode { get; set; } = "";

    [JsonPropertyName("applied_amount")]
    public decimal AppliedAmount { get; set; } = 0;

    [JsonPropertyName("balance_remaining")]
    public decimal BalanceRemaining { get; set; } = 0;

    [JsonPropertyName("is_paid")]
    public bool IsPaid { get; set; } = false;

    [JsonPropertyName("is_pending")]
    public bool IsPending { get; set; } = false;

    [JsonPropertyName("due_date")]
    public string DueDate { get; set; } = "";

    [JsonPropertyName("ship_date")]
    public string ShipDate { get; set; } = "";

    [JsonPropertyName("terms")]
    public string Terms { get; set; } = "";

    [JsonPropertyName("unit_price")]
    public decimal UnitPrice { get; set; } = 0;
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

    [JsonPropertyName("payment_method")]
    public string PaymentMethod { get; set; } = "";

    [JsonPropertyName("deposit_to_account")]
    public string DepositToAccount { get; set; } = "";

    [JsonPropertyName("memo")]
    public string Memo { get; set; } = "";

    [JsonPropertyName("unused_payment")]
    public decimal UnusedPayment { get; set; } = 0;
}

public class CustomerRecord
{
    [JsonPropertyName("list_id")]
    public string ListID { get; set; } = "";

    [JsonPropertyName("name")]
    public string Name { get; set; } = "";

    [JsonPropertyName("full_name")]
    public string FullName { get; set; } = "";

    [JsonPropertyName("company_name")]
    public string CompanyName { get; set; } = "";

    [JsonPropertyName("contact_name")]
    public string ContactName { get; set; } = "";

    [JsonPropertyName("first_name")]
    public string FirstName { get; set; } = "";

    [JsonPropertyName("last_name")]
    public string LastName { get; set; } = "";

    [JsonPropertyName("job_title")]
    public string JobTitle { get; set; } = "";

    [JsonPropertyName("email")]
    public string Email { get; set; } = "";

    [JsonPropertyName("phone")]
    public string Phone { get; set; } = "";

    [JsonPropertyName("alt_phone")]
    public string AltPhone { get; set; } = "";

    [JsonPropertyName("fax")]
    public string Fax { get; set; } = "";

    [JsonPropertyName("bill_address")]
    public string BillAddress { get; set; } = "";

    [JsonPropertyName("bill_address_2")]
    public string BillAddress2 { get; set; } = "";

    [JsonPropertyName("bill_address_3")]
    public string BillAddress3 { get; set; } = "";

    [JsonPropertyName("bill_address_4")]
    public string BillAddress4 { get; set; } = "";

    [JsonPropertyName("bill_address_5")]
    public string BillAddress5 { get; set; } = "";

    [JsonPropertyName("bill_city")]
    public string BillCity { get; set; } = "";

    [JsonPropertyName("bill_state")]
    public string BillState { get; set; } = "";

    [JsonPropertyName("bill_zip")]
    public string BillZip { get; set; } = "";

    [JsonPropertyName("bill_country")]
    public string BillCountry { get; set; } = "";

    [JsonPropertyName("ship_address")]
    public string ShipAddress { get; set; } = "";

    [JsonPropertyName("ship_city")]
    public string ShipCity { get; set; } = "";

    [JsonPropertyName("ship_state")]
    public string ShipState { get; set; } = "";

    [JsonPropertyName("ship_zip")]
    public string ShipZip { get; set; } = "";

    [JsonPropertyName("ship_country")]
    public string ShipCountry { get; set; } = "";

    [JsonPropertyName("customer_type")]
    public string CustomerType { get; set; } = "";

    [JsonPropertyName("terms")]
    public string Terms { get; set; } = "";

    [JsonPropertyName("sales_rep")]
    public string SalesRep { get; set; } = "";

    [JsonPropertyName("balance")]
    public decimal Balance { get; set; } = 0;

    [JsonPropertyName("total_balance")]
    public decimal TotalBalance { get; set; } = 0;

    [JsonPropertyName("credit_limit")]
    public decimal CreditLimit { get; set; } = 0;

    [JsonPropertyName("account_number")]
    public string AccountNumber { get; set; } = "";

    [JsonPropertyName("resale_number")]
    public string ResaleNumber { get; set; } = "";

    [JsonPropertyName("vat_number")]
    public string VatNumber { get; set; } = "";

    [JsonPropertyName("tin_number")]
    public string TinNumber { get; set; } = "";

    [JsonPropertyName("is_vat_registered")]
    public bool IsVatRegistered { get; set; } = false;

    [JsonPropertyName("tax_item_ref")]
    public string TaxItemRef { get; set; } = "";

    [JsonPropertyName("tax_code_ref")]
    public string TaxCodeRef { get; set; } = "";

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;

    [JsonPropertyName("notes")]
    public string Notes { get; set; } = "";
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

    [JsonPropertyName("customers")]
    public List<CustomerRecord> Customers { get; set; } = new();
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

    [JsonPropertyName("imported_customers")]
    public int ImportedCustomers { get; set; }

    [JsonPropertyName("sync_timestamp")]
    public string SyncTimestamp { get; set; } = "";
}
