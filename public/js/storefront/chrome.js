(() => {
    const nav = document.querySelector('.navbar-wt');
    if (!nav) return;

    const onScroll = () => {
        nav.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const badge = document.querySelector('.cart-badge');
    if (badge && Number(badge.textContent) > 0) {
        badge.classList.add('has-items');
    }
})();
