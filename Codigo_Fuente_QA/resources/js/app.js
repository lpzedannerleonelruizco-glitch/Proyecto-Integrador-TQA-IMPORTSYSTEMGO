import './bootstrap';
import * as bootstrap from 'bootstrap';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
window.bootstrap = bootstrap;
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => document.body.classList.toggle('sidebar-open')));
document.querySelectorAll('.toast[data-autohide="true"]').forEach((toast) => bootstrap.Toast.getOrCreateInstance(toast, { delay: 4500 }).show());
const modalElement = document.getElementById('confirmModal'); const modalAction = document.getElementById('confirmModalAction'); const modalMessage = document.getElementById('confirmModalMessage'); let pendingForm = null;
document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => { if (form.dataset.confirmed === 'true') return; event.preventDefault(); pendingForm = form; modalMessage.textContent = form.dataset.confirm; bootstrap.Modal.getOrCreateInstance(modalElement).show(); }));
modalAction?.addEventListener('click', () => { if (!pendingForm) return; pendingForm.dataset.confirmed = 'true'; pendingForm.requestSubmit(); });
document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', () => { if (!form.checkValidity()) return; form.classList.add('loading'); form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => { button.disabled = true; if (button.dataset.loadingText) button.textContent = button.dataset.loadingText; }); }));
document.querySelectorAll('[data-table-search]').forEach((input) => { const table = document.querySelector(input.dataset.tableSearch); input.addEventListener('input', () => { const term = input.value.trim().toLocaleLowerCase('es'); table?.querySelectorAll('tbody tr').forEach((row) => { row.hidden = term !== '' && !row.textContent.toLocaleLowerCase('es').includes(term); }); }); });
const chartDataElement = document.getElementById('dashboard-chart-data');
if (chartDataElement) {
    const charts = JSON.parse(chartDataElement.textContent);
    const palette = ['#1769ff', '#11a36a', '#f1a429', '#7657d5', '#25a9d8', '#e35d6a', '#64748b'];
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const values = charts[canvas.dataset.chart] ?? [];
        let datasets;
        if (canvas.dataset.chart === 'mlForecastBand') {
            datasets = [
                { label: 'Pronóstico', data: values.map((item) => item.value), borderColor: '#1769ff', backgroundColor: 'rgba(23, 105, 255, .12)', tension: .35, fill: false },
                { label: 'Límite inferior', data: values.map((item) => item.lower), borderColor: '#64748b', backgroundColor: 'transparent', borderDash: [5, 5], tension: .35, fill: false },
                { label: 'Límite superior', data: values.map((item) => item.upper), borderColor: '#11a36a', backgroundColor: 'rgba(17, 163, 106, .08)', borderDash: [5, 5], tension: .35, fill: false },
            ];
        } else if (canvas.dataset.chart === 'mlHistory') {
            datasets = [{ label: 'Demanda observada', data: values.map((item) => item.value), borderColor: '#1769ff', backgroundColor: 'rgba(23, 105, 255, .10)', tension: .35, fill: true }];
        } else {
            datasets = [{ label: canvas.dataset.label ?? 'Importe (Bs)', data: values.map((item) => item.value), backgroundColor: canvas.dataset.type === 'doughnut' ? palette : 'rgba(23, 105, 255, .78)', borderColor: '#1769ff', borderWidth: 1, borderRadius: 7, tension: .35, fill: canvas.dataset.type === 'line' }];
            if (values.some((item) => Object.hasOwn(item, 'available'))) datasets.push({ label: 'Disponible actual', data: values.map((item) => item.available), backgroundColor: 'rgba(17, 163, 106, .72)', borderColor: '#11a36a', borderWidth: 1, borderRadius: 7 });
        }
        new Chart(canvas, {
            type: canvas.dataset.type ?? (canvas.dataset.chart === 'mlForecastBand' || canvas.dataset.chart === 'mlHistory' ? 'line' : 'bar'),
            data: { labels: values.map((item) => item.label), datasets },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: canvas.dataset.type === 'doughnut' || datasets.length > 1, position: 'bottom' }, tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${Number(context.parsed.y).toLocaleString('es')}` } } }, scales: canvas.dataset.type === 'doughnut' ? {} : { y: { beginAtZero: true, grid: { color: '#edf1f6' }, ticks: { callback: (value) => Number(value).toLocaleString('es') } }, x: { grid: { display: false } } } },
        });
    });
}

const recommendationDetailsElement = document.getElementById('recommendation-details');
if (recommendationDetailsElement) {
    const details = JSON.parse(recommendationDetailsElement.textContent);
    document.querySelectorAll('[data-recommendation-detail]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = details[button.dataset.recommendationDetail];
            if (!item) return;
            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) element.textContent = value;
            };
            setText('detail-product', `${item.product} · ${item.sku}`);
            setText('detail-branch', item.branch);
            setText('detail-physical', Number(item.physical).toLocaleString('es'));
            setText('detail-reserved', Number(item.reserved).toLocaleString('es'));
            setText('detail-available', Number(item.available).toLocaleString('es'));
            setText('detail-forecast', Number(item.forecast).toLocaleString('es'));
            setText('detail-range', `${Number(item.lower).toLocaleString('es')} – ${Number(item.upper).toLocaleString('es')}`);
            setText('detail-safety', Number(item.safety).toLocaleString('es'));
            setText('detail-suggested', Number(item.suggested).toLocaleString('es'));
            setText('detail-risk', String(item.risk).toUpperCase());
            setText('detail-model', item.model ?? 'No disponible');
            setText('detail-reason', item.reason);
            setText('detail-generated', item.generated ?? 'No disponible');
        });
    });
}
