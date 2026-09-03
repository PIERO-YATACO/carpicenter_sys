// ===== Carpicenter App JS =====
document.addEventListener('DOMContentLoaded', function() {
    initDashboardCharts();
    initReportCharts();
});

function initDashboardCharts() {
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#C62828';
    const primaryLight = getComputedStyle(document.documentElement).getPropertyValue('--primary-light').trim() || '#E53935';

    // Sales Line Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Ene','Feb','Mar','Abr','May','Jun'],
                datasets: [{
                    label: 'Ventas (S/)',
                    data: [18500, 22000, 19800, 28000, 35200, 25430],
                    borderColor: primaryColor,
                    backgroundColor: primaryColor + '1a',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: primaryColor,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b6b80' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b6b80', callback: v => 'S/ ' + (v/1000) + 'K' } }
                }
            }
        });
    }

    // Distribution Donut
    const donutCtx = document.getElementById('distributionChart');
    if (donutCtx) {
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Mesas','Sillas','Sofás','Estantes','Otros'],
                datasets: [{
                    data: [28, 30, 18, 14, 10],
                    backgroundColor: [primaryColor, primaryLight, '#FF5252', '#FF8A80', '#4a1a1a'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#a0a0b0', padding: 12, font: { size: 11 } } }
                }
            }
        });
    }
}

function initReportCharts() {
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#C62828';

    const reportCtx = document.getElementById('reportChart');
    if (reportCtx) {
        new Chart(reportCtx, {
            type: 'bar',
            data: {
                labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
                datasets: [{
                    label: 'Ventas por mes',
                    data: [15200, 18400, 22100, 19800, 25430, 21000, 18900, 23500, 27800, 24100, 29500, 31200],
                    backgroundColor: primaryColor + 'b3', // 70% opacity
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#6b6b80' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b6b80' } }
                }
            }
        });
    }
}

