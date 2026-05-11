<script>
/**
 * Shared layout JavaScript
 * Handles sidebar toggle, collapse persistence, and user dropdown.
 */
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

// Restore sidebar collapse state on load
(function() {
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        const sidebar = document.getElementById('mainSidebar');
        if (sidebar) sidebar.classList.add('collapsed');
    }
})();

function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('active');
}

window.addEventListener('click', function(event) {
    if (!event.target.closest('.user-dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(function(el) {
            el.classList.remove('active');
        });
    }
});
</script>
