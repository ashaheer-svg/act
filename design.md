# ACTIVITY | BI — Enterprise Financial UI/UX Design System
> **Design Specification & Component Library**  
> Based on the Commercial Invoices & Master-Detail Audit Interface (`reports.php?type=invoices`)

---

## 1. Design Philosophy & Principles

1. **High Information Density with Maximum Legibility**:
   Financial executives and analysts need to scan thousands of transactions rapidly. Compact **28px table rows**, **10px uppercase tracking labels**, and **micro-badges** maximize data density without visual clutter.
2. **Tabular Number Alignment (Zero Shifting)**:
   All numerical columns and financial metrics MUST use `font-variant-numeric: tabular-nums` and `font-feature-settings: "tnum" 1`. Decimal points and commas line up strictly right-aligned across all rows.
3. **The 60-30-10 High-Contrast Light Theme**:
   - **60% Canvas** (`#f3f4f6` Cool Gray / Slate-100): Subtle off-white background that makes white card panels pop.
   - **30% Structural Panels** (`#ffffff` Pure White): Card surfaces, tables, command bars, and drawers with crisp `1px solid #e5e7eb` borders and soft diffuse shadows.
   - **10% Semantic Accents** (`#4f46e5` Royal Indigo, `#10b981` Emerald Settled, `#ef4444` Crimson Overdue, `#0284c7` Sky Blue, `#7c3aed` Purple).
4. **Natural Page Scrolling (No Inner Scroll Traps)**:
   Avoid hard `max-height: calc(100vh - ...)` on table containers. Tables must flow naturally with the window scrollbar so users can scroll pages smoothly with mouse wheels or trackpads.
5. **No Broken Controls or Cut-off Dropdowns**:
   Command bar elements must have proportional `max-width` limits and zero horizontal scrollbar leaks (`scrollbar-width: none !important;`).

---

## 2. Design Tokens & Variables

