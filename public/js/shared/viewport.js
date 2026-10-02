(function () {
    var root = document.documentElement;
    var sidebar = document.getElementById('admin-sidebar');
    var toggle = document.querySelector('[data-admin-nav-toggle]');
    var backdrop = document.querySelector('[data-admin-nav-backdrop]');

    function band(width) {
        if (width < 576) return 'mobile';
        if (width < 992) return 'tablet';
        if (width < 1600) return 'desktop';
        return 'ultrawide';
    }

    function aspectName(ratio) {
        if (ratio < 0.8) return 'portrait';
        if (ratio < 1.2) return 'square';
        if (ratio < 2) return 'landscape';
        return 'ultrawide';
    }

    function closeNav() {
        document.body.classList.remove('admin-nav-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    function applyViewport() {
        var width = window.innerWidth;
        var height = Math.max(window.innerHeight, 1);
        var ratio = width / height;
        root.dataset.viewport = band(width);
        root.dataset.aspect = aspectName(ratio);
        root.style.setProperty('--vp-ratio', ratio.toFixed(3));
        if (width >= 992) closeNav();
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            var open = document.body.classList.toggle('admin-nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (backdrop) backdrop.addEventListener('click', closeNav);
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a')) closeNav();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeNav();
        });
    }

    applyViewport();
    window.addEventListener('resize', applyViewport, { passive: true });
})();
