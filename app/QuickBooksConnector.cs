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
    /// Tests connectivity to local QuickBooks Desktop instance.
    /// </summary>
    public (bool Success, string Message) TestConnection(string companyFile = "")
    {
        dynamic? rp = null;
        string ticket = "";

        try
        {
            Type? qbType = Type.GetTypeFromProgID("QBXMLRP2.RequestProcessor");
            if (qbType == null)
            {
                return (false, "QuickBooks XML Request Processor (QBXMLRP2) is not registered. Is QuickBooks Desktop installed on this machine?");
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
    /// Extracts full invoices and payment records in 100% read-only mode using QBXML queries.
    /// </summary>
    public (List<InvoiceRecord> Invoices, List<PaymentRecord> Payments, string? Error) ExtractData(
        string companyFile = "",
        string? fromModifiedDate = null,
        bool includeSerialNumbers = true)
    {
        dynamic? rp = null;
        string ticket = "";

        var invoices = new List<InvoiceRecord>();
        var payments = new List<PaymentRecord>();

        try
        {
            Type? qbType = Type.GetTypeFromProgID("QBXMLRP2.RequestProcessor");
            if (qbType == null)
            {
                return (invoices, payments, "QuickBooks Desktop Request Processor (QBXMLRP2) is not installed.");
            }

            rp = Activator.CreateInstance(qbType);
            if (rp == null)
            {
                return (invoices, payments, "Could not instantiate QBXMLRP2 COM object.");
            }

            rp.OpenConnection2(AppId, AppName, 1);
            ticket = rp.BeginSession(companyFile, 2);

            // 1. Query Invoices (Read-Only)
            string invoiceQueryXml = BuildInvoiceQueryXml(fromModifiedDate);
            string invoiceResponseXml = rp.ProcessRequest(ticket, invoiceQueryXml);
            invoices = ParseInvoiceResponse(invoiceResponseXml, includeSerialNumbers);

            // 2. Query Payments (Read-Only)
            string paymentQueryXml = BuildPaymentQueryXml(fromModifiedDate);
            string paymentResponseXml = rp.ProcessRequest(ticket, paymentQueryXml);
            payments = ParsePaymentResponse(paymentResponseXml);

            return (invoices, payments, null);
        }
        catch (COMException ex)
        {
            return (invoices, payments, InterpretComError(ex.ErrorCode, ex.Message));
        }
        catch (Exception ex)
        {
            return (invoices, payments, $"Extraction error: {ex.Message}");
        }
        finally
        {
            CleanupCom(rp, ticket);
        }
    }

    private string BuildInvoiceQueryXml(string? fromModifiedDate)
    {
        var sb = new StringBuilder();
        sb.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        sb.AppendLine("<?qbxml version=\"13.0\"?>");
        sb.AppendLine("<QBXML>");
        sb.AppendLine("  <QBXMLMsgsRq onError=\"continueOnError\">");
        sb.AppendLine("    <InvoiceQueryRq requestID=\"1\">");
        sb.AppendLine("      <IncludeLineItems>true</IncludeLineItems>");
        sb.AppendLine("      <IncludeLinkedTxns>true</IncludeLinkedTxns>");
        sb.AppendLine("      <OwnerID>0</OwnerID>"); // OwnerID 0 retrieves custom fields (serials)

        if (!string.IsNullOrWhiteSpace(fromModifiedDate) && DateTime.TryParse(fromModifiedDate, out var dt))
        {
            sb.AppendLine("      <ModifiedDateRangeFilter>");
            sb.AppendLine($"        <FromModifiedDate>{dt:yyyy-MM-ddTHH:mm:ss}</FromModifiedDate>");
            sb.AppendLine("      </ModifiedDateRangeFilter>");
        }

        sb.AppendLine("    </InvoiceQueryRq>");
        sb.AppendLine("  </QBXMLMsgsRq>");
        sb.AppendLine("</QBXML>");

        return sb.ToString();
    }

    private string BuildPaymentQueryXml(string? fromModifiedDate)
    {
        var sb = new StringBuilder();
        sb.AppendLine("<?xml version=\"1.0\" encoding=\"utf-8\"?>");
        sb.AppendLine("<?qbxml version=\"13.0\"?>");
        sb.AppendLine("<QBXML>");
        sb.AppendLine("  <QBXMLMsgsRq onError=\"continueOnError\">");
        sb.AppendLine("    <ReceivePaymentQueryRq requestID=\"2\">");
        sb.AppendLine("      <IncludeLineItems>true</IncludeLineItems>");

        if (!string.IsNullOrWhiteSpace(fromModifiedDate) && DateTime.TryParse(fromModifiedDate, out var dt))
        {
            sb.AppendLine("      <ModifiedDateRangeFilter>");
            sb.AppendLine($"        <FromModifiedDate>{dt:yyyy-MM-ddTHH:mm:ss}</FromModifiedDate>");
            sb.AppendLine("      </ModifiedDateRangeFilter>");
        }

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
                decimal subtotal = ParseDecimal(inv.Element("Subtotal")?.Value);
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
                    QBTxnID = txnId
                });
                continue;
            }

            foreach (var line in lineNodes)
            {
                string itemName = line.Element("ItemRef")?.Element("FullName")?.Value ?? "Item";
                string desc = line.Element("Desc")?.Value ?? itemName;
                decimal qty = ParseDecimal(line.Element("Quantity")?.Value, 1);
                decimal amount = ParseDecimal(line.Element("Amount")?.Value);
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
                    QBTxnID = txnId
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
                        Amount = amount > 0 ? amount : totalAmount
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
                    Amount = totalAmount
                });
            }
        }

        return records;
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
        try
        {
            if (!string.IsNullOrEmpty(ticket) && rp is not null)
            {
                ((object)rp).GetType().GetMethod("EndSession")?.Invoke(rp, new object[] { ticket });
            }
            if (rp is not null)
            {
                ((object)rp).GetType().GetMethod("CloseConnection")?.Invoke(rp, null);
                Marshal.ReleaseComObject((object)rp);
            }
        }
        catch
        {
            // Ignore cleanup exceptions
        }
    }

    /// <summary>
    /// Generates realistic mock data including invoice line items with serial numbers for testing.
    /// </summary>
    public static (List<InvoiceRecord> Invoices, List<PaymentRecord> Payments) GetMockData()
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

        return (invoices, payments);
    }
}
