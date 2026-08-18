// app.js
// Handles:
// - Sidebar collapse/expand on mobile
// - Active state highlighting for sidebar nav links

(function () {
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const navLinks = sidebar ? sidebar.querySelectorAll('.nav-link[data-menu]') : [];
    const sections = document.querySelectorAll('[data-section]');
    const pageTitle = document.getElementById('pageTitle');

    // Ensure sidebar is collapsed by default on small screens
    const setInitialSidebarState = () => {
        if (window.innerWidth < 992) {
            body.classList.add('sidebar-collapsed');
        } else {
            body.classList.remove('sidebar-collapsed');
        }
    };

    // Toggle sidebar collapsed state
    const toggleSidebar = () => {
        body.classList.toggle('sidebar-collapsed');
    };

    // Close sidebar (used by close button)
    const closeSidebar = () => {
        if (!body.classList.contains('sidebar-collapsed')) {
            body.classList.add('sidebar-collapsed');
        }
    };

    // Active link handling (pure front-end for now)
    const handleNavClick = (event) => {
        const link = event.currentTarget;

        navLinks.forEach((l) => l.classList.remove('active'));
        link.classList.add('active');

        const menu = link.getAttribute('data-menu');

        // Show the matching section, hide others
        sections.forEach((section) => {
            if (section.getAttribute('data-section') === menu) {
                section.classList.remove('d-none');
            } else {
                section.classList.add('d-none');
            }
        });

        // Update page title based on menu
        if (pageTitle) {
            const label = link.textContent.trim();
            pageTitle.textContent = label;
        }
    };

    // Event listeners
    window.addEventListener('resize', setInitialSidebarState);
    window.addEventListener('DOMContentLoaded', setInitialSidebarState);

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
    }

    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', closeSidebar);
    }

    navLinks.forEach((link) => {
        link.addEventListener('click', handleNavClick);
    });
})();

