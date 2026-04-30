(function ($) {
    'use strict';

    function getSettingsMetadata() {
        var $meta = $('#prixy-settings-metadata');
        return {
            productsDone: parseInt($meta.data('products-done'), 10) || 0,
            totalProducts: parseInt($meta.data('total-products'), 10) || 0,
            originRate: parseFloat($meta.data('origin-rate')) || 0,
        };
    }

    function formatButtonIcon(text) {
        return '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> ' + text;
    }

    function updateSaveButton($btn, text, disabled) {
        $btn.prop('disabled', !!disabled).html(formatButtonIcon(text));
    }

    function showMessage($msg, html) {
        $msg.html(html);
    }

    function renderProductsTable(products, count) {
        var html = '<div class="prixy-origin-rate-results">';
        html += '<div class="prixy-origin-rate-results__header"><svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>✓ ' + count + ' productos procesados</span></div>';
        html += '<div class="prixy-origin-rate-results__table-wrap"><table class="prixy-origin-rate-table"><thead><tr><th>Producto</th><th class="text-right">Precio ARS</th><th class="text-right">Precio USD</th></tr></thead><tbody>';

        products.forEach(function (product) {
            html += '<tr>';
            html += '<td>' + product.name + '</td>';
            html += '<td class="text-right">$' + product.ars + '</td>';
            html += '<td class="text-right prixy-origin-rate-table__usd">$' + product.usd + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
        return html;
    }

    function initializeOriginRate() {
        var $input = $('#prixy-origin-rate');
        var $btn = $('#prixy-save-origin-rate');
        var $msg = $('#prixy-rate-msg');

        if (!$input.length || !$btn.length || !$msg.length) {
            return;
        }

        var currentVal = $input.val();

        if (!currentVal || currentVal === '' || currentVal === '0') {
            updateSaveButton($btn, 'Cargando...', true);
            showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--info">Obteniendo tasa automáticamente...</span>');

            if (typeof prixy_ajax === 'undefined') {
                showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--error">prixy_ajax no definido</span>');
                updateSaveButton($btn, 'Guardar y continuar', false);
                return;
            }

            $.post(prixy_ajax.ajax_url, {
                action: 'prixy_get_current_rate',
                nonce: prixy_ajax.nonce
            }, function (res) {
                if (res.success && res.data && res.data.rate > 0) {
                    $input.val(res.data.rate.toFixed(2));
                    updateSaveButton($btn, 'Confirmar y continuar', false);
                    showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--success">✓ Tasa: $' + res.data.rate.toFixed(2) + '</span>');
                } else {
                    updateSaveButton($btn, 'Guardar y continuar', false);
                    showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--warning">Ingresa manualmente.</span>');
                }
            }, 'json').fail(function (xhr, status, error) {
                updateSaveButton($btn, 'Guardar y continuar', false);
                showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--error">Error. Ingresa manualmente.</span>');
                console.error('DPUWOO origin rate auto-load failed:', status, error, xhr.responseText);
            });
        }
    }

    function bindSaveOriginRate() {
        var $btn = $('#prixy-save-origin-rate');
        var $input = $('#prixy-origin-rate');
        var $msg = $('#prixy-rate-msg');

        if (!$btn.length || !$input.length || !$msg.length) {
            return;
        }

        $btn.on('click', function (e) {
            e.preventDefault();
            var rate = parseFloat($input.val());

            if (!rate || rate <= 0) {
                showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--error">Valor inválido.</span>');
                return;
            }

            updateSaveButton($btn, 'Procesando...', true);
            showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--info">Procesando productos...</span>');

            $.ajax({
                url: prixy_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'prixy_save_origin_rate',
                    value: rate,
                    nonce: prixy_ajax.nonce
                }
            }).done(function (res) {
                if (res.success) {
                    var products = res.data.products || [];
                    var count = res.data.processed || 0;

                    if (products.length > 0) {
                        showMessage($msg, renderProductsTable(products, count));
                    } else {
                        showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--success">✓ Tasa guardada. ' + count + ' productos configurados.</span>');
                    }

                    $btn.hide();
                    setTimeout(function () {
                        window.location.reload();
                    }, 3000);
                } else {
                    updateSaveButton($btn, 'Guardar y continuar', false);
                    showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--error">Error: ' + (res.data?.message || 'No se pudo guardar') + '</span>');
                }
            }).fail(function () {
                updateSaveButton($btn, 'Guardar y continuar', false);
                showMessage($msg, '<span class="prixy-origin-rate-status prixy-origin-rate-status--error">Error de conexión.</span>');
            });
        });
    }

    function bindSetupProcess() {
        var metadata = getSettingsMetadata();
        var setupProcessed = metadata.productsDone;
        var setupTotal = metadata.totalProducts;
        var setupRate = metadata.originRate;
        var setupBatchSize = 5;

        var $btn = $('#start-setup-btn');
        var $panel = $('#setup-progress-panel');
        var $count = $('#setup-count');
        var $bar = $('#setup-progress-bar');
        var $list = $('#setup-products-list');
        var $complete = $('#setup-complete-msg');

        if (!$btn.length || !$panel.length || !$count.length || !$bar.length || !$list.length) {
            return;
        }

        function updateSetupProgress() {
            var percent = setupTotal > 0 ? Math.round((setupProcessed / setupTotal) * 100) : 0;
            $bar.css('width', percent + '%');
            $count.text(setupProcessed + ' / ' + setupTotal);
        }

        function completeSetup() {
            $bar.css('width', '100%');
            $count.text(setupTotal + ' / ' + setupTotal);
            $btn.text('✓ Completado').addClass('prixy-btn--success').prop('disabled', false);
            $complete.show();
        }

        function processSetupBatch() {
            if (setupProcessed >= setupTotal) {
                completeSetup();
                return;
            }

            $.post(prixy_ajax.ajax_url, {
                action: 'prixy_first_setup_batch',
                nonce: prixy_ajax.nonce,
                offset: setupProcessed,
                limit: setupBatchSize,
                rate: setupRate
            }, function (response) {
                if (response.success && response.data.products) {
                    setupProcessed += response.data.products.length;
                    updateSetupProgress();

                    response.data.products.forEach(function (p) {
                        $list.append('<div class="prixy-origin-rate-setup-row"><span>' + p.name + '</span><span>$' + p.ars + ' → $' + p.usd + '</span></div>');
                    });

                    $list.scrollTop($list[0].scrollHeight);
                    setTimeout(processSetupBatch, 100);
                } else {
                    setTimeout(processSetupBatch, 100);
                }
            }).fail(function () {
                setupProcessed += setupBatchSize;
                updateSetupProgress();
                setTimeout(processSetupBatch, 100);
            });
        }

        $btn.on('click', function () {
            if (setupRate <= 0) {
                alert('Primero configurá la tasa de referencia en el formulario.');
                return;
            }

            $panel.show();
            $btn.prop('disabled', true).text('Procesando...');
            processSetupBatch();
        });
    }

    $(document).ready(function () {
        console.log('DPUWOO settings page script initialized');
        initializeOriginRate();
        bindSaveOriginRate();
        bindSetupProcess();
    });
})(jQuery);
