<script>
/**
 * Shared layout JavaScript
 * Handles: sidebar collapse, nav group expand/collapse, user dropdown.
 */

/* ── Sidebar collapse toggle ── */
function updateSidebarIcon(collapsed) {
    const icon = document.getElementById('sidebarToggleIcon');
    if (icon) {
        if (collapsed) {
            icon.className = 'icon-chevrons-right';
        } else {
            icon.className = 'icon-chevrons-left';
        }
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
