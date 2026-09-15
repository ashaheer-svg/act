<?php
/**
 * Modern Report Pagination Component (Concept B: Modern Floating Rail)
 *
 * Standardized across all business reports with minimized Lucide chevrons,
 * smart window truncation (1 ... 14 [15] 16 ... 497), and direct page jumping.
 *
 * @param int $currentPage Current 1-based page number
 * @param int $totalPages Total number of pages
 * @param int $totalRecords Total count of records across dataset
 * @param int $limit Items per page
 * @param string $baseUrl Base URL with existing filter query params (without &p=)
 * @param string $itemLabel Plural noun describing items (e.g. 'invoices', 'accounts', 'units')
 * @return string Rendered HTML
 */
function renderPaginationRail($currentPage, $totalPages, $totalRecords, $limit, $baseUrl, $itemLabel = 'records') {
    if ($totalRecords <= 0) {
        return '';
    }

    static $cssRendered = false;

    $currentPage = max(1, (int)$currentPage);
    $totalPages = max(1, (int)$totalPages);
    $start = max(1, ($currentPage - 1) * $limit + 1);
    $end = min($totalRecords, $currentPage * $limit);

    // Calculate smart window of pages
    $pagesToShow = [];
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
    .pg-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pg-btn {
        height: 28px;
        min-width: 28px;
        padding: 0 7px;
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
        gap: 6px;
        padding-left: 8px;
        border-left: 1px solid #e2e8f0;
        margin-left: 6px;
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
    </style>
    <?php endif; ?>

    <div class="pagination-rail">
        <div class="pg-summary">
            Showing <strong><?php echo number_format($start); ?></strong>–<strong><?php echo number_format($end); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> <?php echo htmlspecialchars($itemLabel); ?>
            <?php if ($totalPages > 1): ?>
                <span class="pg-total-badge">Page <?php echo number_format($currentPage); ?> of <?php echo number_format($totalPages); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div class="pg-controls">
                <!-- First Page Button (<<) -->
                <?php if ($isFirst): ?>
                    <span class="pg-btn pg-btn-icon disabled" title="First Page"><i class="icon-chevrons-left"></i></span>
                <?php else: ?>
                    <a href="<?php echo $baseUrl; ?>&p=1" class="pg-btn pg-btn-icon" title="First Page (Page 1)"><i class="icon-chevrons-left"></i></a>
                <?php endif; ?>

                <!-- Previous Page Button (<) -->
                <?php if ($isFirst): ?>
                    <span class="pg-btn pg-btn-icon disabled" title="Previous Page"><i class="icon-chevron-left"></i></span>
                <?php else: ?>
                    <a href="<?php echo $baseUrl; ?>&p=<?php echo $prevPage; ?>" class="pg-btn pg-btn-icon" title="Previous Page (Page <?php echo $prevPage; ?>)"><i class="icon-chevron-left"></i></a>
                <?php endif; ?>

                <!-- Numerical Page Numbers & Ellipsis -->
                <?php foreach ($pagesToShow as $pNum): ?>
                    <?php if ($pNum === '...'): ?>
                        <span class="pg-ellipsis">…</span>
                    <?php elseif ($pNum === $currentPage): ?>
                        <span class="pg-btn active" title="Current Page <?php echo $pNum; ?>"><?php echo $pNum; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $baseUrl; ?>&p=<?php echo $pNum; ?>" class="pg-btn" title="Page <?php echo $pNum; ?>"><?php echo $pNum; ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Next Page Button (>) -->
                <?php if ($isLast): ?>
                    <span class="pg-btn pg-btn-icon disabled" title="Next Page"><i class="icon-chevron-right"></i></span>
                <?php else: ?>
                    <a href="<?php echo $baseUrl; ?>&p=<?php echo $nextPage; ?>" class="pg-btn pg-btn-icon" title="Next Page (Page <?php echo $nextPage; ?>)"><i class="icon-chevron-right"></i></a>
                <?php endif; ?>

                <!-- Last Page Button (>>) -->
                <?php if ($isLast): ?>
                    <span class="pg-btn pg-btn-icon disabled" title="Last Page"><i class="icon-chevrons-right"></i></span>
                <?php else: ?>
                    <a href="<?php echo $baseUrl; ?>&p=<?php echo $totalPages; ?>" class="pg-btn pg-btn-icon" title="Last Page (Page <?php echo $totalPages; ?>)"><i class="icon-chevrons-right"></i></a>
                <?php endif; ?>
            </div>

            <!-- Direct Jump Input -->
            <div class="pg-jump-area">
                <span class="pg-jump-text">Go to:</span>
                <input type="number" min="1" max="<?php echo $totalPages; ?>" class="pg-jump-input" value="<?php echo $currentPage; ?>" title="Type page number and press Enter" onkeydown="if(event.key==='Enter'){const val=Math.max(1,Math.min(<?php echo $totalPages; ?>,parseInt(this.value)||1));window.location.href='<?php echo $baseUrl; ?>&p='+val;}">
                <button type="button" class="pg-jump-btn" onclick="const inp=this.previousElementSibling;const val=Math.max(1,Math.min(<?php echo $totalPages; ?>,parseInt(inp.value)||1));window.location.href='<?php echo $baseUrl; ?>&p='+val;">Go</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
