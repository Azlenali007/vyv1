/**
 * Chart.js Dashboard Visualizations (Strictly fed by real MySQL query data)
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;

    const overviewCanvas = document.getElementById('salesOverviewChart');
    if (overviewCanvas && window.DASHBOARD_CHART_DATA) {
        const cfg = window.DASHBOARD_CHART_DATA;
        const ctx = overviewCanvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 260);
        grad.addColorStop(0, 'rgba(113, 56, 216, 0.28)');
        grad.addColorStop(1, 'rgba(113, 56, 216, 0.01)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: cfg.labels || [],
                datasets: [{
                    label: cfg.label || 'Volume',
                    data: cfg.values || [],
                    borderColor: '#7138D8',
                    backgroundColor: grad,
                    borderWidth: 3,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#7138D8',
                    pointBorderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.38,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#151515',
                        titleColor: '#FFFFFF',
                        bodyColor: '#E2E8F0',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748B', font: { family: 'Plus Jakarta Sans', size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(15, 23, 42, 0.06)' },
                        ticks: { color: '#64748B', font: { family: 'Plus Jakarta Sans', size: 12 } }
                    }
                }
            }
        });
    }

    const donutCanvas = document.getElementById('orderStatusChart');
    if (donutCanvas && window.ORDER_STATUS_DATA) {
        const s = window.ORDER_STATUS_DATA;
        const total = (s.completed || 0) + (s.processing || 0) + (s.pending || 0) + (s.cancelled || 0);
        if (total > 0) {
            new Chart(donutCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Processing', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [s.completed, s.processing, s.pending, s.cancelled],
                        backgroundColor: ['#16A36A', '#7138D8', '#F59E0B', '#DC2626'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    }
});
