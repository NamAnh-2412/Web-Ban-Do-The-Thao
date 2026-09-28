document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('week-revenue-chart');
    const boot = document.getElementById('dashboard-charts-config');
    if (!canvas || !boot || typeof Chart === 'undefined') return;
    const cfg = JSON.parse(boot.textContent);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: cfg.labels || [],
            datasets: [{
                label: 'Doanh thu',
                data: cfg.values || [],
                backgroundColor: '#1a4f8c',
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('vi-VN').format(value) + 'đ',
                    },
                },
            },
        },
    });
});