Add or reference these CSS custom properties from [`layout.css`](file:///c:/Users/shahe/OneDrive/working/ai-sales/layout.css):

```css
:root {
    /* Canvas & Surfaces */
    --bg-main: #f3f4f6;          /* Outer page background */
    --card-bg: #ffffff;          /* Card & panel surface */
    --surface-subtle: #f8fafc;   /* Table header & secondary backgrounds */
    --border-color: #e5e7eb;     /* Primary panel borders */
    --border-divider: #f1f5f9;   /* Table row dividers */

    /* Typography & Contrast */
    --text-main: #0f172a;        /* High-contrast near-black (Slate-900) */
    --text-muted: #64748b;       /* Secondary labels & subtitles (Slate-500) */
    --text-light: #94a3b8;       /* Placeholders & disabled text */

    /* Navigation & Shell */
    --sidebar-bg: #0b1121;       /* Deep Midnight Blue */
    --sidebar-active: #4f46e5;   /* Active indigo indicator */
    --sidebar-width: 180px;      /* Standard expanded sidebar */
    --sidebar-collapsed-width: 50px;
    --header-height: 40px;

    /* Semantic Action Accents */
    --primary: #4f46e5;          /* Royal Indigo (Key actions & active links) */
    --success: #10b981;          /* Emerald Green (Settled, Active, Positive) */
    --warning: #f59e0b;          /* Amber (Due soon, Attention, Pending) */
    --danger: #ef4444;           /* Crimson Red (Overdue, Critical, Credit) */
    --info: #0284c7;             /* Sky Blue (Secondary tags, S/N, Info) */

    /* Radii */
    --radius-sm: 4px;            /* Badges, inputs, buttons */
    --radius-md: 6px;            /* Cards, command bar, table container */
    --radius-lg: 8px;            /* Modals, large panels */

    /* Multi-layered Diffuse Shadows */
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
    --shadow-md: 0 8px 16px -2px rgba(0, 0, 0, 0.06), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --shadow-lg: 0 16px 24px -4px rgba(0, 0, 0, 0.08), 0 8px 12px -4px rgba(0, 0, 0, 0.04);
}
```

---

## 3. Typography Standards

| Element | Font Family | Size | Weight | Line Height | Case / Tracking | Example Usage |
|---|---|---|---|---|---|---|
| **Page Title** | `'Inter Tight', sans-serif` | `20px` | `800` | `1.2` | Regular / `-0.3px` | "Commercial Invoices", "Brand Performance" |
| **Section Header** | `'Inter Tight', sans-serif` | `15px` | `700` | `1.3` | Regular | Card headers, modal titles |
| **KPI Large Value** | `'Inter Tight', sans-serif` | `16px–18px` | `800` | `1.1` | Tabular Nums / `-0.2px` | `LKR 1,330,923,696`, `77,462` |
| **KPI Label** | `'Inter', sans-serif` | `10px` | `700` | `1.2` | Uppercase / `+0.04em` | `TOTAL INVOICES`, `GROSS BILLED` |
| **KPI Subtitle** | `'Inter', sans-serif` | `10.5px` | `500` | `1.2` | Regular | "Unique Clients", "IRD Tax Liability" |
| **Table Header** | `'Inter', sans-serif` | `10px` | `700` | `1.2` | Uppercase / `+0.04em` | `DATE`, `INVOICE #`, `CUSTOMER`, `GROSS` |
| **Table Data (Text)** | `'Inter', sans-serif` | `11.5px` | `600` | `1.25` | Regular | Customer name, product description |
| **Table Data (Numbers)** | `'Inter', sans-serif` | `11.5px` | `600–700` | `1.25` | Tabular Nums | Currency amounts, quantities, days |
| **Document # Code** | `'Inter', sans-serif` | `11.5px` | `700` | `1.2` | Tabular / Color `--primary` | `AS012345`, `INV-2026-001` |
| **Micro Badge** | `'Inter', sans-serif` | `9.5px` | `700` | `1.2` | Uppercase | `PAID`, `UNPAID`, `+VAT`, `CR`, `S/N` |

---

## 4. Component Library & Markup Blueprint

### Component 1: 38px Unified Command Bar (`.command-bar`)
The standard control bar placed at the top of every dashboard or report page.

```html
<div class="command-bar">
    <!-- Left: Primary Selectors & Filters -->
    <div class="cmd-left">
        <!-- View Switcher -->
        <div class="cmd-group">
            <span class="cmd-label"><i class="icon-sliders"></i> View:</span>
            <select class="cmd-select cmd-select-report" onchange="window.location.href='?type='+this.value">
                <option value="invoices" selected>Commercial Invoices</option>
                <option value="unpaid">Unpaid Invoices</option>
                <option value="brand_growth">Brand & Category</option>
            </select>
        </div>

        <!-- Filter 1 -->
        <div class="cmd-group">
            <span class="cmd-label">Breakdown:</span>
            <select class="cmd-select" onchange="location.href='?view_mode='+this.value">
                <option value="brand">By Brand</option>
                <option value="category">By Category</option>
                <option value="matrix">Matrix</option>
            </select>
        </div>

        <!-- Filter 2 -->
        <div class="cmd-group">
            <span class="cmd-label">Year:</span>
            <select class="cmd-select" onchange="location.href='?year='+this.value">
                <option value="all">All Years</option>
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
            </select>
        </div>
    </div>

    <!-- Right: Action Buttons (Method, Print, Export) -->
    <div class="cmd-right">
        <button type="button" class="cmd-btn" onclick="toggleMethodology()" title="Report Methodology">
            <i class="icon-help-circle"></i> Method
        </button>
        <button type="button" class="cmd-btn" onclick="window.print()" title="Print / PDF Brief">
            <i class="icon-printer"></i> Print
        </button>
        <a href="?export=csv" class="cmd-btn" title="Download CSV">
            <i class="icon-download"></i> CSV
        </a>
    </div>
</div>
```

```css
/* Styling Rules */
.command-bar {
    height: 38px;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 10px;
    box-shadow: var(--shadow-sm);
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}
.command-bar::-webkit-scrollbar { display: none !important; }

.cmd-left, .cmd-right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.cmd-group {
    display: flex;
    align-items: center;
    gap: 3px;
}
.cmd-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.cmd-select {
    height: 26px;
    font-size: 11.5px;
    font-weight: 600;
    font-family: inherit;
    color: var(--text-main);
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 2px 6px;
    outline: none;
    max-width: 175px;
}
.cmd-select-report {
    width: 175px;
    max-width: 220px;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}
.cmd-btn {
    height: 26px;
    padding: 0 8px;
    font-size: 11px;
    font-weight: 600;
    font-family: inherit;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid var(--border-color);
    background: #f8fafc;
    color: var(--text-main);
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.cmd-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
```

---

### Component 2: High-Density Financial Metrics Ribbon (`.metrics-strip`)
Displays 4–5 core aggregated financial indicators across a horizontal auto-fit grid.

```html
<div class="metrics-strip">
    <!-- Metric Pill 1: Primary Count -->
    <div class="metric-pill">
        <span class="metric-pill-label">Total Invoices</span>
        <span class="metric-pill-val">12,410</span>
        <span class="metric-pill-sub">418 Unique Clients</span>
    </div>

    <!-- Metric Pill 2: Gross Billed -->
    <div class="metric-pill">
        <span class="metric-pill-label">Gross Billed</span>
        <span class="metric-pill-val">LKR 1,330,923,696</span>
        <span class="metric-pill-sub">77,462 Units Dispatched</span>
    </div>

    <!-- Metric Pill 3: Net Recognized Base -->
    <div class="metric-pill">
        <span class="metric-pill-label">Net Base (Pre-VAT)</span>
        <span class="metric-pill-val">LKR 1,127,901,437</span>
        <span class="metric-pill-sub">Core Recognized Revenue</span>
    </div>

    <!-- Metric Pill 4: Statutory Tax -->
    <div class="metric-pill">
        <span class="metric-pill-label">Statutory 18% VAT</span>
        <span class="metric-pill-val" style="color: var(--primary);">LKR 203,022,259</span>
        <span class="metric-pill-sub">IRD Tax Liability</span>
    </div>

    <!-- Metric Pill 5: Dual Settlement Status -->
    <div class="metric-pill">
        <span class="metric-pill-label">Settlement Realization</span>
        <span class="metric-pill-val" style="color: #15803d; font-size: 13.5px;">
            Settled: LKR 1,195,430,280
        </span>
        <span class="metric-pill-sub" style="color: #b91c1c; font-weight: 600;">
            Unpaid: LKR 135,493,416 (76 inv)
        </span>
    </div>
</div>
```

```css
/* Styling Rules */
.metrics-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 10px;
}
.metric-pill {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-sm);
}
.metric-pill-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.04em;
}
.metric-pill-val {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-main);
    margin-top: 2px;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.2px;
}
.metric-pill-sub {
    font-size: 10.5px;
    color: var(--text-muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
```

---

### Component 3: Secondary Quick Filter Bar
Placed directly beneath the metrics ribbon to filter table data by dimensions without reloading the entire page layout.

```html
<form method="GET" action="" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; flex-wrap: wrap;">
    <input type="hidden" name="type" value="invoices">

    <!-- Search Input -->
    <div style="display: flex; align-items: center; gap: 4px;">
        <span class="cmd-label">Search:</span>
        <input type="text" name="search" value="" placeholder="Search invoices, clients, PO..." class="cmd-input" style="width: 180px;">
    </div>

    <!-- Dropdown Filter -->
    <div style="display: flex; align-items: center; gap: 4px;">
        <span class="cmd-label">Brand:</span>
        <select name="brand" class="cmd-select" onchange="this.form.submit()">
            <option value="">All Brands</option>
            <option value="Synology">Synology</option>
            <option value="Seagate">Seagate</option>
        </select>
    </div>

    <!-- Sort Selector -->
    <div style="display: flex; align-items: center; gap: 4px;">
        <span class="cmd-label">Sort:</span>
        <select name="sort" class="cmd-select" onchange="this.form.submit()">
            <option value="date_desc">Newest First</option>
            <option value="amount_desc">Amount (High to Low)</option>
        </select>
    </div>

    <!-- Reset Link (Shown only when active filters exist) -->
    <a href="?type=invoices" class="cmd-btn" style="height: 24px; font-size: 10.5px;">Reset Filters</a>
</form>
```

---

### Component 4: High-Density Rational Table (`table.rational-table`)
The core data grid system. Features sticky headers, 28px rows, tabular numbers, and micro-badges.

```html
<div class="table-dense-container">
    <div style="overflow-x: auto;">
        <table class="rational-table">
            <thead>
                <tr>
                    <th style="width: 76px;">Date</th>
                    <th style="width: 98px;">Invoice #</th>
                    <th>Customer</th>
                    <th style="width: 44px;">Rep</th>
                    <th style="width: 80px;">PO #</th>
                    <th style="width: 140px;">Data Summary</th>
                    <th class="text-right" style="width: 95px;">Base Net</th>
                    <th class="text-right" style="width: 85px;">18% VAT</th>
                    <th class="text-right" style="width: 110px;">Gross Total</th>
                    <th class="text-center" style="width: 68px;">Status</th>
                    <th class="text-center" style="width: 55px;">Audit</th>
                </tr>
            </thead>
            <tbody>
                <!-- Selected / Clickable Row -->
                <tr class="row-selected" onclick="selectRow('AS010867', this)">
                    <td style="color: var(--text-muted); font-size: 11px;">2026-01-15</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <span class="dense-doc-num">AS010867</span>
                            <span class="dense-badge dense-badge-sn" title="Hardware Serial Numbers Registered">S/N</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;">
                            Avian Technologies (Pvt) Ltd
                        </div>
                        <div style="font-size: 10.5px; color: #047857; font-weight: 600; display: flex; align-items: center; gap: 3px; margin-top: 1px;">
                            <i class="icon-briefcase" style="font-size: 9px;"></i>
                            <span>Dialog Axiata PLC</span>
                        </div>
                    </td>
                    <td><span style="font-size: 11px; color: #475569;">AS-01</span></td>
                    <td><span style="font-size: 10.5px; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; color: #334155;">PO-99214</span></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <span style="font-size: 10.5px; font-weight: 600; color: #475569;">3 itm</span>
                            <span class="dense-badge dense-badge-hw">HW:2</span>
                            <span class="dense-badge dense-badge-ma">MA:1</span>
                        </div>
                    </td>
                    <td class="text-right dense-num" style="color: #475569;">1,250,000</td>
                    <td class="text-right dense-num" style="color: #64748b;">225,000</td>
                    <td class="text-right dense-num-bold dense-num">
                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                            <span>1,475,000</span>
                            <span class="dense-badge dense-badge-plusvat">+VAT</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="dense-badge dense-badge-settled">Paid</span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">
                            Audit
                        </button>
                    </td>
                </tr>

                <!-- Unpaid Row -->
                <tr onclick="selectRow('AS010868', this)">
                    <td style="color: var(--text-muted); font-size: 11px;">2026-01-20</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <span class="dense-doc-num">AS010868</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;">
                            Brandix Apparel Limited
                        </div>
                    </td>
                    <td><span style="font-size: 11px; color: #475569;">AS-04</span></td>
                    <td><span style="color: #cbd5e1;">—</span></td>
                    <td><span style="font-size: 10.5px; font-weight: 600; color: #475569;">1 itm</span></td>
                    <td class="text-right dense-num" style="color: #475569;">450,000</td>
                    <td class="text-right dense-num" style="color: #64748b;">81,000</td>
                    <td class="text-right dense-num-bold dense-num">531,000</td>
                    <td class="text-center">
                        <span class="dense-badge dense-badge-unpaid">Unpaid</span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="cmd-btn" style="height: 20px; padding: 0 6px; font-size: 10px;">
                            Audit
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

```css
/* Table Styles */
.table-dense-container {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
table.rational-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 11.5px;
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum" 1;
    color: var(--text-main);
    line-height: 1.25;
}
table.rational-table thead th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
    text-align: left;
}
table.rational-table thead th.text-right { text-align: right; }
table.rational-table thead th.text-center { text-align: center; }

