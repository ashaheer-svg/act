<?php
/**
 * Report Methodology & Interpretation Guide Component
 * Provides comprehensive documentation on Business Usage, Scope & Validity, and Calculation Methods.
 */

function renderReportMethodology($type, $currency = 'LKR ') {
    $guides = [
        'monthly' => [
            'title' => 'Monthly Sales Performance Methodology',
            'badge' => 'Tactical Trading • IRD Tax Reconciliation',
            'usage' => 'Tracks short-term sales velocity, operational revenue targets, and Inland Revenue Department (IRD) monthly VAT commitments for statutory tax reporting.',
            'validity' => 'Scope covers all finalized commercial invoices within the selected calendar month in Sri Lanka Rupees (LKR). QuickBooks placeholder rows ("Item", zero-value memos) are excluded; valid serialized warranty replacements are preserved.',
            'calc' => [
                'Base Net Revenue' => 'Σ base_value = total_amount / (1 + 0.18)',
                'Statutory 18% VAT' => 'Σ vat_component = total_amount - base_value',
                'Gross Invoiced' => 'Σ total_amount (total legal claim)'
            ]
        ],
        'quarterly' => [
            'title' => 'Quarterly Executive Summary Methodology',
            'badge' => 'Executive Review • Seasonality Analysis',
            'usage' => 'Used by executive management and the Board of Directors to evaluate mid-term fiscal performance, cyclical sales trends, and tax liability accruals.',
            'validity' => 'Aggregates transaction tranches across standard 3-month calendar quarters (Q1: Jan–Mar, Q2: Apr–Jun, Q3: Jul–Sep, Q4: Oct–Dec). Excludes cancelled transactions and zero-amount description lines.',
            'calc' => [
                'Quarterly Base' => 'Σ base_value for all invoices where invoice_date falls in the quarter',
                'Quarterly VAT' => 'Σ vat_component across all taxable quarterly invoices',
                'Quarterly Gross' => 'Σ total_amount billed to accounts in the quarter'
            ]
        ],
        'yearly' => [
            'title' => 'Yearly Performance & Annual Growth Methodology',
            'badge' => 'Strategic Growth • Auditing Standard',
            'usage' => 'Evaluates multi-year compound annual growth, macro-level revenue trajectory, and annual financial auditing reconciliations.',
            'validity' => 'Analyzes verified historical commercial transactions spanning 2021 to present. Data normalized in LKR with consistent tax separation.',
            'calc' => [
                'Annual Gross' => 'Σ total_amount for all invoices within the fiscal calendar year',
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
                'Volume Badge' => 'Count of valid distinct invoice dispatches in the year'
            ]
        ],
        'stock' => [
            'title' => 'Stock Movement & FSN Inventory Velocity Methodology',
            'badge' => 'Supply Chain • Working Capital Optimization',
            'usage' => 'Classifies inventory velocity into Fast, Slow, and Non-Moving (FSN) tiers. Prevents stockouts of critical SKUs and highlights stagnant working capital tied up in dormant stock.',
            'validity' => 'Excludes QuickBooks placeholder headers ("Item"), narrative comment rows, and zero-value date ranges. Retains viable serialized warranty dispatches and tangible physical unit shipments.',
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
            'validity' => 'Calculated across all 648 active client accounts using verified invoice history. Updated continuously with new syncs.',
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
            'validity' => 'Mapped via customer account profiles and transactional classifications. Invoices with $0 value are filtered out.',
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
            'validity' => 'Mapped from QuickBooks transaction sales rep initials (rep codes) and internal team rosters.',
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
        ],
        'invoices' => [
            'title' => 'Invoice Summary & Line-Item Audit Methodology',
            'badge' => 'Document Audit • Tax Invoice Integrity',
            'usage' => 'Used by billing auditors, credit controllers, and sales managers to verify commercial invoice document totals, tax breakdowns (base net vs 18% VAT), serial number dispatches, and matched payment receipts.',
            'validity' => 'Aggregates verified line items by QuickBooks Invoice Number in Sri Lanka Rupees (LKR). Non-viable 0-value placeholder descriptions are suppressed while genuine serialized warranty dispatches are preserved.',
            'calc' => [
                'Invoice Base Net' => 'Σ s.base_value = total_amount / (1 + 0.18)',
                'Statutory 18% VAT' => 'Σ s.vat_component = total_amount - base_value',
                'Invoice Gross Total' => 'Σ s.total_amount for all line items under the invoice number',
                'Settlement Status' => 'Settled if paid_date is recorded in sales ledger or matched payments >= gross; otherwise Unpaid / Balance Due'
            ]
        ],
        'warranties' => [
            'title' => 'Hardware Asset & Serial Number Warranty Registry Methodology',
            'badge' => 'Asset Lifecycle • RMA & Service Contract SLA',
            'usage' => 'Tracks individual hardware asset units (Synology NAS, BDCOM switches, Seagate enterprise drives) by discrete serial number, monitoring active warranty windows and upcoming expirations.',
            'validity' => 'Assets extracted from QuickBooks multi-line invoice descriptions via AI normalization. Units are linked to customer profiles and originating invoice numbers.',
            'calc' => [
                'Days Remaining' => 'julianday(warranty_expiry_date) - julianday(CURRENT_DATE)',
                'Expiring Soon' => 'Expiry within 30, 60, or 90 days from today',
                'Status' => 'ACTIVE if expiry >= today; EXPIRED if expiry < today'
            ]
        ],
        'renewals' => [
            'title' => 'Software License & SaaS Subscription Pipeline Methodology',
            'badge' => 'Recurring Revenue • SaaS Renewal Pipeline',
            'usage' => 'Manages recurring license renewals (Acronis Cyber Protect, ESET, Microsoft SaaS) and service contracts to eliminate missed subscription renewals and forecast recurring revenue.',
            'validity' => 'Derived from normalized line items with service coverage periods (start date → end date) and contracted seat tiers.',
            'calc' => [
                'Renewal Opportunity Value' => 'Historical contracted software gross amount or annual subscription rate',
                'Renewal Due Soon' => 'Subscription period ending within 60 days',
                'Monthly Pipeline' => 'Σ opportunity value grouped by expiration calendar month'
            ]
        ]
    ];

    $guide = $guides[$type] ?? null;
    if (!$guide) return;

    $cardId = 'methodology_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $type);
    ?>
    <div class="methodology-card">
        <div class="methodology-header" onclick="toggleMethodology('<?php echo $cardId; ?>')" title="Click to view or hide calculation logic & formulas">
            <div class="methodology-header-title">
                <i class="icon-calculator" style="color: var(--sidebar-active);"></i>
                <span><?php echo htmlspecialchars($guide['title']); ?></span>
                <span class="methodology-badge"><?php echo htmlspecialchars($guide['badge']); ?></span>
            </div>
            <div class="methodology-action">
                <span class="methodology-toggle-text" id="text_<?php echo $cardId; ?>">Show Logic & Formulas</span>
                <span class="methodology-toggle-icon" id="icon_<?php echo $cardId; ?>">▶</span>
            </div>
        </div>
        <div class="methodology-body collapsed" id="body_<?php echo $cardId; ?>">
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
