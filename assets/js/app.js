document.addEventListener('DOMContentLoaded', function () {
    const shell = document.querySelector('.app-shell');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!shell || !toggleBtn) return;

    function openSidebar() {
        shell.classList.add('sidebar-open');
    }
    function closeSidebar() {
        shell.classList.remove('sidebar-open');
    }

    toggleBtn.addEventListener('click', function () {
        shell.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    });

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.querySelectorAll('.menu-nav .menu-item a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) closeSidebar();
    });
});