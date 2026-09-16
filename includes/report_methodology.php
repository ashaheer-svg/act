<?php
/**
 * Report Methodology & Interpretation Guide Component
 * Provides comprehensive documentation on Business Usage, Scope & Validity, and Calculation Methods.
 * Concept B: Minimized by default into a top Command-Bar button ('Method'), expanding on demand.
 */

function renderReportMethodology($type, $currency = 'LKR ') {
    $guides = [
        'invoices' => [
            'title' => 'Commercial Invoices & Line-Item Audit Methodology',
            'badge' => 'Document Audit • Tax Invoice Integrity',
            'usage' => 'Used by billing auditors, credit controllers, and sales managers to verify commercial invoice document totals, tax breakdowns (base net vs 18% VAT), serial number dispatches, and matched payment receipts.',
            'validity' => 'Aggregates verified line items by QuickBooks Invoice Number in Sri Lanka Rupees (LKR). Non-viable 0-value placeholder descriptions are suppressed while genuine serialized warranty dispatches are preserved.',
            'calc' => [
                'Invoice Base Net' => 'Σ s.base_value = total_amount / (1 + 0.18)',
                'Statutory 18% VAT' => 'Σ s.vat_component = total_amount - base_value',
                'Invoice Gross Total' => 'Σ s.total_amount for all line items under invoice',
                'Settlement Status' => 'Settled if paid_date recorded in sales ledger or matched payments >= gross; otherwise Unpaid'
            ]
        ],
        'unpaid_invoices' => [
            'title' => 'Unpaid Invoices Ledger (Sorted by Customer) Methodology',
            'badge' => 'Receivables Audit • Aging Risk Tiers',
            'usage' => 'Directs collection operations, debt recovery priority, and legal notice escalation by grouping outstanding receivables by debtor account alphabetically or by outstanding balance.',
            'validity' => 'Scope covers commercial invoices dated after 2021-12-31 with balance due > 0 and paid_date null/empty. Invoices up to 2021-12-31 are recognized as settled.',
            'calc' => [
                'Net Base' => 'Σ base_value = total_amount / 1.18',
                '18% VAT' => 'Σ vat_component = total_amount - base_value',
                'Aging Days' => 'CAST(julianday(\'now\') - julianday(invoice_date) AS INT)',
                'Risk Tiers' => 'Current (≤30d), Attention (31–60d), Overdue (61–90d), Critical (>90d)'
            ]
        ],
        'warranties' => [
            'title' => 'Hardware Asset & Serial Warranty Registry Methodology',
            'badge' => 'Asset Lifecycle • RMA & Service Contract SLA',
            'usage' => 'Tracks individual hardware asset units (Synology NAS, BDCOM switches, Seagate enterprise drives) by discrete serial number, monitoring active warranty windows and upcoming expirations.',
            'validity' => 'Assets extracted from QuickBooks multi-line invoice descriptions via AI normalization. Units are linked to customer profiles and originating invoice numbers.',
            'calc' => [
                'Days Remaining' => 'julianday(warranty_expiry_date) - julianday(CURRENT_DATE)',
                'Expiring Soon' => 'Expiry within 30, 60, or 90 days from today',
                'Status' => 'ACTIVE if expiry >= today; EXPIRED if expiry < today'
            ]
        ],
        'ltv' => [
            'title' => 'Customer Lifetime Value (LTV) & Cohort Analysis Methodology',
            'badge' => 'Strategic Enterprise Value • Customer Equity',
            'usage' => 'Identifies highest-yielding enterprise accounts, customer acquisition cohort retention, and long-term customer equity in Sri Lanka Rupees (LKR).',
            'validity' => 'Aggregates all historical commercial sales from customer acquisition date to present, excluding zero-value memos.',
            'calc' => [
                'Gross Invoiced LTV' => 'Σ total_amount billed to customer across lifetime',
                'Realized Base LTV' => 'Σ base_value recognized revenue',
                'Gross Margin LTV' => 'Realized Base LTV - Aggregated Cost of Goods Sold (COGS)',
                'Avg Order Value (AOV)' => 'Gross Invoiced LTV / Total Lifetime Orders'
            ]
        ],
        'churn' => [
            'title' => 'Customer Churn Risk & Account Retention Intelligence Methodology',
            'badge' => 'Predictive Retention • Revenue Risk Mitigation',
            'usage' => 'Detects dormant accounts, sudden ordering drop-offs, and lapsed clients before complete attrition occurs.',
            'validity' => 'Monitors purchasing velocity and order intervals across all active customer profiles.',
            'calc' => [
                'Days Inactive' => 'CAST(julianday(\'now\') - julianday(MAX(invoice_date)) AS INT)',
                'Risk Scoring' => 'Active (≤ 90d), Low Risk (91–180d), High Risk (181–365d), Lapsed / Churned (> 365d)',
                'At-Risk Revenue' => 'Historical annualized billing for accounts with High/Lapsed churn risk'
            ]
        ],
        'eol' => [
            'title' => 'Hardware Asset End-of-Life (EOL) & Refresh Intelligence Methodology',
            'badge' => 'Hardware Refresh • Lifecycle Expansion',
            'usage' => 'Directs proactive hardware replacement campaigns, trade-in proposals, and migration to modern models before hardware failure.',
            'validity' => 'Evaluates serialized hardware assets sold with expired warranties or operational life exceeding 36 to 60 months.',
            'calc' => [
                'Operating Lifespan' => 'CAST(julianday(\'now\') - julianday(initial_start_date) AS INT) / 365.25 years',
                'Refresh Priority' => 'Critical if operating > 5 years or warranty expired > 2 years',
                'Opportunity Value' => 'Estimated modern replacement hardware list price'
            ]
        ],
        'contracts' => [
            'title' => 'Maintenance Agreements & Annual Service Contract (SLA) Methodology',
            'badge' => 'Recurring Service SLA • SLA Governance',
            'usage' => 'Tracks annual maintenance agreements (MA), hardware support contracts, and software SLAs, preventing lapsed customer coverage.',
            'validity' => 'Aggregates active, expiring, and historical service agreements registered under client accounts.',
            'calc' => [
                'Annual Contract Value (ACV)' => 'Contract gross billing per annual service term',
                'Expiration Window' => 'Days until contract end_date (Due Soon if ≤ 60 days)',
                'Renewal Opportunity' => 'Estimated renewal proposal value for pending expirations'
            ]
        ],
        'rental_roi' => [
            'title' => 'Rental Fleet Equipment ROI & Unit Yield Methodology',
            'badge' => 'Fleet Asset Economics • Payback Velocity',
            'usage' => 'Evaluates capital return on investment (ROI), monthly rental yield, and payback period for leased/rented IT assets and equipment.',
            'validity' => 'Tracks capitalized rental fleet asset costs against recurring rental billing invoices.',
            'calc' => [
                'Rental Revenue Realized' => 'Σ rental line item billings attributed to asset',
                'Payback Period' => 'Asset Capital Cost / Monthly Rental Inflow',
                'Annualized Yield %' => '(Annual Rental Invoiced / Asset Capital Cost) × 100'
            ]
        ],
        'brand_growth' => [
            'title' => 'Brand Revenue Performance & Vendor Expansion Methodology',
            'badge' => 'Vendor Analytics • Brand Contribution',
            'usage' => 'Evaluates commercial contribution by brand (Synology, BDCOM, Seagate, ASUS, Acronis, etc.) to negotiate vendor rebate tiers and inventory allocation.',
            'validity' => 'Categorized line item sales mapped to verified vendor brands in LKR.',
            'calc' => [
                'Brand Base Net' => 'Σ base_value for brand line items',
                'YoY Brand Growth' => '((Current Year Base - Prior Year Base) / Prior Year Base) × 100',
                'Brand Revenue Share' => '(Brand Gross / Total Platform Gross) × 100'
            ]
        ],
        'dso_trends' => [
            'title' => 'Days Sales Outstanding (DSO) & Collection Velocity Methodology',
            'badge' => 'Working Capital • Cash Conversion Velocity',
            'usage' => 'Monitors enterprise cash conversion efficiency and the speed at which receivables are converted to cash.',
            'validity' => 'Evaluates all settled commercial invoices from issue date to bank receipt clearance.',
            'calc' => [
                'Invoice DSO' => 'julianday(paid_date) - julianday(invoice_date)',
                'Period DSO' => '(Total Outstanding Receivables / Gross Period Sales) × Days in Period',
                'Collection Target' => 'Benchmark standard ≤ 30 days'
            ]
        ],
        'tax_audit' => [
            'title' => 'Statutory 18% VAT & Inland Revenue (IRD) RAMIS Reconciliation Methodology',
            'badge' => 'Statutory Tax Compliance • RAMIS Reconciled',
            'usage' => 'Used for IRD monthly and quarterly tax audits, verifying output VAT collections and tax invoice declarations.',
            'validity' => 'Commercial invoices with legal tax claims in LKR, applying the statutory 18% VAT model (effective Jan 1, 2024).',
            'calc' => [
                'Statutory Base Net' => 'Σ base_value = total_amount / 1.18',
                '18% VAT Output' => 'Σ vat_component = total_amount - base_value',
                'Total Tax Invoice Claim' => 'Σ total_amount'
            ]
        ],
        'monthly_overview' => [
            'title' => 'Executive Monthly Column-Wise Sales Performance Matrix Methodology',
            'badge' => 'Financial Cadence • Multi-Month Single-Page Matrix',
            'usage' => 'Enables C-level executives, sales directors, and financial controllers to review monthly revenue trajectory, invoicing cadence, units volume, and collection rates side-by-side across all 12 months on a single screen.',
            'validity' => 'Aggregates verified transactions in sales ledger by calendar month (YYYY-MM). Supports rolling 12 months or selectable calendar years (Jan–Dec).',
            'calc' => [
                'Gross Invoiced' => 'Σ total_amount billed in month',
                'Net Base' => 'Σ base_value recognized revenue before statutory tax',
                '18% VAT' => 'Σ vat_component statutory tax portion',
                'Collection Rate' => '(Σ Collected / Σ Gross) × 100%',
                'MoM Growth' => '((Current Month Gross - Prior Month Gross) / Prior Month Gross) × 100%'
            ]
        ],
        'monthly_customer' => [
            'title' => 'Customer Monthly Sales Matrix & Account Run-Rate Methodology',
            'badge' => 'Account Retention • 12-Month Customer Revenue Trajectory',
            'usage' => 'Allows key account managers and sales leaders to track customer purchase cadence, identify seasonal demand surges, spot declining purchasing volume, and audit monthly spend across all active enterprise accounts on a single page.',
            'validity' => 'Aggregates verified QuickBooks commercial invoices grouped by customer across 12 discrete months. Zero-value memo rows are excluded. Supports filtering by customer type (Partner vs End Customer) and product brand.',
            'calc' => [
                'Monthly Account Billing' => 'Σ total_amount billed to customer per month',
                'Period Total' => 'Σ total_amount across active 12-month window',
                'Monthly Run-Rate Benchmark' => 'Period Total / 12 months',
                'Portfolio Revenue Share' => '(Customer Period Total / Grand Portfolio Total) × 100%'
            ]
        ],
        'monthly_rep' => [
            'title' => 'Sales Representative Monthly Performance & Client Reach Methodology',
            'badge' => 'Sales Governance • 12-Month Rep Revenue & Quota Cadence',
            'usage' => 'Empowers sales managers to analyze individual sales executive monthly revenue contribution, active customer reach, deal count, and sales stability across the 12-month fiscal timeline.',
            'validity' => 'Invoices mapped via sales rep initials/code linked with verified team roster. Unmapped transactions grouped under \'Unassigned / Direct\'. Supports brand and customer type filtering.',
            'calc' => [
                'Monthly Rep Billing' => 'Σ total_amount closed by sales representative per month',
                'Period Total' => 'Σ total_amount closed across active 12-month window',
                'Monthly Average Run-Rate' => 'Period Total / 12 months',
                'Active Customer Reach' => 'COUNT(DISTINCT customer_name) transacted in period',
                'Team Contribution %' => '(Rep Period Total / Total Sales Team Gross) × 100%'
            ]
        ],
        'monthly' => [
            'title' => 'Executive Monthly Column-Wise Sales Performance Matrix Methodology',
            'badge' => 'Financial Cadence • Multi-Month Single-Page Matrix',
            'usage' => 'Enables C-level executives, sales directors, and financial controllers to review monthly revenue trajectory, invoicing cadence, units volume, and collection rates side-by-side across all 12 months on a single screen.',
            'validity' => 'Aggregates verified transactions in sales ledger by calendar month (YYYY-MM). Supports rolling 12 months or selectable calendar years (Jan–Dec).',
            'calc' => [
                'Gross Invoiced' => 'Σ total_amount billed in month',
                'Net Base' => 'Σ base_value recognized revenue before statutory tax',
                '18% VAT' => 'Σ vat_component statutory tax portion',
                'Collection Rate' => '(Σ Collected / Σ Gross) × 100%',
                'MoM Growth' => '((Current Month Gross - Prior Month Gross) / Prior Month Gross) × 100%'
            ]
        ],
        'quarterly' => [
            'title' => 'Quarterly Executive Summary Methodology',
            'badge' => 'Executive Review • Seasonality Analysis',
            'usage' => 'Used by executive management and the Board of Directors to evaluate mid-term fiscal performance, cyclical sales trends, and tax liability accruals.',
            'validity' => 'Aggregates transaction tranches across standard 3-month calendar quarters (Q1: Jan–Mar, Q2: Apr–Jun, Q3: Jul–Sep, Q4: Oct–Dec).',
            'calc' => [
                'Quarterly Base' => 'Σ base_value for all invoices where invoice_date falls in quarter',
                'Quarterly VAT' => 'Σ vat_component across all taxable quarterly invoices',
                'Quarterly Gross' => 'Σ total_amount billed to accounts in quarter'
            ]
        ],
        'yearly' => [
            'title' => 'Yearly Performance & Annual Growth Methodology',
            'badge' => 'Strategic Growth • Auditing Standard',
            'usage' => 'Evaluates multi-year compound annual growth, macro-level revenue trajectory, and annual financial auditing reconciliations.',
            'validity' => 'Analyzes verified historical commercial transactions spanning 2021 to present. Data normalized in LKR with consistent tax separation.',
            'calc' => [
                'Annual Gross' => 'Σ total_amount for all invoices within fiscal calendar year',
                'Annual Base' => 'Σ base_value recognized commercial revenue',
                'Monthly Average' => 'Annual Gross / 12 months'
            ]
        ],
        'matrix' => [
            'title' => 'Customer Performance Matrix (YoY Pivot) Methodology',
            'badge' => 'Channel Consistency • Seasonality Matrix',
            'usage' => 'Identifies recurring purchasing rhythms, accounts with seasonal buying patterns, and accounts experiencing revenue drops across 12 calendar months.',
            'validity' => 'Pivot table covers all active purchasing accounts for the selected year. Filterable by product category/brand, channel partner type, and assigned sales executive.',
            'calc' => [
                'Monthly Net' => 'Σ base_value partitioned by customer and calendar month (Jan–Dec)',
                'Annual Customer Total' => 'Σ base_value across all 12 months',
                'Volume Badge' => 'Count of valid distinct invoice dispatches in year'
            ]
        ],
        'stock' => [
            'title' => 'Stock Movement & FSN Inventory Velocity Methodology',
            'badge' => 'Supply Chain • Working Capital Optimization',
            'usage' => 'Classifies inventory velocity into Fast, Slow, and Non-Moving (FSN) tiers. Prevents stockouts of critical SKUs and highlights stagnant working capital tied up in dormant stock.',
            'validity' => 'Excludes QuickBooks placeholder headers ("Item") and zero-value rows. Retains viable serialized warranty dispatches and tangible physical unit shipments.',
            'calc' => [
                'Fast-Moving (F)' => 'Last dispatched ≤ 60 days ago AND (≥ 6 active dispatch months OR ≥ 20 units moved)',
                'Slow-Moving (S)' => 'Last dispatched ≤ 180 days ago AND ≥ 2 active dispatch months',
                'Non-Moving / Dormant (N)' => 'Last dispatched > 180 days ago OR single sporadic shipment',
                'Days Since Last Dispatch' => 'CAST(julianday(\'now\') - julianday(MAX(invoice_date)) AS INT)'
            ]
        ],
        'rfm' => [
            'title' => 'RFM Customer Segmentation & Churn Risk Methodology',
            'badge' => 'Customer Lifecycle • Churn Prevention',
            'usage' => 'Directs targeted account manager retention strategies, marketing campaigns, and VIP reward programs by segmenting clients based on transactional engagement.',
            'validity' => 'Calculated across active client accounts using verified invoice history. Updated continuously with new syncs.',
            'calc' => [
                'Recency (R)' => 'Score 3: ≤ 90 days | Score 2: 91–180 days | Score 1: > 180 days',
                'Frequency (F)' => 'Score 3: ≥ 10 orders | Score 2: 3–9 orders | Score 1: 1–2 orders',
                'Monetary (M)' => 'Score 3: > 10M LKR | Score 2: 1M–10M LKR | Score 1: < 1M LKR',
                'Champions / VIPs' => 'R ≥ 3 AND F ≥ 3 AND M ≥ 3 (Highest commercial retention priority)'
            ]
        ],
        'partners' => [
            'title' => 'Partner vs. End-Customer Cohort Analysis Methodology',
            'badge' => 'Channel Economics • B2B Distribution',
            'usage' => 'Compares commercial contribution between indirect channel partners (dealers, system integrators) and direct enterprise end-users to guide tier discounting and sales incentives.',
            'validity' => 'Mapped via customer account profiles and transactional classifications.',
            'calc' => [
                'Revenue Share %' => '(Segment Gross Billed / Total Platform Invoiced) × 100',
                'Average Order Value (AOV)' => 'Segment Total Revenue / Total Distinct Invoices',
                'Average Collection Period' => 'AVG(days_to_pay) for settled transactions in segment'
            ]
        ],
        'reps' => [
            'title' => 'Sales Rep Performance & Collection Health Methodology',
            'badge' => 'Sales Governance • DSO Accountability',
            'usage' => 'Evaluates individual sales executive productivity, quota attainment, client relationship diversity, and cash collection discipline.',
            'validity' => 'Mapped from QuickBooks transaction sales rep initials and internal team rosters.',
            'calc' => [
                'Gross Revenue' => 'Σ total_amount credited to representative',
                'Collected Revenue' => 'Σ total_amount where payment has been realized and cleared',
                'Collection Rate %' => '(Collected Revenue / Gross Revenue) × 100',
                'Average DSO' => 'ROUND(AVG(days_to_pay), 1) days from invoice date to settlement'
            ]
        ],
        'credit' => [
            'title' => 'Customer Credit Health & Scoring Index Methodology',
            'badge' => 'Credit Governance • Bad Debt Mitigation',
            'usage' => 'Informs credit term approvals (Advance payment vs Net 30/60 days), credit limit extensions, and collection escalation.',
            'validity' => 'Calculates real-time 0–100 index based on full historical settlement velocity and open uncollected accounts receivable exposure.',
            'calc' => [
                'Base Score' => '100 points maximum',
                'Turnaround Penalty' => 'Deducts 0.5 × max(0, avg_days_to_pay - 30) for late settlements',
                'Unpaid Debt Penalty' => 'Deducts 5.0 × unpaid_invoices_count',
                'Aging Severity Penalty' => 'Score reduced by 50% if customer has open invoices > 120 days overdue',
                'Tiers' => '85–100 (Excellent) | 70–84 (Good) | 50–69 (Fair) | 30–49 (At Risk) | 0–29 (Critical)'
            ]
        ],
        'aging' => [
            'title' => 'Accounts Receivable Aging & Overdue Analysis Methodology',
            'badge' => 'Treasury Liquidity • Default Containment',
            'usage' => 'Used by treasury and credit control teams to track uncollected balances, forecast cash flow, and initiate debt recovery proceedings.',
            'validity' => 'Evaluates all open invoices relative to today\'s date. $0 placeholder rows and cancelled entries are excluded so only genuine claims appear.',
            'calc' => [
                'Aging Days' => 'CAST(julianday(\'now\') - julianday(invoice_date) AS INT)',
                'Standard Tranches' => '0–30 Days (Current) | 31–60 Days | 61–90 Days | 91–180 Days | 181–365 Days | Over 1 Year',
                'Critical Alert' => 'Unpaid balances exceeding 90 days trigger high-risk credit review'
            ]
        ]
    ];

    $typeKey = $type;
    if ($type === 'monthly') {
        $view = $_GET['view'] ?? 'overview';
        if ($view === 'customer') {
            $typeKey = 'monthly_customer';
        } elseif ($view === 'rep') {
            $typeKey = 'monthly_rep';
        } else {
            $typeKey = 'monthly_overview';
        }
    }

    $guide = $guides[$typeKey] ?? $guides[$type] ?? null;
    if (!$guide) return;

    $cardId = 'methodology_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $typeKey);
    ?>
    <!-- Concept B: Minimized by Default Methodology Panel (0px vertical space until triggered by 'Method' button) -->
    <style>
        .methodology-panel-b {
            display: none;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
            margin-bottom: 16px;
            overflow: hidden;
            animation: methodologySlideDown 0.2s ease;
        }
        .methodology-header-b {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .methodology-body-b {
            padding: 16px 18px;
            background: #ffffff;
        }
        @keyframes methodologySlideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="methodology-card methodology-panel-b" id="reportMethodologyPanel">
        <div class="methodology-header-b">
            <div class="methodology-header-title">
                <i class="icon-calculator" style="color: #2563eb; font-size: 16px;"></i>
                <span style="font-weight: 700; color: #0f172a; font-size: 13.5px;"><?php echo htmlspecialchars($guide['title']); ?></span>
                <span class="methodology-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 10.5px; padding: 2px 8px; border-radius: 20px; font-weight: 600;"><?php echo htmlspecialchars($guide['badge']); ?></span>
            </div>
            <button type="button" onclick="toggleReportMethodology()" class="cmd-btn" style="font-size: 11.5px; padding: 4px 10px; border-radius: 5px; color: #64748b;" title="Minimize methodology">
                <i class="icon-x"></i> Close
            </button>
        </div>
        <div class="methodology-body-b">
            <div class="methodology-grid">
                <div class="methodology-col">
                    <div class="methodology-col-label usage"><i class="icon-target"></i> Business Usage</div>
                    <p><?php echo htmlspecialchars($guide['usage']); ?></p>
                </div>
                <div class="methodology-col">
                    <div class="methodology-col-label validity"><i class="icon-shield-check"></i> Scope & Validity</div>
                    <p><?php echo htmlspecialchars($guide['validity']); ?></p>
                </div>
                <div class="methodology-col">
                    <div class="methodology-col-label calc"><i class="icon-calculator"></i> Calculation Method & Formulas</div>
                    <?php foreach ($guide['calc'] as $label => $formula): ?>
                        <p>• <strong><?php echo htmlspecialchars($label); ?>:</strong> <span class="methodology-formula"><?php echo htmlspecialchars($formula); ?></span></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
