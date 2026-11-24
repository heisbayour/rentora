/**
 * Chart.js wrapper for the dashboard (bundled, loaded on demand so other
 * pages do not pay for it). Colours come from the brand CSS variables.
 */
export default function dashboardChart(type, data) {
    return {
        chart: null,
        async init() {
            const { default: Chart } = await import('chart.js/auto');
            const styles = getComputedStyle(document.documentElement);
            const primary = `rgb(${styles.getPropertyValue('--color-primary-600').trim()})`;
            const primarySoft = `rgb(${styles.getPropertyValue('--color-primary-600').trim()} / 0.25)`;
            const palette = ['#16A34A', '#F59E0B', '#EA580C', '#DC2626'];

            const datasets = data.datasets.map((dataset, index) => {
                if (type === 'doughnut') {
                    return { ...dataset, backgroundColor: palette, borderWidth: 0 };
                }
                return { ...dataset, backgroundColor: index === 0 ? primarySoft : primary, borderRadius: 4 };
            });

            const money = (value) => new Intl.NumberFormat('en-NG', { maximumFractionDigits: 0 }).format(value);

            this.chart = new Chart(this.$refs.canvas, {
                type,
                data: { labels: data.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12 } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label || ctx.label}: ${money(ctx.parsed.y ?? ctx.parsed)}` } },
                    },
                    scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { callback: (value) => money(value) } } } : {},
                },
            });
        },
        destroy() {
            this.chart?.destroy();
        },
    };
}
