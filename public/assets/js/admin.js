(function () {
    'use strict';

    var pendingForm = null;

    window.adminConfirm = function (event, formEl) {
        event.preventDefault();
        pendingForm = formEl;
        var msg = formEl.dataset.confirm || 'Wirklich fortfahren?';
        document.getElementById('confirmMessage').textContent = msg;
        var ok = document.getElementById('confirmOkBtn');
        ok.disabled = false;
        ok.textContent = 'Ja, löschen';
        var modal = document.getElementById('confirmModal');
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    window.closeAdminConfirm = function () {
        pendingForm = null;
        var modal = document.getElementById('confirmModal');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var ok = document.getElementById('confirmOkBtn');
        if (ok) {
            ok.addEventListener('click', function () {
                if (!pendingForm) return;
                this.disabled = true;
                this.textContent = '…';
                pendingForm.submit();
                closeAdminConfirm();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAdminConfirm();
        });

        var importForm = document.getElementById('importForm');
        if (importForm) {
            importForm.addEventListener('submit', function () {
                var btn = document.getElementById('importBtn');
                var txt = document.getElementById('importBtnText');
                btn.disabled = true;
                txt.textContent = 'Wird importiert…';
            });
        }

        var canvas = document.getElementById('auslastungChart');
        if (canvas && window.Chart && window.ADMIN_CHART_LABELS && window.ADMIN_CHART_VALUES) {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: window.ADMIN_CHART_LABELS,
                    datasets: [{
                        label: 'Check-ins',
                        data: window.ADMIN_CHART_VALUES,
                        backgroundColor: 'rgba(13, 148, 136, 0.7)',
                        borderColor:     'rgba(13, 148, 136, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var n = ctx.parsed.y;
                                    return ' ' + n + ' Check-in' + (n !== 1 ? 's' : '');
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                maxRotation: 45,
                                callback: function (val, index) {
                                    return index % 3 === 0 ? this.getLabelForValue(val) : '';
                                },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 11 } },
                            grid: { color: 'rgba(0,0,0,0.05)' },
                        },
                    },
                },
            });
        }
    });
})();
