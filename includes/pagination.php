<?php
/**
 * Modern Report Pagination Component (Concept B: Modern Floating Rail)
 *
 * Standardized across all business reports with minimized Lucide chevrons,
 * smart window truncation (1 ... 14 [15] 16 ... 497), direct page jumping,
 * per-page limit selector (25 | 50 | 100 | All), and dedicated Print All controls.
 *
 * @param int $currentPage Current 1-based page number
 * @param int $totalPages Total number of pages
 * @param int $totalRecords Total count of records across dataset
 * @param int|string $limit Items per page or 999999/'all'
 * @param string $baseUrl Base URL with existing filter query params (without &p= or &limit=)
 * @param string $itemLabel Plural noun describing items (e.g. 'invoices', 'accounts', 'units')
 * @return string Rendered HTML
 */
function renderPaginationRail($currentPage, $totalPages, $totalRecords, $limit, $baseUrl, $itemLabel = 'records') {
    if ($totalRecords <= 0) {
        return '';
    }

    static $cssRendered = false;

    // Determine if all entries are requested or all records fit on one view
    $isExplicitAll = ($limit === 'all' || (int)$limit >= 999999 || (isset($_GET['limit']) && $_GET['limit'] === 'all') || !empty($_GET['show_all']));
    $isAll = $isExplicitAll || ($totalRecords <= (int)$limit);

    // Clean baseUrl of any existing pagination/limit/print/show_all params
    $cleanBaseUrl = preg_replace('/([?&])(p|limit|show_all|print)=[^&]*/i', '', $baseUrl);
    $cleanBaseUrl = preg_replace('/[?&]+$/', '', $cleanBaseUrl);
    $cleanBaseUrl = preg_replace('/([?&])&+/', '$1', $cleanBaseUrl);
    $delim = (strpos($cleanBaseUrl, '?') === false) ? '?' : '&';

    $currentPage = max(1, (int)$currentPage);
    $totalPages = max(1, (int)$totalPages);

    if ($isExplicitAll) {
        $currentPage = 1;
        $totalPages = 1;
        $start = 1;
        $end = $totalRecords;
    } else {
        $numLimit = max(1, (int)$limit);
        $start = max(1, ($currentPage - 1) * $numLimit + 1);
        $end = min($totalRecords, $currentPage * $numLimit);
    }

    // Page limit parameter for page number navigation
    $pageLimitParam = (!$isExplicitAll && (int)$limit !== 25 && (int)$limit < 999999) ? 'limit=' . (int)$limit . '&' : '';

    // Calculate smart window of pages (only needed if paginated)
    $pagesToShow = [];
    if (!$isAll && $totalPages > 1) {
        if ($totalPages <= 7) {
            for ($i = 1; $i <= $totalPages; $i++) {
                $pagesToShow[] = $i;
            }
        } elseif ($currentPage <= 4) {
            $pagesToShow = [1, 2, 3, 4, 5, '...', $totalPages];
        } elseif ($currentPage >= $totalPages - 3) {
            $pagesToShow = [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
        } else {
            $pagesToShow = [1, '...', $currentPage - 1, $currentPage, $currentPage + 1, '...', $totalPages];
        }
    }

    $isFirst = ($currentPage <= 1);
    $isLast = ($currentPage >= $totalPages);
    $prevPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    ob_start();
    ?>
    <?php if (!$cssRendered): $cssRendered = true; ?>
    <style>
    .pagination-rail {
        padding: 10px 16px;
        background: #ffffff;
        border-top: 1px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
        user-select: none;
    }
    .pg-summary {
        font-size: 12px;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .pg-summary strong {
        color: #0f172a;
        font-weight: 700;
    }
    .pg-summary .pg-total-badge {
        background: #f1f5f9;
        color: #334155;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 4px;
        border: 1px solid #e2e8f0;
    }
    .pg-summary .pg-badge-all {
        background: #ecfdf5 !important;
        color: #065f46 !important;
        border-color: #a7f3d0 !important;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .pg-actions-wrap {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .pg-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pg-btn {
        height: 28px;
        min-width: 28px;
        padding: 0 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        user-select: none;
        transition: all 0.12s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .pg-btn:hover:not(.disabled):not(.active) {
        background: #f8fafc;
        color: #0f172a;
        border-color: #94a3b8;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    }
    .pg-btn:active:not(.disabled) {
        transform: translateY(0);
    }
    .pg-btn.active {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: #ffffff !important;
        font-weight: 700;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        cursor: default;
    }
    .pg-btn.disabled {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #cbd5e1 !important;
        cursor: not-allowed;
        opacity: 0.55;
        box-shadow: none;
        pointer-events: none;
    }
    .pg-btn-icon {
        width: 28px;
        padding: 0;
    }
    .pg-btn-icon i {
        font-size: 13px;
        line-height: 1;
    }
    .pg-btn-print {
        background: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
        font-weight: 700;
        gap: 5px;
        padding: 0 11px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
    }
    .pg-btn-print:hover {
        background: #1e293b !important;
        border-color: #1e293b !important;
        color: #ffffff !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.28);
    }
    .pg-btn-print i {
        font-size: 13px;
        line-height: 1;
    }
    .pg-ellipsis {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 28px;
        color: #94a3b8;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1px;
        user-select: none;
    }
    .pg-jump-area {
        display: flex;
        align-items: center;
        gap: 5px;
        padding-left: 6px;
        border-left: 1px solid #e2e8f0;
        margin-left: 4px;
    }
    .pg-jump-text {
        font-size: 11.5px;
        color: #64748b;
    }
    .pg-jump-input {
        width: 44px;
        height: 28px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-align: center;
        font-size: 11.5px;
        font-weight: 600;
        color: #0f172a;
        background: #ffffff;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .pg-jump-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }
    .pg-jump-btn {
        height: 28px;
        padding: 0 8px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        transition: all 0.1s;
    }
    .pg-jump-btn:hover {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }
    .pg-size-group {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        background: #f8fafc;
        padding: 2px 4px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        margin-left: 4px;
    }
    .pg-size-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        margin: 0 4px 0 2px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .pg-size-btn {
        height: 24px;
        min-width: 24px;
        padding: 0 6px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 4px;
        border: none;
        background: transparent;
        color: #475569;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.12s;
    }
    .pg-size-btn:hover:not(.active) {
        background: #e2e8f0;
        color: #0f172a;
    }
    .pg-size-btn.active {
        background: #2563eb !important;
        color: #ffffff !important;
        font-weight: 700;
        box-shadow: 0 1px 2px rgba(37, 99, 235, 0.3);
        cursor: default;
    }
    .pg-divider {
        height: 20px;
        width: 1px;
        background: #cbd5e1;
        margin: 0 2px;
    }
    </style>
    <?php endif; ?>

    <div class="pagination-rail">
        <!-- Left: Record Count & Badge Summary -->
        <div class="pg-summary">
            <?php if ($isExplicitAll): ?>
                Showing all <strong><?php echo number_format($totalRecords); ?></strong> <?php echo htmlspecialchars($itemLabel); ?>
                <span class="pg-total-badge pg-badge-all"><i class="icon-check" style="font-size: 11px;"></i> All Entries Displayed</span>
            <?php elseif ($isAll): ?>
                Showing all <strong><?php echo number_format($totalRecords); ?></strong> <?php echo htmlspecialchars($itemLabel); ?>
                <span class="pg-total-badge pg-badge-all">All Records</span>
            <?php else: ?>
                Showing <strong><?php echo number_format($start); ?></strong>–<strong><?php echo number_format($end); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> <?php echo htmlspecialchars($itemLabel); ?>
                <?php if ($totalPages > 1): ?>
                    <span class="pg-total-badge">Page <?php echo number_format($currentPage); ?> of <?php echo number_format($totalPages); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Right: Controls, Page Size & Print Options -->
        <div class="pg-actions-wrap">
            <!-- Numerical Page Navigation & Jump (when paginated) -->
            <?php if (!$isExplicitAll && $totalPages > 1): ?>
                <div class="pg-controls">
                    <!-- First Page Button (<<) -->
                    <?php if ($isFirst): ?>
                        <span class="pg-btn pg-btn-icon disabled" title="First Page"><i class="icon-chevrons-left"></i></span>
                    <?php else: ?>
                        <a href="<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p=1" class="pg-btn pg-btn-icon" title="First Page (Page 1)"><i class="icon-chevrons-left"></i></a>
                    <?php endif; ?>

                    <!-- Previous Page Button (<) -->
                    <?php if ($isFirst): ?>
                        <span class="pg-btn pg-btn-icon disabled" title="Previous Page"><i class="icon-chevron-left"></i></span>
                    <?php else: ?>
                        <a href="<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p=<?php echo $prevPage; ?>" class="pg-btn pg-btn-icon" title="Previous Page (Page <?php echo $prevPage; ?>)"><i class="icon-chevron-left"></i></a>
                    <?php endif; ?>

                    <!-- Numerical Page Numbers & Ellipsis -->
                    <?php foreach ($pagesToShow as $pNum): ?>
                        <?php if ($pNum === '...'): ?>
                            <span class="pg-ellipsis">…</span>
                        <?php elseif ($pNum === $currentPage): ?>
                            <span class="pg-btn active" title="Current Page <?php echo $pNum; ?>"><?php echo $pNum; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p=<?php echo $pNum; ?>" class="pg-btn" title="Page <?php echo $pNum; ?>"><?php echo $pNum; ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Next Page Button (>) -->
                    <?php if ($isLast): ?>
                        <span class="pg-btn pg-btn-icon disabled" title="Next Page"><i class="icon-chevron-right"></i></span>
                    <?php else: ?>
                        <a href="<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p=<?php echo $nextPage; ?>" class="pg-btn pg-btn-icon" title="Next Page (Page <?php echo $nextPage; ?>)"><i class="icon-chevron-right"></i></a>
                    <?php endif; ?>

                    <!-- Last Page Button (>>) -->
                    <?php if ($isLast): ?>
                        <span class="pg-btn pg-btn-icon disabled" title="Last Page"><i class="icon-chevrons-right"></i></span>
                    <?php else: ?>
                        <a href="<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p=<?php echo $totalPages; ?>" class="pg-btn pg-btn-icon" title="Last Page (Page <?php echo $totalPages; ?>)"><i class="icon-chevrons-right"></i></a>
                    <?php endif; ?>
                </div>

                <!-- Direct Jump Input -->
                <div class="pg-jump-area">
                    <span class="pg-jump-text">Go to:</span>
                    <input type="number" min="1" max="<?php echo $totalPages; ?>" class="pg-jump-input" value="<?php echo $currentPage; ?>" title="Type page number and press Enter" onkeydown="if(event.key==='Enter'){const val=Math.max(1,Math.min(<?php echo $totalPages; ?>,parseInt(this.value)||1));window.location.href='<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p='+val;}">
                    <button type="button" class="pg-jump-btn" onclick="const inp=this.previousElementSibling;const val=Math.max(1,Math.min(<?php echo $totalPages; ?>,parseInt(inp.value)||1));window.location.href='<?php echo $cleanBaseUrl . $delim . $pageLimitParam; ?>p='+val;">Go</button>
                </div>

                <div class="pg-divider"></div>
            <?php endif; ?>

            <!-- Page Size / View Selector (25 | 50 | 100 | All) -->
            <?php if ($totalRecords > 25 || $isExplicitAll): ?>
                <div class="pg-size-group">
                    <span class="pg-size-label">Show:</span>
                    <?php 
                    $pageSizes = [25, 50, 100];
                    foreach ($pageSizes as $sz):
                        if ($sz >= $totalRecords && !$isExplicitAll && (int)$limit === $sz) {
                            // If total records is less than this size, skip showing redundant higher options
                            continue;
                        }
                        $isSzActive = (!$isExplicitAll && (int)$limit === $sz);
                    ?>
                        <a href="<?php echo $cleanBaseUrl . $delim; ?>limit=<?php echo $sz; ?>&p=1" 
                           class="pg-size-btn <?php echo $isSzActive ? 'active' : ''; ?>" 
                           title="Show <?php echo $sz; ?> <?php echo htmlspecialchars($itemLabel); ?> per page">
                           <?php echo $sz; ?>
                        </a>
                    <?php endforeach; ?>

                    <!-- All Option -->
                    <a href="<?php echo $cleanBaseUrl . $delim; ?>limit=all" 
                       class="pg-size-btn <?php echo $isExplicitAll ? 'active' : ''; ?>" 
                       title="Display all <?php echo number_format($totalRecords); ?> <?php echo htmlspecialchars($itemLabel); ?> on one page">
                       All<?php echo ($totalRecords > 100) ? ' (' . number_format($totalRecords) . ')' : ''; ?>
                    </a>
                </div>

                <div class="pg-divider"></div>
            <?php endif; ?>

            <!-- Print Controls -->
            <div style="display: inline-flex; align-items: center; gap: 4px;">
                <?php if ($isExplicitAll || $isAll): ?>
                    <!-- Currently viewing all entries: direct print triggers print on the full dataset -->
                    <button type="button" onclick="window.print()" class="pg-btn pg-btn-print" title="Print all <?php echo number_format($totalRecords); ?> <?php echo htmlspecialchars($itemLabel); ?>">
                        <i class="icon-printer"></i> Print All
                    </button>
                <?php else: ?>
                    <!-- Currently paginated: offer Print Page (current 25/50) and Print All (full dataset) -->
                    <button type="button" onclick="window.print()" class="pg-btn" title="Print current page view (records <?php echo number_format($start); ?>–<?php echo number_format($end); ?>)">
                        <i class="icon-printer"></i> Print Page
                    </button>
                    <a href="<?php echo $cleanBaseUrl . $delim; ?>limit=all&print=1" 
                       target="_blank" 
                       class="pg-btn pg-btn-print pg-btn-print-all-link" 
                       data-total="<?php echo number_format($totalRecords); ?>" 
                       title="Open and print all <?php echo number_format($totalRecords); ?> <?php echo htmlspecialchars($itemLabel); ?> across all pages">
                        <i class="icon-printer"></i> Print All (<?php echo number_format($totalRecords); ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
