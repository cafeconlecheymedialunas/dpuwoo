/* global jQuery, Chart, dpuwoo_dashboard, dpuwoo_ajax */
(function ($) {
    'use strict';

    var rateChart     = null;
    var productsChart = null;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function humanizeTimestamp(ts) {
        if (!ts) return '—';
        var now  = Math.floor(Date.now() / 1000);
        var diff = ts - now;
        if (diff <= 0) return 'En breve';
        var h = Math.floor(diff / 3600);
        var m = Math.floor((diff % 3600) / 60);
        if (h > 24) { var d = Math.floor(h / 24); return 'en ' + d + 'd ' + (h % 24) + 'h'; }
        if (h > 0)  return 'en ' + h + 'h ' + m + 'min';
        return 'en ' + m + ' min';
    }

    function humanizeDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d)) return dateStr;
        return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' })
            + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        var diffMin = Math.floor((new Date() - d) / 60000);
        if (diffMin < 2)  return 'Hace un momento';
        if (diffMin < 60) return 'Hace ' + diffMin + ' min';
        var diffH = Math.floor(diffMin / 60);
        if (diffH < 24)   return 'Hace ' + diffH + 'h';
        var diffD = Math.floor(diffH / 24);
        return 'Hace ' + diffD + ' día' + (diffD > 1 ? 's' : '');
    }

    function formatPct(value) {
        if (value === null || value === undefined || value === '') return '—';
        var n = parseFloat(value);
        if (isNaN(n)) return '—';
        var sign = n >= 0 ? '+' : '';
        var color = n > 0 ? '#22c55e' : (n < 0 ? '#ef4444' : '#6b7280');
        return '<span style="color:' + color + '; font-weight:600;">' + sign + n.toFixed(2) + '%</span>';
    }

    function contextBadge(context) {
        if (context === 'cron') {
            return '<span style="background:#fff7ed; color:#ea580c; border-radius:5px; padding:2px 7px; font-size:11px; font-weight:600;">Auto</span>';
        }
        return '<span style="background:#eff6ff; color:#2563eb; border-radius:5px; padding:2px 7px; font-size:11px; font-weight:600;">Manual</span>';
    }

    // ── KPI Population ───────────────────────────────────────────────────────

    function populateKpis(data) {
        var last = (data.last_runs && data.last_runs.length) ? data.last_runs[0] : null;
        if (last) {
            var rate = parseFloat(last.dollar_value);
            $('#dpuwoo-kpi-rate').text(isNaN(rate) ? '—' : '$' + rate.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
            $('#dpuwoo-kpi-rate-type').text(last.dollar_type || '—');
        }
        var nextCron = data.next_cron;
        if (nextCron && data.cron_enabled) {
            $('#dpuwoo-kpi-next-cron').text(humanizeTimestamp(nextCron));
            $('#dpuwoo-kpi-next-cron-sub').text(
                new Date(nextCron * 1000).toLocaleString('es-AR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
            );
        }
    }

    // ── Statistics Population ─────────────────────────────────────────────────

    function populateStats(stats) {
        if (!stats) return;
        $('#dpuwoo-stat-runs').text(parseInt(stats.total_runs || 0).toLocaleString('es-AR'));
        $('#dpuwoo-stat-products').text(parseInt(stats.total_products || 0).toLocaleString('es-AR'));
        var avg = parseFloat(stats.avg_pct || 0);
        $('#dpuwoo-stat-avg').text((avg >= 0 ? '+' : '') + avg.toFixed(2) + '%')
            .css('color', avg > 0 ? '#22c55e' : (avg < 0 ? '#ef4444' : '#111827'));
        var updated  = parseInt(stats.updated_count || 0);
        var errors   = parseInt(stats.error_count   || 0);
        var total    = updated + errors;
        var rate     = total > 0 ? Math.round((updated / total) * 100) : (parseInt(stats.total_runs) > 0 ? 100 : 0);
        $('#dpuwoo-stat-success').text(rate + '%')
            .css('color', rate >= 95 ? '#22c55e' : (rate >= 80 ? '#f97316' : '#ef4444'));
    }

    // ── Charts ────────────────────────────────────────────────────────────────

    function renderRateChart(chartRuns) {
        var canvas = document.getElementById('dpuwoo-chart-rate');
        if (!canvas) return;
        if (!chartRuns || !chartRuns.length) {
            $(canvas).hide(); $('#dpuwoo-chart-rate-empty').show(); return;
        }
        var labels = chartRuns.map(function (r) { var d = new Date(r.date.replace(' ', 'T')); return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }); });
        var values = chartRuns.map(function (r) { return parseFloat(r.dollar_value); });
        if (rateChart) rateChart.destroy();
        rateChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { labels: labels, datasets: [{ label: 'Tipo de cambio', data: values, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.08)', fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 }] },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return '$' + c.parsed.y.toLocaleString('es-AR', { minimumFractionDigits: 2 }); } } } },
                scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 11 } } }, y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, callback: function (v) { return '$' + v.toLocaleString('es-AR'); } } } }
            }
        });
    }

    function renderProductsChart(chartRuns) {
        var canvas = document.getElementById('dpuwoo-chart-products');
        if (!canvas) return;
        var last10 = (chartRuns || []).slice(-10);
        if (!last10.length) { $(canvas).hide(); $('#dpuwoo-chart-products-empty').show(); return; }
        var labels = last10.map(function (r) { var d = new Date(r.date.replace(' ', 'T')); return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }); });
        var values = last10.map(function (r) { return parseInt(r.total_products, 10) || 0; });
        var colors = last10.map(function (r) { return r.context === 'cron' ? 'rgba(249,115,22,.8)' : 'rgba(99,102,241,.8)'; });
        if (productsChart) productsChart.destroy();
        productsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Productos', data: values, backgroundColor: colors, borderRadius: 4 }] },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return c.parsed.y + ' productos'; } } } },
                scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 }, precision: 0 } } }
            }
        });
    }

    // ── Recent Activity Table ─────────────────────────────────────────────────

    function renderActivityTable(lastRuns) {
        $('#dpuwoo-activity-loading').hide();
        if (!lastRuns || !lastRuns.length) { $('#dpuwoo-activity-empty').show(); return; }
        var tbody = $('#dpuwoo-activity-tbody').empty();
        $.each(lastRuns, function (i, run) {
            var row = $('<tr>').css('border-bottom', '1px solid #f9fafb');
            row.append($('<td>').css('padding', '9px 10px').html('<span style="font-weight:500;color:#111827;">' + humanizeDate(run.date) + '</span><br><span style="font-size:11px;color:#9ca3af;">' + timeAgo(run.date) + '</span>'));
            row.append($('<td>').css('padding', '9px 10px').html(contextBadge(run.context)));
            row.append($('<td>').css({ padding: '9px 10px', textAlign: 'right', fontWeight: '500', color: '#374151' }).text(run.dollar_value ? '$' + parseFloat(run.dollar_value).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '—'));
            row.append($('<td>').css({ padding: '9px 10px', textAlign: 'right', color: '#374151' }).text(run.total_products ? parseInt(run.total_products).toLocaleString('es-AR') : '0'));
            row.append($('<td>').css({ padding: '9px 10px', textAlign: 'right' }).html(formatPct(run.percentage_change)));
            var actions = $('<td>').css({ padding: '9px 10px', textAlign: 'right', whiteSpace: 'nowrap' });
            actions.append($('<a>').attr('href', dpuwoo_dashboard.logs_url).css({ fontSize: '12px', color: '#6366f1', textDecoration: 'none', marginRight: '8px', fontWeight: '500' }).text('Ver'));
            actions.append($('<button>').css({ fontSize: '12px', color: '#ef4444', background: 'none', border: 'none', cursor: 'pointer', padding: 0, fontWeight: '500' }).text('Revertir').attr('data-run-id', run.id).addClass('dpuwoo-revert-run-btn'));
            row.append(actions);
            tbody.append(row);
        });
        $('#dpuwoo-activity-table').show();
    }

    // ── Simulation ────────────────────────────────────────────────────────────
    // Uses correct field names from the AJAX response:
    // - data.batch_info.total_batches, data.batch_info.current_batch
    // - data.summary.updated, data.summary.skipped, data.summary.errors
    // - data.threshold_met (false = blocked by threshold/direction)

    var simRunning  = false;
    var simBatch    = 0;
    var simUpdated  = 0;
    var simSkipped  = 0;
    var simTotBatch = 1;

    function simIcon(animate) {
        var el = document.getElementById('dpuwoo-sim-icon');
        if (!el) return;
        el.style.animation = animate ? 'spin 1s linear infinite' : '';
    }

    function simSetUI(label, progress) {
        $('#dpuwoo-sim-label').text(label);
        if (progress !== undefined) {
            $('#dpuwoo-sim-progress-wrap').show();
            $('#dpuwoo-sim-progress-bar').css('width', Math.min(progress, 100) + '%');
            $('#dpuwoo-sim-progress-label').text(label);
        }
    }

    function simReset() {
        simRunning  = false;
        simBatch    = 0;
        simUpdated  = 0;
        simSkipped  = 0;
        simTotBatch = 1;
        simIcon(false);
        $('#dpuwoo-btn-simulate').prop('disabled', false);
        $('#dpuwoo-sim-label').text('Simular ahora');
        $('#dpuwoo-sim-progress-wrap').hide();
        $('#dpuwoo-sim-progress-bar').css('width', '0%');
    }

    function simShowResult() {
        var msg;
        if (simUpdated > 0) {
            msg = simUpdated.toLocaleString('es-AR') + ' producto' + (simUpdated > 1 ? 's' : '') + ' cambiarían de precio';
            if (simSkipped > 0) msg += ' · ' + simSkipped + ' omitidos';
        } else {
            msg = 'Ningún producto cambiaría con la configuración actual';
            if (simSkipped > 0) msg += ' (' + simSkipped + ' omitidos)';
        }
        $('#dpuwoo-sim-result-title').text('Resultado de simulación');
        $('#dpuwoo-sim-summary').text(msg);
        $('#dpuwoo-sim-actions').show();
        $('#dpuwoo-apply-progress-wrap').hide();
        $('#dpuwoo-sim-result').show();
        // Disable apply button if nothing to update
        $('#dpuwoo-btn-apply').prop('disabled', simUpdated === 0).css('opacity', simUpdated === 0 ? '.5' : '1');
        simReset();
    }

    function simProcessBatch() {
        $.ajax({
            url:      dpuwoo_ajax.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: { action: 'dpuwoo_simulate_batch', nonce: dpuwoo_ajax.nonce, batch: simBatch }
        })
        .done(function (res) {
            if (!res.success) {
                simSetUI(res.data && res.data.message ? res.data.message : 'Error en la simulación');
                simIcon(false);
                $('#dpuwoo-btn-simulate').prop('disabled', false);
                simRunning = false;
                return;
            }
            var data       = res.data;
            var batchInfo  = data.batch_info || {};
            var summary    = data.summary    || {};

            // Threshold blocked?
            if (data.threshold_met === false) {
                var reason = data.message || 'Variación no alcanza el umbral mínimo';
                $('#dpuwoo-sim-result-title').text('Simulación bloqueada');
                $('#dpuwoo-sim-summary').text(reason);
                $('#dpuwoo-sim-actions').hide();
                $('#dpuwoo-sim-result').show();
                simReset();
                return;
            }

            simTotBatch  = parseInt(batchInfo.total_batches, 10) || 1;
            simUpdated  += parseInt(summary.updated, 10) || 0;
            simSkipped  += parseInt(summary.skipped, 10) || 0;

            var pct = Math.round(((simBatch + 1) / simTotBatch) * 100);
            simSetUI('Simulando... ' + (simBatch + 1) + ' / ' + simTotBatch, pct);

            if (simBatch + 1 < simTotBatch) {
                simBatch++;
                simProcessBatch();
            } else {
                simSetUI('Completado', 100);
                setTimeout(simShowResult, 350);
            }
        })
        .fail(function () {
            simSetUI('Error de conexión — reintentar');
            simIcon(false);
            $('#dpuwoo-btn-simulate').prop('disabled', false);
            simRunning = false;
        });
    }

    function startSimulation() {
        if (simRunning) return;
        simRunning  = true;
        simBatch    = 0;
        simUpdated  = 0;
        simSkipped  = 0;
        simTotBatch = 1;
        $('#dpuwoo-sim-result').hide();
        $('#dpuwoo-btn-simulate').prop('disabled', true);
        simIcon(true);
        simSetUI('Iniciando simulación...', 0);
        simProcessBatch();
    }

    // ── Apply (real update) ────────────────────────────────────────────────────

    var applyRunning = false;
    var applyBatch   = 0;
    var applyRunId   = 0;
    var applyTotBat  = 1;
    var applyUpdated = 0;

    function applyIcon(animate) {
        var el = document.getElementById('dpuwoo-apply-icon');
        if (!el) return;
        el.style.animation = animate ? 'spin 1s linear infinite' : '';
    }

    function applySetUI(label, progress) {
        $('#dpuwoo-apply-label').text(label);
        if (progress !== undefined) {
            $('#dpuwoo-apply-progress-wrap').show();
            $('#dpuwoo-apply-progress-bar').css('width', Math.min(progress, 100) + '%');
            $('#dpuwoo-apply-progress-label').text(label);
        }
    }

    function applyShowResult(success) {
        applyRunning = false;
        applyIcon(false);
        $('#dpuwoo-btn-apply').prop('disabled', false);
        if (success) {
            $('#dpuwoo-sim-result-title').text('✓ Precios actualizados');
            $('#dpuwoo-sim-summary').text(applyUpdated.toLocaleString('es-AR') + ' producto' + (applyUpdated > 1 ? 's' : '') + ' actualizados correctamente');
            $('#dpuwoo-sim-actions').hide();
            applySetUI('Completado', 100);
            // Refresh activity table after a moment
            setTimeout(function () { loadDashboardData(true); }, 800);
        } else {
            $('#dpuwoo-sim-result-title').text('Error al aplicar');
            applySetUI('Error');
        }
    }

    function applyProcessBatch() {
        var postData = { action: 'dpuwoo_update_batch', nonce: dpuwoo_ajax.nonce, batch: applyBatch };
        if (applyRunId) postData.run_id = applyRunId;

        $.ajax({ url: dpuwoo_ajax.ajax_url, type: 'POST', dataType: 'json', data: postData })
        .done(function (res) {
            if (!res.success) {
                applySetUI(res.data && res.data.message ? res.data.message : 'Error al actualizar');
                applyIcon(false);
                $('#dpuwoo-btn-apply').prop('disabled', false);
                applyRunning = false;
                return;
            }
            var data      = res.data;
            var batchInfo = data.batch_info || {};
            var summary   = data.summary   || {};

            // Capture run_id from batch 0
            if (applyBatch === 0 && data.run_id) applyRunId = data.run_id;

            applyTotBat   = parseInt(batchInfo.total_batches, 10) || 1;
            applyUpdated += parseInt(summary.updated, 10) || 0;

            var pct = Math.round(((applyBatch + 1) / applyTotBat) * 100);
            applySetUI('Actualizando... ' + (applyBatch + 1) + ' / ' + applyTotBat, pct);

            if (applyBatch + 1 < applyTotBat) {
                applyBatch++;
                applyProcessBatch();
            } else {
                applySetUI('Completado', 100);
                setTimeout(function () { applyShowResult(true); }, 350);
            }
        })
        .fail(function () {
            applySetUI('Error de conexión');
            applyIcon(false);
            $('#dpuwoo-btn-apply').prop('disabled', false);
            applyRunning = false;
        });
    }

    function startApply() {
        if (applyRunning) return;
        if (!confirm('¿Confirmar la actualización de precios en la tienda?')) return;
        applyRunning = true;
        applyBatch   = 0;
        applyRunId   = 0;
        applyUpdated = 0;
        applyTotBat  = 1;
        $('#dpuwoo-btn-apply').prop('disabled', true);
        applyIcon(true);
        applySetUI('Aplicando cambios...', 0);
        applyProcessBatch();
    }

    // ── Revert Run ────────────────────────────────────────────────────────────

    $(document).on('click', '.dpuwoo-revert-run-btn', function () {
        var btn = $(this), runId = btn.data('run-id');
        if (!runId || !confirm('¿Revertir todos los precios de esta ejecución?')) return;
        btn.text('...').prop('disabled', true);
        $.post(dpuwoo_ajax.ajax_url, { action: 'dpuwoo_revert_run', nonce: dpuwoo_ajax.nonce, run_id: runId })
        .done(function (res) {
            if (res.success) { btn.closest('tr').css({ opacity: '.4', transition: 'opacity .4s' }); btn.text('Revertido'); }
            else { alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Error')); btn.text('Revertir').prop('disabled', false); }
        })
        .fail(function () { alert('Error de conexión.'); btn.text('Revertir').prop('disabled', false); });
    });

    // ── Dashboard data loader ─────────────────────────────────────────────────

    function loadDashboardData(refreshOnly) {
        if (!refreshOnly) {
            $('#dpuwoo-activity-loading').show();
            $('#dpuwoo-activity-table').hide();
            $('#dpuwoo-activity-empty').hide();
        }
        $.ajax({
            url:      dpuwoo_dashboard.ajax_url,
            type:     'POST',
            dataType: 'json',
            data:     { action: 'dpuwoo_get_dashboard_stats', nonce: dpuwoo_dashboard.nonce }
        })
        .done(function (res) {
            if (!res.success) return;
            var d = res.data;
            populateKpis(d);
            populateStats(d.stats);
            if (!refreshOnly) {
                renderRateChart(d.chart_runs);
                renderProductsChart(d.chart_runs);
            }
            renderActivityTable(d.last_runs);
        })
        .fail(function () { $('#dpuwoo-activity-loading').text('Error al cargar datos.'); });
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    $(function () {
        $('#dpuwoo-btn-simulate').on('click', startSimulation);
        $('#dpuwoo-btn-apply').on('click', startApply);
        $('#dpuwoo-sim-close').on('click', function () { $('#dpuwoo-sim-result').hide(); });
        loadDashboardData(false);
    });

}(jQuery));
