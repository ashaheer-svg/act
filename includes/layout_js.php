<script>
/**
 * Shared layout JavaScript
 * Handles: sidebar collapse, nav group expand/collapse, user dropdown.
 */

/* ── Sidebar collapse toggle ── */
function updateSidebarIcon(collapsed) {
    const icon = document.getElementById('sidebarToggleIcon');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (icon) {
        icon.className = collapsed ? 'icon-chevrons-right' : 'icon-chevrons-left';
    }
    if (collapseBtn) {
        collapseBtn.title = collapsed ? 'Expand Sidebar (180px)' : 'Collapse Sidebar (50px)';
    }

    const footerIcon = document.getElementById('sidebarFooterToggleIcon');
    const footerBtn = document.getElementById('sidebarFooterToggleBtn');
    const footerSpan = footerBtn ? footerBtn.querySelector('span') : null;
    if (footerIcon) {
        footerIcon.className = collapsed ? 'icon-chevrons-right' : 'icon-chevrons-left';
    }
    if (footerSpan) {
        footerSpan.textContent = collapsed ? 'Expand' : 'Collapse';
    }
    if (footerBtn) {
        footerBtn.setAttribute('data-title', collapsed ? 'Expand Sidebar' : 'Collapse Sidebar');
        footerBtn.title = collapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
    updateSidebarIcon(collapsed);
}

/* ── Nav group (accordion) toggle ── */
function toggleNavGroup(btn) {
    const group = btn.closest('.nav-group');
    const isOpen = group.classList.toggle('open');
    // Persist state per group (use button text as key)
    const key = 'navGroup_' + (btn.querySelector('span')?.textContent ?? '');
    localStorage.setItem(key, isOpen);
}

/* ── Restore sidebar collapse state on load ── */
(function () {
    const isCollapsed = (localStorage.getItem('sidebarCollapsed') === 'true');
    const sidebar = document.getElementById('mainSidebar');
    if (sidebar && isCollapsed) {
        sidebar.classList.add('collapsed');
        updateSidebarIcon(true);
    }
})();

/* ── User dropdown toggle ── */
function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('active');
}

window.addEventListener('click', function (event) {
    if (!event.target.closest('.user-dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(function (el) {
            el.classList.remove('active');
        });
    }
});
</script>
