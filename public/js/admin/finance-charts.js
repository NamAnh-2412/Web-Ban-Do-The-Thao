(function () {
    const boot = document.getElementById('finance-charts-config');
    if (!boot || typeof Chart === 'undefined') {
        return;
    }
    const cfg = JSON.parse(boot.textContent);
    const statusCanvas = document.getElementById('financeStatusChart');
    const methodCanvas = document.getElementById('financeMethodChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: cfg.statusLabels || [],
                datasets: [{
                    data: cfg.statusData || [],
                    backgroundColor: ['#f0ad4e', '#198754', '#dc3545', '#6c757d', '#0dcaf0'],
                }],
            },
            options: { plugins: { legend: { position: 'bottom' } } },
        });
    }
    if (methodCanvas) {
        new Chart(methodCanvas, {
            type: 'bar',
            data: {
                labels: cfg.methodLabels || [],
                datasets: [{ label: 'Đã thu (đ)', data: cfg.methodData || [], backgroundColor: '#1a4f8c' }],
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }
})();