table.rational-table tbody tr {
    height: 28px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.1s ease;
}
table.rational-table tbody tr:hover { background: #f8fafc; }
table.rational-table tbody tr.row-selected {
    background: #eff6ff !important;
    border-left: 3px solid #4f46e5;
}
table.rational-table tbody td {
    padding: 4px 8px;
    white-space: nowrap;
    vertical-align: middle;
}
table.rational-table tbody td.text-right { text-align: right; }
table.rational-table tbody td.text-center { text-align: center; }

.dense-num {
    font-family: 'Inter', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum" 1;
    font-weight: 600;
}
.dense-num-bold {
    font-weight: 700;
    color: var(--text-main);
}
.dense-doc-num {
    font-family: 'Inter', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: var(--primary);
    letter-spacing: -0.2px;
}
```

---

### Component 5: Semantic Micro-Badge System (`.dense-badge`)
Compact 9.5px status tags used for document types, hardware indicators, and payment states.

| Badge Class | Purpose | Background | Color / Border | Example Code |
|---|---|---|---|---|
| `.dense-badge-settled` | Paid / Reconciled | `#ecfdf5` | `#10b981` (Emerald) | `<span class="dense-badge dense-badge-settled">Paid</span>` |
| `.dense-badge-unpaid` | Unpaid / Pending | `#fffbeb` | `#b45309` (Amber) | `<span class="dense-badge dense-badge-unpaid">Unpaid</span>` |
| `.dense-badge-credit` | Credit Memo | `#fee2e2` | `#b91c1c` (Crimson) | `<span class="dense-badge dense-badge-credit">CR</span>` |
| `.dense-badge-hw` | Hardware Count | `#eff6ff` | `#1d4ed8` (Blue) | `<span class="dense-badge dense-badge-hw">HW:3</span>` |
| `.dense-badge-ma` | Maintenance / SLA | `#f0fdf4` | `#15803d` (Green) | `<span class="dense-badge dense-badge-ma">MA:1</span>` |
| `.dense-badge-sn` | Serial Numbers Tag | `#e0e7ff` | `#3730a3` (Indigo) | `<span class="dense-badge dense-badge-sn">S/N</span>` |
| `.dense-badge-plusvat` | Statutory Plus VAT | `#f3e8ff` | `#6b21a8` / `1px solid #d8b4fe` | `<span class="dense-badge dense-badge-plusvat">+VAT</span>` |
| `.dense-badge-inclusive` | VAT Inclusive | `#f1f5f9` | `#475569` / `1px solid #cbd5e1` | `<span class="dense-badge dense-badge-inclusive">INCL</span>` |

```css
/* Micro Badge CSS */
.dense-badge {
    font-size: 9.5px;
    font-weight: 700;
    padding: 1.5px 5px;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    line-height: 1.2;
    text-transform: uppercase;
}
.dense-badge-hw { background: #eff6ff; color: #1d4ed8; }
.dense-badge-ma { background: #f0fdf4; color: #15803d; }
.dense-badge-sn { background: #e0e7ff; color: #3730a3; }
.dense-badge-vat { background: #f1f5f9; color: #475569; }
.dense-badge-plusvat { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; font-weight: 700; }
.dense-badge-inclusive { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.dense-badge-settled { background: #ecfdf5; color: #10b981; }
.dense-badge-unpaid { background: #fffbeb; color: #b45309; }
.dense-badge-credit { background: #fee2e2; color: #b91c1c; }
```

---

### Component 6: Master-Detail Split Layout & Audit Drawer (`.split-layout`)
Allows selecting an item on the master table to instantly load line-item details, contract terms, or audit trails without leaving the view.

```html
<div class="split-layout" id="splitLayout">
    <!-- Master Table Column -->
    <div class="split-grid">
        <div class="split-table-wrapper">
            <table class="rational-table">...</table>
        </div>
        <!-- Pagination Footer -->
        <div class="split-pagination">...</div>
    </div>

    <!-- Collapsible Side Detail Drawer -->
    <div class="split-drawer drawer-collapsed" id="sideAuditDrawer">
        <div class="drawer-header">
            <div class="drawer-title-area">
                <i class="icon-file-text" style="color: #818cf8; font-size: 13px;"></i>
                <span class="drawer-title" id="drawerTitle">Invoice Audit</span>
            </div>
            <div class="drawer-controls">
                <button type="button" class="drawer-ctrl-btn" onclick="toggleDrawerFullscreen()" title="Toggle Fullscreen Inspector">
                    <i class="icon-maximize-2" id="drawerExpandIcon"></i> Full
                </button>
                <button type="button" class="drawer-ctrl-btn" onclick="closeDrawer()" title="Close Drawer">
                    <i class="icon-x"></i>
                </button>
            </div>
        </div>
        <div class="drawer-body" id="drawerBody">
            <!-- AJAX Line Items, Serials & Financials Render Here -->
        </div>
    </div>
</div>
```

```css
/* Split Layout & Drawer CSS */
.split-layout {
    display: flex;
    gap: 10px;
    position: relative;
    align-items: flex-start;
}
.split-grid {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}
.split-drawer {
    width: 440px;
    max-width: 45vw;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: width 0.2s ease, opacity 0.2s ease;
    flex-shrink: 0;
    position: sticky;
    top: 14px;
    max-height: calc(100vh - 28px);
}
.split-drawer.drawer-collapsed { display: none; }
.split-drawer.drawer-fullscreen {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100vw; max-width: 100vw; height: 100vh;
    z-index: 3000;
    border-radius: 0; border: none;
}
.drawer-header {
    height: 38px;
    background: #0b1121;
    color: #ffffff;
    padding: 0 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.drawer-title {
    font-size: 12px;
    font-weight: 700;
    color: #ffffff;
}
.drawer-ctrl-btn {
    background: rgba(255, 255, 255, 0.12);
    border: none;
    color: #ffffff;
    height: 24px;
    padding: 0 6px;
    border-radius: 3px;
    font-size: 11px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    transition: background 0.15s ease;
}
.drawer-ctrl-btn:hover { background: rgba(255, 255, 255, 0.22); }
.drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    background: #f8fafc;
}
```

---

### Component 7: Modern Floating Pagination Rail (`.split-pagination`)
Consistent footer control for paginated tables with record counters and jump navigation.

```html
<div class="split-pagination">
    <!-- Left: Record Counter -->
    <div>
        Showing <strong>1</strong> – <strong>25</strong> of <strong>12,410</strong> invoices
    </div>

    <!-- Right: Page Controls -->
    <div style="display: flex; align-items: center; gap: 6px;">
        <a href="?p=1" class="cmd-btn" style="height: 22px; padding: 0 6px;" title="First Page">
            <i class="icon-chevrons-left"></i>
        </a>
        <a href="?p=1" class="cmd-btn" style="height: 22px; padding: 0 6px;" title="Previous Page">
            <i class="icon-chevron-left"></i>
        </a>
        <span style="font-weight: 600; color: var(--text-main);">Page 1 of 497</span>
        <a href="?p=2" class="cmd-btn" style="height: 22px; padding: 0 6px;" title="Next Page">
            <i class="icon-chevron-right"></i>
        </a>
        <a href="?p=497" class="cmd-btn" style="height: 22px; padding: 0 6px;" title="Last Page">
            <i class="icon-chevrons-right"></i>
        </a>
    </div>
</div>
```

---

## 5. Responsive Behavior & Screen Resolutions

| Viewport Width | Sidebar Behavior | Command Bar | Table Behavior | Split Drawer |
|---|---|---|---|---|
| **&ge; 1440px (Desktop Full)** | Expanded (180px) | Single row, all options visible | Full column width | Side-by-side (440px) |
| **1280px (Standard Laptop)** | Expanded (180px) | Single row, auto-ellipsis select | Natural horizontal scroll if needed | Side-by-side (380px) |
| **1024px (Tablet Landscape)** | Collapsed (50px icon rail) | Compact buttons | Scroll wrapper | Converts to bottom or overlay |
| **&le; 768px (Mobile)** | Collapsed / Off-canvas drawer | Stacks in 2 rows | Card / vertical stacked rows | Fullscreen modal overlay |

---

## 6. Page Construction Template (Copy-Paste Starter)

Use this complete boilerplate whenever creating a new report or operational view in the application:

```php
<?php
require_once 'config.php';
require_once 'classes/Auth.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$auth = new Auth();
$auth->requireLogin();
$user = $auth->getCurrentUser();

$db = new Database();
$reports = new Reports($db);
$currency = 'LKR ';
$reportTitle = 'My New Financial Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($reportTitle); ?> - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Inter+Tight:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=2.5.0">
    <style>
        .command-bar {
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .command-bar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- 1. Shared Left Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- 2. Main Wrapper -->
        <main class="main-wrapper">
            <!-- 3. Top Header Bar -->
            <?php $searchPlaceholder = 'Search report...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <!-- 4. Unified 38px Command Bar -->
                <div class="command-bar">
                    <div class="cmd-left">
                        <div class="cmd-group">
                            <span class="cmd-label"><i class="icon-sliders"></i> View:</span>
                            <select class="cmd-select cmd-select-report" onchange="location.href='reports.php?type='+this.value">
                                <option value="invoices">Commercial Invoices</option>
                                <option value="my_page" selected>My New Financial Report</option>
                            </select>
                        </div>
                    </div>
                    <div class="cmd-right">
                        <button type="button" class="cmd-btn" onclick="window.print()"><i class="icon-printer"></i> Print</button>
                        <a href="?export=csv" class="cmd-btn"><i class="icon-download"></i> CSV</a>
                    </div>
                </div>

                <!-- 5. High-Density Financial Metrics Ribbon -->
                <div class="metrics-strip">
                    <div class="metric-pill">
                        <span class="metric-pill-label">Metric 1</span>
                        <span class="metric-pill-val">1,240</span>
                        <span class="metric-pill-sub">Subtitle notes</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Metric 2</span>
                        <span class="metric-pill-val"><?= $currency; ?>145,000,000</span>
                        <span class="metric-pill-sub">Gross realization</span>
                    </div>
                </div>

                <!-- 6. Master Table (Natural Window Scrolling) -->
                <div class="table-dense-container">
                    <div style="overflow-x: auto;">
                        <table class="rational-table">
                            <thead>
                                <tr>
                                    <th>Column 1</th>
                                    <th>Column 2</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Value 1</td>
                                    <td>Value 2</td>
                                    <td class="text-right dense-num"><?= $currency; ?>120,000</td>
                                    <td class="text-center"><span class="dense-badge dense-badge-settled">Active</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
```

---

## 7. Design System Quality Checklist

Before finalizing any new page, verify against this checklist:

- [ ] **Tabular Nums**: Numerical table columns and financial values use `font-variant-numeric: tabular-nums`.
- [ ] **No Inner Scroll Trap**: Table container does NOT have `max-height: calc(...)` trapping scrolling inside a box.
- [ ] **No Command Bar Scrollbars**: `.command-bar` has `scrollbar-width: none !important;` with hidden webkit scrollbar.
- [ ] **Alignment Consistency**: Text left-aligned, monetary values right-aligned, status badges centered.
- [ ] **Semantic Color Palette**: Green for Settled/Paid/Active, Amber for Pending/Due soon, Red for Overdue/Credit, Indigo for primary actions.
- [ ] **Clean Truncation**: Wide text fields (customer names, descriptions) have `max-width` with `overflow: hidden; text-overflow: ellipsis; white-space: nowrap;` and a tooltip `title=""`.
- [ ] **Print Ready**: Backgrounds and borders print cleanly without clipping or dark bars.
