// Gráfico de ingresos por barbero del dashboard (Chart.js, barras doradas #C8A15A).
document.addEventListener('DOMContentLoaded', function () {
    var lienzo = document.getElementById('graficoIngresos');

    if (!lienzo || typeof Chart === 'undefined') {
        return;
    }

    var datos = JSON.parse(lienzo.dataset.grafico);

    function moneda(n) {
        return '$' + Math.round(n).toLocaleString('es-CO');
    }

    // Eje vertical abreviado: $800 mil, $1,2 M
    function abreviar(n) {
        if (n >= 1000000) { return '$' + (n / 1000000).toLocaleString('es-CO', { maximumFractionDigits: 1 }) + ' M'; }
        if (n >= 1000) { return '$' + Math.round(n / 1000) + ' mil'; }
        return '$' + n;
    }

    // Escribe el valor encima de cada barra (como en el mockup)
    var valoresSobreBarras = {
        id: 'valoresSobreBarras',
        afterDatasetsDraw: function (grafico) {
            var ctx = grafico.ctx;
            ctx.save();
            ctx.font = '600 12px Inter, sans-serif';
            ctx.fillStyle = '#1C1C1E';
            ctx.textAlign = 'center';
            grafico.getDatasetMeta(0).data.forEach(function (barra, i) {
                ctx.fillText(moneda(grafico.data.datasets[0].data[i]), barra.x, barra.y - 6);
            });
            ctx.restore();
        }
    };

    new Chart(lienzo, {
        type: 'bar',
        data: {
            labels: datos.etiquetas,
            datasets: [{
                label: 'Ingresos',
                data: datos.valores,
                backgroundColor: '#C8A15A',
                borderRadius: 6,
                maxBarThickness: 72
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 22 } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return ' ' + moneda(c.parsed.y); } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: abreviar }, grid: { color: '#ece7dc' } },
                x: { grid: { display: false } }
            }
        },
        plugins: [valoresSobreBarras]
    });
});
