document.addEventListener('click', function (event) {
    const btn = event.target.closest('[data-copy]');
    if (!btn) {
        return;
    }
    event.preventDefault();
    const value = btn.getAttribute('data-copy') || '';
    const label = btn.getAttribute('data-copy-label') || 'Sao chép';
    const done = function () {
        btn.textContent = 'Đã chép';
        setTimeout(function () { btn.textContent = label; }, 1200);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(function () {
            window.prompt('Sao chép:', value);
        });
    } else {
        window.prompt('Sao chép:', value);
    }
});
