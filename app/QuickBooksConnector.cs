using System.Diagnostics;
using System.Globalization;
using System.Runtime.InteropServices;
using System.Text;
using System.Xml.Linq;

namespace SalesBISync;

public class QuickBooksConnector
{
    private const string AppName = "Sales BI Read-Only Sync";
    private const string AppId = "";

    /// <summary>
    /// Checks if a QuickBooks Desktop process (qbw32.exe or qbw.exe) is actively running on this machine.
    /// </summary>
    public static bool IsQuickBooksRunning()
    {
        try
        {
            var processes = Process.GetProcesses();
            foreach (var p in processes)
            {
                try
                {
                    string name = p.ProcessName.ToLowerInvariant();
                    if (name == "qbw32" || name == "qbw" || name.StartsWith("qbw32") || name.StartsWith("qbw"))
                    {
                        return true;
                    }
                }
                catch
                {
                    // Ignore access denied on system processes
                }
                finally
                {
                    p.Dispose();
                }
            }
            return false;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>
    /// Tests connectivity to local QuickBooks Desktop instance.
    /// </summary>
    public (bool Success, string Message) TestConnection(string companyFile = "")
    {
        dynamic? rp = null;
        string ticket = "";

        try
        {
            Type? qbType = Type.GetTypeFromProgID("QBXMLRP2.RequestProcessor")
                        ?? Type.GetTypeFromProgID("QBXMLRP2e.RequestProcessor");
            if (qbType == null)
            {
                return (false, "QuickBooks XML Request Processor (QBXMLRP2) is not registered. Ensure QuickBooks Desktop is installed and QBXMLRP2.dll is registered.");
            }

            rp = Activator.CreateInstance(qbType);
            if (rp == null)
            {
                return (false, "Could not instantiate QBXMLRP2 COM object.");
            }

            // OpenConnection2: 1 = local QBD
            rp.OpenConnection2(AppId, AppName, 1);

            // BeginSession: 2 = qbFileOpenDoNotCare
            ticket = rp.BeginSession(companyFile, 2);

            return (true, "Successfully connected and authenticated with QuickBooks Desktop!");
        }
        catch (COMException ex)
        {
            string detail = InterpretComError(ex.ErrorCode, ex.Message);
            return (false, detail);
        }
        catch (Exception ex)
        {
            return (false, $"Connection failed: {ex.Message}");
        }
        finally
        {
            CleanupCom(rp, ticket);
        }
    }

    /// <summary>
    /// Extracts full invoices, payment records, and customer directory in 100% read-only mode using QBXML queries.
    /// </summary>
    public (List<InvoiceRecord> Invoices, List<PaymentRecord> Payments, List<CustomerRecord> Customers, string? Error) ExtractData(
        string companyFile = "",
        string? fromModifiedDate = null,
        bool includeSerialNumbers = true,
        Action<string, string?>? progressCallback = null,
        string? fromTxnDate = null,
        string? toTxnDate = null)
    {
        dynamic? rp = null;
        string ticket = "";

        var invoices = new List<InvoiceRecord>();
        var payments = new List<PaymentRecord>();
        var customers = new List<CustomerRecord>();

        try
        {
            progressCallback?.Invoke("connect", "Connecting to QuickBooks Desktop COM API...");
            Type? qbType = Type.GetTypeFromProgID("QBXMLRP2.RequestProcessor")
                        ?? Type.GetTypeFromProgID("QBXMLRP2e.RequestProcessor");
            if (qbType == null)
            {
                return (invoices, payments, customers, "QuickBooks Desktop Request Processor (QBXMLRP2) is not registered. Ensure QuickBooks Desktop is installed and QBXMLRP2.dll is registered.");
            }

            rp = Activator.CreateInstance(qbType);
            if (rp == null)
            {
                return (invoices, payments, customers, "Could not instantiate QBXMLRP2 COM object.");
            }

            rp.OpenConnection2(AppId, AppName, 1);
            ticket = rp.BeginSession(companyFile, 2);
            progressCallback?.Invoke("session_ready", "QuickBooks Desktop read-only session established.");

            string qbXmlVersion = DetectQbXmlVersion(rp, ticket);

            // 1. Query Invoices (Read-Only)
            progressCallback?.Invoke("invoices_query", "Querying QuickBooks Invoices & line items (QBXML)...");
            string invoiceQueryXml = BuildInvoiceQueryXml(qbXmlVersion, fromModifiedDate, fromTxnDate, toTxnDate, includeOwnerId: true);
            string invoiceResponseXml;
            try
            {
                invoiceResponseXml = rp.ProcessRequest(ticket, invoiceQueryXml);
            }
            catch (COMException ex) when ((uint)ex.ErrorCode == 0x80040400)
            {
                // Fallback: Retry without OwnerID if current QuickBooks version doesn't support OwnerID tag
                invoiceQueryXml = BuildInvoiceQueryXml(qbXmlVersion, fromModifiedDate, fromTxnDate, toTxnDate, includeOwnerId: false);
                invoiceResponseXml = rp.ProcessRequest(ticket, invoiceQueryXml);
            }

            progressCallback?.Invoke("invoices_parse", "Parsing invoices and serial numbers...");
            invoices = ParseInvoiceResponse(invoiceResponseXml, includeSerialNumbers);
            progressCallback?.Invoke("invoices_done", $"Extracted {invoices.Count} invoice line items.");

            // 2. Query Payments (Read-Only)
            progressCallback?.Invoke("payments_query", "Querying QuickBooks Received Payments (QBXML)...");
            string paymentQueryXml = BuildPaymentQueryXml(qbXmlVersion, fromModifiedDate, fromTxnDate, toTxnDate);
            string paymentResponseXml = rp.ProcessRequest(ticket, paymentQueryXml);

            progressCallback?.Invoke("payments_parse", "Parsing received payments...");
            payments = ParsePaymentResponse(paymentResponseXml);
            progressCallback?.Invoke("payments_done", $"Extracted {payments.Count} payment records.");

            // 3. Query Customers (Read-Only)
            progressCallback?.Invoke("customers_query", "Querying QuickBooks Customer Directory (QBXML)...");
            string customerQueryXml = BuildCustomerQueryXml(qbXmlVersion);
            string customerResponseXml = rp.ProcessRequest(ticket, customerQueryXml);

            progressCallback?.Invoke("customers_parse", "Parsing customer profiles and contact details...");
            customers = ParseCustomerResponse(customerResponseXml);
            progressCallback?.Invoke("customers_done", $"Extracted {customers.Count} customer profiles.");

            return (invoices, payments, customers, null);
        }
        catch (COMException ex)
        {
            return (invoices, payments, customers, InterpretComError(ex.ErrorCode, ex.Message));
        }
        catch (Exception ex)
        {
            return (invoices, payments, customers, $"Extraction error: {ex.Message}");
        }
        finally
        {
            CleanupCom(rp, ticket);
        }
    }

    private string DetectQbXmlVersion(dynamic rp, string ticket)
    {
        try
        {
            dynamic? rawVersions = null;
            try
            {
                rawVersions = rp.QBXMLVersionsForSession(ticket);
            }
            catch
            {
                try
                {
                    rawVersions = rp.get_QBXMLVersionsForSession(ticket);
                }
                catch { }
            }

            if (rawVersions is Array arr && arr.Length > 0)
            {
                var list = new List<string>();
                foreach (var v in arr)
                {
                    if (v != null) list.Add(v.ToString()!);
                }

                if (list.Contains("13.0")) return "13.0";

                var sorted = list
                    .Where(v => decimal.TryParse(v, NumberStyles.Any, CultureInfo.InvariantCulture, out _))
                    .OrderByDescending(v => decimal.Parse(v, CultureInfo.InvariantCulture))
                    .ToList();

                if (sorted.Count > 0)
                {
                    return sorted[0];
                }
            }
        }
        catch
        {
            // Ignore version detection failure and fallback to 13.0
        }

        return "13.0";
    }

    private string BuildInvoiceQueryXml(string qbXmlVersion, string? fromModifiedDate, string? fromTxnDate = null, string? toTxnDate = null, bool includeOwnerId = true)
    {
        var sb = new StringBuilder();
        sb.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        sb.AppendLine($"<?qbxml version=\"{qbXmlVersion}\"?>");
        sb.AppendLine("<QBXML>");
        sb.AppendLine("  <QBXMLMsgsRq onError=\"continueOnError\">");
        sb.AppendLine("    <InvoiceQueryRq requestID=\"1\">");

        // 1. FILTERS MUST PRECEDE IncludeLineItems according to qbXML DTD
        if (!string.IsNullOrWhiteSpace(fromTxnDate) || !string.IsNullOrWhiteSpace(toTxnDate))
        {
            sb.AppendLine("      <TxnDateRangeFilter>");
            if (!string.IsNullOrWhiteSpace(fromTxnDate) && DateTime.TryParse(fromTxnDate, out var dtFrom))
            {
                sb.AppendLine($"        <FromTxnDate>{dtFrom:yyyy-MM-dd}</FromTxnDate>");
            }
            if (!string.IsNullOrWhiteSpace(toTxnDate) && DateTime.TryParse(toTxnDate, out var dtTo))
            {
                sb.AppendLine($"        <ToTxnDate>{dtTo:yyyy-MM-dd}</ToTxnDate>");
            }
            sb.AppendLine("      </TxnDateRangeFilter>");
        }
        else if (!string.IsNullOrWhiteSpace(fromModifiedDate) && DateTime.TryParse(fromModifiedDate, out var dt))
        {
            sb.AppendLine("      <ModifiedDateRangeFilter>");
            sb.AppendLine($"        <FromModifiedDate>{dt:yyyy-MM-ddTHH:mm:ss}</FromModifiedDate>");
            sb.AppendLine("      </ModifiedDateRangeFilter>");
        }

        // 2. INCLUDE FLAGS
        sb.AppendLine("      <IncludeLineItems>true</IncludeLineItems>");
        sb.AppendLine("      <IncludeLinkedTxns>true</IncludeLinkedTxns>");

        // 3. OWNERID (CUSTOM FIELDS / SERIALS)
        if (includeOwnerId)
        {
            sb.AppendLine("      <OwnerID>0</OwnerID>");
        }

        sb.AppendLine("    </InvoiceQueryRq>");
        sb.AppendLine("  </QBXMLMsgsRq>");
        sb.AppendLine("</QBXML>");

        return sb.ToString();
    }

    private string BuildPaymentQueryXml(string qbXmlVersion, string? fromModifiedDate, string? fromTxnDate = null, string? toTxnDate = null)
    {
        var sb = new StringBuilder();
        sb.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        sb.AppendLine($"<?qbxml version=\"{qbXmlVersion}\"?>");
        sb.AppendLine("<QBXML>");
        sb.AppendLine("  <QBXMLMsgsRq onError=\"continueOnError\">");
        sb.AppendLine("    <ReceivePaymentQueryRq requestID=\"2\">");

        // 1. FILTERS MUST PRECEDE IncludeLineItems according to qbXML DTD
        if (!string.IsNullOrWhiteSpace(fromTxnDate) || !string.IsNullOrWhiteSpace(toTxnDate))
        {
            sb.AppendLine("      <TxnDateRangeFilter>");
            if (!string.IsNullOrWhiteSpace(fromTxnDate) && DateTime.TryParse(fromTxnDate, out var dtFrom))
            {
                sb.AppendLine($"        <FromTxnDate>{dtFrom:yyyy-MM-dd}</FromTxnDate>");
            }
            if (!string.IsNullOrWhiteSpace(toTxnDate) && DateTime.TryParse(toTxnDate, out var dtTo))
            {
                sb.AppendLine($"        <ToTxnDate>{dtTo:yyyy-MM-dd}</ToTxnDate>");
            }
            sb.AppendLine("      </TxnDateRangeFilter>");
        }
        else if (!string.IsNullOrWhiteSpace(fromModifiedDate) && DateTime.TryParse(fromModifiedDate, out var dt))
        {
            sb.AppendLine("      <ModifiedDateRangeFilter>");
            sb.AppendLine($"        <FromModifiedDate>{dt:yyyy-MM-ddTHH:mm:ss}</FromModifiedDate>");
            sb.AppendLine("      </ModifiedDateRangeFilter>");
        }

        // 2. INCLUDE FLAGS
        sb.AppendLine("      <IncludeLineItems>true</IncludeLineItems>");

        sb.AppendLine("    </ReceivePaymentQueryRq>");
        sb.AppendLine("  </QBXMLMsgsRq>");
        sb.AppendLine("</QBXML>");

        return sb.ToString();
    }

    private List<InvoiceRecord> ParseInvoiceResponse(string xml, bool includeSerialNumbers)
    {
        var records = new List<InvoiceRecord>();

        if (string.IsNullOrWhiteSpace(xml)) return records;

        var doc = XDocument.Parse(xml);
        var invoiceNodes = doc.Descendants("InvoiceRet");

        foreach (var inv in invoiceNodes)
        {
            string txnId = inv.Element("TxnID")?.Value ?? "";
            string date = inv.Element("TxnDate")?.Value ?? "";
            string refNum = inv.Element("RefNumber")?.Value ?? "";
            string customer = inv.Element("CustomerRef")?.Element("FullName")?.Value ?? "";
            string rep = inv.Element("SalesRepRef")?.Element("FullName")?.Value ?? "";
            string po = inv.Element("PONumber")?.Value ?? "";
            string memo = inv.Element("Memo")?.Value ?? "";

            // Invoice header financial & tax footers
            decimal subtotal = ParseDecimal(inv.Element("Subtotal")?.Value);
            decimal salesTaxTotal = ParseDecimal(inv.Element("SalesTaxTotal")?.Value);
            decimal salesTaxRate = ParseDecimal(inv.Element("SalesTaxPercentage")?.Value);
            string salesTaxItem = inv.Element("ItemSalesTaxRef")?.Element("FullName")?.Value ?? "";
            string customerTaxCode = inv.Element("CustomerSalesTaxCodeRef")?.Element("FullName")?.Value ?? "";
            decimal appliedAmount = ParseDecimal(inv.Element("AppliedAmount")?.Value);
            decimal balanceRemaining = ParseDecimal(inv.Element("BalanceRemaining")?.Value);
            bool isPaid = inv.Element("IsPaid")?.Value?.Equals("true", StringComparison.OrdinalIgnoreCase) ?? false;
            bool isPending = inv.Element("IsPending")?.Value?.Equals("true", StringComparison.OrdinalIgnoreCase) ?? false;
            string dueDate = inv.Element("DueDate")?.Value ?? "";
            string shipDate = inv.Element("ShipDate")?.Value ?? "";
            string terms = inv.Element("TermsRef")?.Element("FullName")?.Value ?? "";

            // Collect any invoice-level custom fields for serials
            var invoiceCustomFields = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            foreach (var ext in inv.Elements("DataExtRet"))
            {
                string name = ext.Element("DataExtName")?.Value ?? "";
                string val = ext.Element("DataExtValue")?.Value ?? "";
                if (!string.IsNullOrWhiteSpace(name) && !string.IsNullOrWhiteSpace(val))
                {
                    invoiceCustomFields[name] = val;
                }
            }

            var lineNodes = inv.Elements("InvoiceLineRet");
            if (!lineNodes.Any())
            {
                // Invoice with no distinct lines: create single record
                records.Add(new InvoiceRecord
                {
                    Type = "Invoice",
                    Date = date,
                    Num = refNum,
                    Name = customer,
                    Item = "Invoice Summary",
                    Description = memo,
                    Amount = subtotal,
                    Rep = rep,
                    PONumber = po,
                    Memo = memo,
                    QBTxnID = txnId,
                    Subtotal = subtotal,
                    SalesTaxTotal = salesTaxTotal,
                    SalesTaxRate = salesTaxRate,
                    SalesTaxItem = salesTaxItem,
                    CustomerTaxCode = customerTaxCode,
                    AppliedAmount = appliedAmount,
                    BalanceRemaining = balanceRemaining,
                    IsPaid = isPaid,
                    IsPending = isPending,
                    DueDate = dueDate,
                    ShipDate = shipDate,
                    Terms = terms,
                    UnitPrice = subtotal
                });
                continue;
            }

            foreach (var line in lineNodes)
            {
                string itemName = line.Element("ItemRef")?.Element("FullName")?.Value ?? "Item";
                string desc = line.Element("Desc")?.Value ?? itemName;
                decimal qty = ParseDecimal(line.Element("Quantity")?.Value, 1);
                decimal amount = ParseDecimal(line.Element("Amount")?.Value);
                decimal unitPrice = ParseDecimal(line.Element("Rate")?.Value, qty > 0 ? (amount / qty) : amount);
                string taxCode = line.Element("SalesTaxCodeRef")?.Element("FullName")?.Value ?? "Taxable Sales";

                // Check Advanced Inventory Serial / Lot numbers
                string lotNumber = line.Element("LotNumber")?.Value ?? "";
                string serialNumber = line.Element("SerialNumber")?.Value ?? "";

                // Check line-level custom fields
                var lineCustomSerials = new List<string>();
                foreach (var ext in line.Elements("DataExtRet"))
                {
                    string extName = ext.Element("DataExtName")?.Value ?? "";
                    string extVal = ext.Element("DataExtValue")?.Value ?? "";
                    if (IsSerialField(extName) && !string.IsNullOrWhiteSpace(extVal))
                    {
                        lineCustomSerials.Add($"{extName}: {extVal}");
                    }
                }

                if (includeSerialNumbers)
                {
                    var extraDetails = new List<string>();

                    if (!string.IsNullOrWhiteSpace(serialNumber) && !desc.Contains(serialNumber, StringComparison.OrdinalIgnoreCase))
                    {
                        extraDetails.Add($"S/N: {serialNumber}");
                    }
                    if (!string.IsNullOrWhiteSpace(lotNumber) && !desc.Contains(lotNumber, StringComparison.OrdinalIgnoreCase))
                    {
                        extraDetails.Add($"Lot: {lotNumber}");
                    }
                    foreach (var s in lineCustomSerials)
                    {
                        if (!desc.Contains(s, StringComparison.OrdinalIgnoreCase))
                        {
                            extraDetails.Add(s);
                        }
                    }

                    // Check invoice-level serial fields
                    foreach (var kvp in invoiceCustomFields)
                    {
                        if (IsSerialField(kvp.Key) && !desc.Contains(kvp.Value, StringComparison.OrdinalIgnoreCase))
                        {
                            extraDetails.Add($"{kvp.Key}: {kvp.Value}");
                        }
                    }

                    if (extraDetails.Count > 0)
                    {
                        desc = $"{desc} [{string.Join(" | ", extraDetails)}]";
                    }
                }

                // Extract product category from item path if present (e.g. Hardware:Servers -> Hardware)
                string category = "";
                if (itemName.Contains(':'))
                {
                    category = itemName.Split(':')[0].Trim();
                }

                records.Add(new InvoiceRecord
                {
                    Type = "Invoice",
                    Date = date,
                    Num = refNum,
                    Name = customer,
                    Item = itemName,
                    Description = desc,
                    SalesTaxCode = taxCode,
                    Qty = qty,
                    Amount = amount,
                    ProductCategory = category,
                    Rep = rep,
                    PONumber = po,
                    Memo = memo,
                    QBTxnID = txnId,
                    Subtotal = subtotal,
                    SalesTaxTotal = salesTaxTotal,
                    SalesTaxRate = salesTaxRate,
                    SalesTaxItem = salesTaxItem,
                    CustomerTaxCode = customerTaxCode,
                    AppliedAmount = appliedAmount,
                    BalanceRemaining = balanceRemaining,
                    IsPaid = isPaid,
                    IsPending = isPending,
                    DueDate = dueDate,
                    ShipDate = shipDate,
                    Terms = terms,
                    UnitPrice = unitPrice
                });
            }
        }

        return records;
    }

    private List<PaymentRecord> ParsePaymentResponse(string xml)
    {
        var records = new List<PaymentRecord>();

        if (string.IsNullOrWhiteSpace(xml)) return records;

        var doc = XDocument.Parse(xml);
        var paymentNodes = doc.Descendants("ReceivePaymentRet");

        foreach (var pay in paymentNodes)
        {
            string customer = pay.Element("CustomerRef")?.Element("FullName")?.Value ?? "";
            string date = pay.Element("TxnDate")?.Value ?? "";
            string refNum = pay.Element("RefNumber")?.Value ?? "";
            decimal totalAmount = ParseDecimal(pay.Element("TotalAmount")?.Value);
            string payMethod = pay.Element("PaymentMethodRef")?.Element("FullName")?.Value ?? "";
            string depositAccount = pay.Element("DepositToAccountRef")?.Element("FullName")?.Value ?? "";
            string payMemo = pay.Element("Memo")?.Value ?? "";
            decimal unusedPayment = ParseDecimal(pay.Element("UnusedPayment")?.Value);

            var appliedTxns = pay.Descendants("AppliedToTxnRet");
            if (appliedTxns.Any())
            {
                foreach (var applied in appliedTxns)
                {
                    string invNum = applied.Element("RefNumber")?.Value ?? "";
                    decimal amount = ParseDecimal(applied.Element("Amount")?.Value);

                    records.Add(new PaymentRecord
                    {
                        CustomerName = customer,
                        InvoiceNum = invNum,
                        PaymentDate = date,
                        ReferenceNum = refNum,
                        Amount = amount > 0 ? amount : totalAmount,
                        PaymentMethod = payMethod,
                        DepositToAccount = depositAccount,
                        Memo = payMemo,
                        UnusedPayment = unusedPayment
                    });
                }
            }
            else
            {
                records.Add(new PaymentRecord
                {
                    CustomerName = customer,
                    InvoiceNum = "",
                    PaymentDate = date,
                    ReferenceNum = refNum,
                    Amount = totalAmount,
                    PaymentMethod = payMethod,
                    DepositToAccount = depositAccount,
                    Memo = payMemo,
                    UnusedPayment = unusedPayment
                });
            }
        }

        return records;
    }

    private string BuildCustomerQueryXml(string qbXmlVersion)
    {
        var sb = new StringBuilder();
        sb.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        sb.AppendLine($"<?qbxml version=\"{qbXmlVersion}\"?>");
        sb.AppendLine("<QBXML>");
        sb.AppendLine("  <QBXMLMsgsRq onError=\"continueOnError\">");
        sb.AppendLine("    <CustomerQueryRq requestID=\"3\">");
        sb.AppendLine("      <ActiveStatus>All</ActiveStatus>");
        sb.AppendLine("    </CustomerQueryRq>");
        sb.AppendLine("  </QBXMLMsgsRq>");
        sb.AppendLine("</QBXML>");
        return sb.ToString();
    }

    private List<CustomerRecord> ParseCustomerResponse(string responseXml)
    {
        var records = new List<CustomerRecord>();
        if (string.IsNullOrWhiteSpace(responseXml)) return records;

        var doc = XDocument.Parse(responseXml);
        var custNodes = doc.Descendants("CustomerRet");

        foreach (var cust in custNodes)
        {
            string listId = cust.Element("ListID")?.Value ?? "";
            string name = cust.Element("Name")?.Value ?? "";
            string fullName = cust.Element("FullName")?.Value ?? name;
            string companyName = cust.Element("CompanyName")?.Value ?? "";
            string firstName = cust.Element("FirstName")?.Value ?? "";
            string lastName = cust.Element("LastName")?.Value ?? "";
            string jobTitle = cust.Element("JobTitle")?.Value ?? "";
            string contact = cust.Element("Contact")?.Value ?? "";
            if (string.IsNullOrWhiteSpace(contact) && (!string.IsNullOrWhiteSpace(firstName) || !string.IsNullOrWhiteSpace(lastName)))
            {
                contact = $"{firstName} {lastName}".Trim();
            }

            string phone = cust.Element("Phone")?.Value ?? "";
            string altPhone = cust.Element("AltPhone")?.Value ?? "";
            string fax = cust.Element("Fax")?.Value ?? "";
            string email = cust.Element("Email")?.Value ?? "";

            var billAddr = cust.Element("BillAddress");
            string billAddress1 = billAddr?.Element("Addr1")?.Value ?? "";
            string billAddress2 = billAddr?.Element("Addr2")?.Value ?? "";
            string billAddress3 = billAddr?.Element("Addr3")?.Value ?? "";
            string billAddress4 = billAddr?.Element("Addr4")?.Value ?? "";
            string billAddress5 = billAddr?.Element("Addr5")?.Value ?? "";
            
            var billAddressParts = new[] { billAddress1, billAddress2, billAddress3, billAddress4, billAddress5 }
                .Where(s => !string.IsNullOrWhiteSpace(s));
            string combinedBillAddress = string.Join(", ", billAddressParts);

            string billCity = billAddr?.Element("City")?.Value ?? "";
            string billState = billAddr?.Element("State")?.Value ?? "";
            string billZip = billAddr?.Element("PostalCode")?.Value ?? "";
            string billCountry = billAddr?.Element("Country")?.Value ?? "";

            var shipAddr = cust.Element("ShipAddress");
            string shipAddress = shipAddr?.Element("Addr1")?.Value ?? "";
            string shipCity = shipAddr?.Element("City")?.Value ?? "";
            string shipState = shipAddr?.Element("State")?.Value ?? "";
            string shipZip = shipAddr?.Element("PostalCode")?.Value ?? "";
            string shipCountry = shipAddr?.Element("Country")?.Value ?? "";

            string customerType = cust.Element("CustomerTypeRef")?.Element("FullName")?.Value ?? "";
            string terms = cust.Element("TermsRef")?.Element("FullName")?.Value ?? "";
            string salesRep = cust.Element("SalesRepRef")?.Element("FullName")?.Value ?? "";

            decimal balance = ParseDecimal(cust.Element("Balance")?.Value);
            decimal totalBalance = ParseDecimal(cust.Element("TotalBalance")?.Value, balance);
            decimal creditLimit = ParseDecimal(cust.Element("CreditLimit")?.Value);

            string acctNum = cust.Element("AccountNumber")?.Value ?? "";
            bool isActive = cust.Element("IsActive")?.Value?.Equals("true", StringComparison.OrdinalIgnoreCase) ?? true;
            string notes = cust.Element("Notes")?.Value ?? "";

            // Additional Tax Details
            string resaleNum = cust.Element("ResaleNumber")?.Value ?? "";
            string taxItemRef = cust.Element("ItemSalesTaxRef")?.Element("FullName")?.Value ?? "";
            string taxCodeRef = cust.Element("SalesTaxCodeRef")?.Element("FullName")?.Value ?? "";

            var customTaxFields = new List<string>();
            foreach (var ext in cust.Elements("DataExtRet"))
            {
                string val = ext.Element("DataExtValue")?.Value ?? "";
                if (!string.IsNullOrWhiteSpace(val)) customTaxFields.Add(val);
            }

            var (vatNumber, tinNumber, isVatRegistered) = ExtractTaxIdentifiers(
                resaleNum,
                $"{combinedBillAddress} {billCity} {billState} {notes} {string.Join(" ", customTaxFields)}"
            );

            records.Add(new CustomerRecord
            {
                ListID = listId,
                Name = name,
                FullName = fullName,
                CompanyName = companyName,
                ContactName = contact,
                FirstName = firstName,
                LastName = lastName,
                JobTitle = jobTitle,
                Email = email,
                Phone = phone,
                AltPhone = altPhone,
                Fax = fax,
                BillAddress = combinedBillAddress,
                BillAddress2 = billAddress2,
                BillAddress3 = billAddress3,
                BillAddress4 = billAddress4,
                BillAddress5 = billAddress5,
                BillCity = billCity,
                BillState = billState,
                BillZip = billZip,
                BillCountry = billCountry,
                ShipAddress = shipAddress,
                ShipCity = shipCity,
                ShipState = shipState,
                ShipZip = shipZip,
                ShipCountry = shipCountry,
                CustomerType = customerType,
                Terms = terms,
                SalesRep = salesRep,
                Balance = balance,
                TotalBalance = totalBalance,
                CreditLimit = creditLimit,
                AccountNumber = acctNum,
                ResaleNumber = resaleNum,
                VatNumber = vatNumber,
                TinNumber = tinNumber,
                IsVatRegistered = isVatRegistered,
                TaxItemRef = taxItemRef,
                TaxCodeRef = taxCodeRef,
                IsActive = isActive,
                Notes = notes
            });
        }

        return records;
    }

    private static (string vatNum, string tinNum, bool isVatRegistered) ExtractTaxIdentifiers(string resaleNum, string fullText)
    {
        string full = $"{resaleNum} {fullText}";
        string vatNum = "";
        string tinNum = "";
        bool isVat = false;

        // 1. Explicit VAT Registration Number
        var vatMatch = System.Text.RegularExpressions.Regex.Match(
            full,
            @"(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{4})?|[0-9A-Z\-\/]{7,})",
            System.Text.RegularExpressions.RegexOptions.IgnoreCase);

        if (vatMatch.Success)
        {
            vatNum = vatMatch.Groups[1].Value.Trim();
            isVat = true;
        }
        else
        {
            var vatDirect = System.Text.RegularExpressions.Regex.Match(full, @"\b([0-9]{9}-7000)\b");
            if (vatDirect.Success)
            {
                vatNum = vatDirect.Groups[1].Value.Trim();
                isVat = true;
            }
        }

        // 2. TIN Number
        var tinMatch = System.Text.RegularExpressions.Regex.Match(
            full,
            @"(?:TIN)\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9]{9}|[0-9A-Z\-\/]{7,})",
            System.Text.RegularExpressions.RegexOptions.IgnoreCase);

        if (tinMatch.Success)
        {
            tinNum = tinMatch.Groups[1].Value.Trim();
        }
        else if (!string.IsNullOrWhiteSpace(resaleNum))
        {
            if (resaleNum.Contains("-7000"))
            {
                vatNum = resaleNum.Trim();
                isVat = true;
            }
            else if (resaleNum.Length >= 9)
            {
                tinNum = resaleNum.Trim();
            }
        }

        if (!isVat && !string.IsNullOrWhiteSpace(tinNum) && full.Contains("-7000"))
        {
            vatNum = $"{tinNum}-7000";
            isVat = true;
        }

        return (vatNum, tinNum, isVat);
    }

    private static bool IsSerialField(string fieldName)
    {
        string name = fieldName.ToLowerInvariant();
        return name.Contains("serial") || name.Contains("s/n") || name.Contains("sn") || 
               name.Contains("imei") || name.Contains("mac") || name.Contains("lot");
    }

    private static decimal ParseDecimal(string? val, decimal defaultVal = 0)
    {
        if (string.IsNullOrWhiteSpace(val)) return defaultVal;
        if (decimal.TryParse(val, NumberStyles.Any, CultureInfo.InvariantCulture, out var res))
        {
            return res;
        }
        return defaultVal;
    }

    private string InterpretComError(int errorCode, string originalMessage)
    {
        unchecked
        {
            uint code = (uint)errorCode;
            return code switch
            {
                0x80040154 => "QuickBooks COM component is not registered or process bitness mismatch (0x80040154: REGDB_E_CLASSNOTREG). QuickBooks Desktop uses a 32-bit (x86) COM library. The application must run as 32-bit (win-x86), and QBXMLRP2.dll must be registered on the system.",
                0x80040400 => "QuickBooks XML stream parsing error (0x80040400). The XML query was rejected by QuickBooks due to element ordering or unsupported query tags for the company file version.",
                0x80040408 => "QuickBooks is not running. Please open QuickBooks Desktop with your company file open, then retry.",
                0x80040416 => "QuickBooks company file is not open. Please open your company file in QuickBooks Desktop.",
                0x8004041C => "QuickBooks denied access. Check your QuickBooks integrated application permissions in QuickBooks: Edit > Preferences > Integrated Applications.",
                0x80040418 => "This QuickBooks session was cancelled by the user.",
                0x80040420 => "The QuickBooks company file could not be opened in multi-user mode. Open in single-user or ensure server is running.",
                _ => $"QuickBooks COM Error (0x{code:X8}): {originalMessage}"
            };
        }
    }

    private void CleanupCom(dynamic? rp, string ticket)
    {
        if (rp is null) return;

        try
        {
            if (!string.IsNullOrEmpty(ticket))
            {
                try
                {
                    rp.EndSession(ticket);
                }
                catch
                {
                    try
                    {
                        rp.GetType().InvokeMember("EndSession", System.Reflection.BindingFlags.InvokeMethod, null, rp, new object[] { ticket });
                    }
                    catch { }
                }
            }

            try
            {
                rp.CloseConnection();
            }
            catch
            {
                try
                {
                    rp.GetType().InvokeMember("CloseConnection", System.Reflection.BindingFlags.InvokeMethod, null, rp, null);
                }
                catch { }
            }
        }
        finally
        {
            try
            {
                Marshal.FinalReleaseComObject(rp);
            }
            catch { }

            rp = null;
            GC.Collect();
            GC.WaitForPendingFinalizers();
        }
    }

    /// <summary>
    /// Generates realistic mock data including invoice line items, payments, and customer profiles for testing.
    /// </summary>
    public static (List<InvoiceRecord> Invoices, List<PaymentRecord> Payments, List<CustomerRecord> Customers) GetMockData()
    {
        var invoices = new List<InvoiceRecord>
        {
            new()
            {
                Type = "Invoice",
                Date = DateTime.Now.AddDays(-10).ToString("yyyy-MM-dd"),
                Num = "INV-" + Random.Shared.Next(10000, 99999),
                Name = "Apex Global Technologies",
                Item = "Servers:Rackmount",
                Description = "PowerEdge R750 2U Server [S/N: PE-99482-SN10294 | Dual Xeon Gold, 64GB DDR4, 2x 960GB NVMe]",
                SalesTaxCode = "Taxable Sales",
                Qty = 1,
                Amount = 5900.00m,
                ProductCategory = "Hardware",
                Rep = "JS",
                PONumber = "PO-APEX-4491",
                Memo = "Primary data center deployment"
            },
            new()
            {
                Type = "Invoice",
                Date = DateTime.Now.AddDays(-8).ToString("yyyy-MM-dd"),
                Num = "INV-" + Random.Shared.Next(10000, 99999),
                Name = "Matrix Logistics Ltd",
                Item = "Networking:Switches",
                Description = "Cisco Catalyst 9300 48-Port PoE+ [S/N: FCW2419L02A | MAC: 00:1A:2B:3C:4D:5E]",
                SalesTaxCode = "Taxable Sales",
                Qty = 2,
                Amount = 4800.00m,
                ProductCategory = "Hardware",
                Rep = "MR",
                PONumber = "PO-MAT-8812",
                Memo = "Branch network upgrade"
            },
            new()
            {
                Type = "Invoice",
                Date = DateTime.Now.AddDays(-5).ToString("yyyy-MM-dd"),
                Num = "INV-" + Random.Shared.Next(10000, 99999),
                Name = "Summit Healthcare Partners",
                Item = "Services:Consulting",
                Description = "Cloud Infrastructure Security Audit & Optimization",
                SalesTaxCode = "Taxable Sales",
                Qty = 15,
                Amount = 3750.00m,
                ProductCategory = "Services",
                Rep = "JS",
                PONumber = "PO-SHP-2026-9",
                Memo = "Phase 1 completed"
            }
        };

        var payments = new List<PaymentRecord>
        {
            new()
            {
                CustomerName = "Apex Global Technologies",
                InvoiceNum = invoices[0].Num,
                PaymentDate = DateTime.Now.AddDays(-2).ToString("yyyy-MM-dd"),
                ReferenceNum = "ACH-772910",
                Amount = 5900.00m
            }
        };

        var customers = new List<CustomerRecord>
        {
            new()
            {
                ListID = "80000001-1001",
                Name = "Apex Global Technologies",
                FullName = "Apex Global Technologies",
                CompanyName = "Apex Global Technologies Inc.",
                ContactName = "David Chen",
                FirstName = "David",
                LastName = "Chen",
                JobTitle = "VP Infrastructure",
                Email = "procurement@apextech.example.com",
                Phone = "(512) 555-0144",
                BillAddress = "100 Technology Parkway, Suite 400",
                BillCity = "Austin",
                BillState = "TX",
                BillZip = "78701",
                BillCountry = "USA",
                CustomerType = "Partner",
                Terms = "Net 30",
                SalesRep = "JS",
                Balance = 5900.00m,
                TotalBalance = 5900.00m,
                CreditLimit = 50000.00m,
                AccountNumber = "ACT-8821",
                IsActive = true
            },
            new()
            {
                ListID = "80000002-1002",
                Name = "Matrix Logistics Ltd",
                FullName = "Matrix Logistics Ltd",
                CompanyName = "Matrix Logistics International",
                ContactName = "Sarah Jenkins",
                FirstName = "Sarah",
                LastName = "Jenkins",
                JobTitle = "Operations Director",
                Email = "s.jenkins@matrixlogistics.example.com",
                Phone = "(312) 555-0188",
                BillAddress = "450 Harbor Boulevard, Dock 12",
                BillCity = "Chicago",
                BillState = "IL",
                BillZip = "60601",
                BillCountry = "USA",
                CustomerType = "End Customer",
                Terms = "Net 15",
                SalesRep = "MR",
                Balance = 0.00m,
                TotalBalance = 0.00m,
                CreditLimit = 25000.00m,
                AccountNumber = "ACT-4419",
                IsActive = true
            },
            new()
            {
                ListID = "80000003-1003",
                Name = "Summit Healthcare Partners",
                FullName = "Summit Healthcare Partners",
                CompanyName = "Summit Healthcare System LLC",
                ContactName = "Dr. Robert Vance",
                FirstName = "Robert",
                LastName = "Vance",
                JobTitle = "Chief Technology Officer",
                Email = "admin@summithealthcare.example.com",
                Phone = "(206) 555-0177",
                BillAddress = "720 Medical Center Way",
                BillCity = "Seattle",
                BillState = "WA",
                BillZip = "98104",
                BillCountry = "USA",
                CustomerType = "End Customer",
                Terms = "Net 30",
                SalesRep = "JS",
                Balance = 3750.00m,
                TotalBalance = 3750.00m,
                CreditLimit = 75000.00m,
                AccountNumber = "ACT-9903",
                IsActive = true
            }
        };

        return (invoices, payments, customers);
    }
}
