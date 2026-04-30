jQuery(document).ready(function ($) {
    const $provider = $('#prixy_api_provider');
    const $currency = $('#prixy_reference_currency');
    const $spinner = $('#prixy_currency_loading');
    const $desc = $('#prixy_currency_description');
    
    // Estado
    let currentProvider = '';
    let currencyCache = {};
    
    // Función para llamar a la API
    function callCurrencyAPI(provider) {
        // Mostrar cargando
        $spinner.show();
        $currency.prop('disabled', true);
        $currency.html('<option value="">Cargando monedas...</option>');
        $desc.text(`Cargando monedas de ${provider}...`);
        
        // Hacer la petición AJAX
        $.ajax({
            url: prixy_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'prixy_get_currencies',
                provider: provider,
                nonce: prixy_ajax.nonce
            },
            success: function(response) {
                $spinner.hide();
                
                if (response.success && response.data && response.data.currencies) {
                    // Guardar en cache local
                    currencyCache[provider] = response.data;
                    localStorage.setItem('prixy_currencies_' + provider, JSON.stringify(response.data));
                    
                    // Llenar el select con las monedas
                    fillCurrencySelect(response.data.currencies, provider);
                } else {
                    showError('Error al cargar monedas');
                }
            },
            error: function() {
                $spinner.hide();
                showError('Error de conexión con la API');
            }
        });
    }
    
    // Llenar el select de monedas
    function fillCurrencySelect(currencies, provider) {
        // Obtener el valor de referencia guardado en PHP si existe
        const dbValue = $currency.attr('data-saved-value') || $currency.val();
        
        // Limpiar select
        $currency.html('<option value="">-- Seleccione moneda --</option>');
        
        // Agregar cada moneda
        Object.keys(currencies).forEach(key => {
            const currency = currencies[key];
            
            // Crear opción
            const option = $('<option>', {
                value: currency.code || currency.key,
                text: currency.name || currency.code || currency.key
            });
            
            // Agregar data attributes si existen
            if (currency.value) option.data('rate', currency.value);
            if (currency.buy) option.data('buy', currency.buy);
            if (currency.sell) option.data('sell', currency.sell);
            
            $currency.append(option);
        });
        
        // Habilitar select
        $currency.prop('disabled', false);
        
        // Intentar restaurar valor: 
        // 1. Prioridad al valor que ya tenía (DB)
        // 2. Si no hay valor previo, usar localStorage
        let restoredValue = '';
        if (dbValue && $currency.find(`option[value="${dbValue}"]`).length > 0) {
            restoredValue = dbValue;
            console.log('Cargando moneda desde DB:', dbValue);
        } else {
            const lastCurrency = localStorage.getItem('prixy_last_currency_' + provider);
            if (lastCurrency && $currency.find(`option[value="${lastCurrency}"]`).length > 0) {
                restoredValue = lastCurrency;
                console.log('Cargando moneda desde localStorage:', lastCurrency);
            }
        }

        if (restoredValue) {
            // Usar .val() sin disparar .trigger('change') para no sobreescribir la tasa de origen
            $currency.val(restoredValue);
        }
        
        // Actualizar descripción
        const count = Object.keys(currencies).length;
        $desc.html(`<strong>${provider}</strong> - ${count} monedas disponibles`);
    }
    
    // Mostrar error
    function showError(message) {
        $currency.html(`
            <option value="">-- Error --</option>
            <option value="USD">USD (Respaldo)</option>
        `);
        $desc.text(message);
    }
    
    // Cuando cambia el proveedor
    $provider.on('change', function() {
        const provider = $(this).val();
        
        if (!provider) {
            $currency.html('<option value="">Seleccione proveedor</option>');
            $currency.prop('disabled', true);
            $desc.text('Seleccione un proveedor para cargar monedas');
            return;
        }
        
        // Guardar moneda actual del proveedor anterior
        if (currentProvider && $currency.val()) {
            localStorage.setItem('prixy_last_currency_' + currentProvider, $currency.val());
        }
        
        // Verificar cache primero
        const cached = localStorage.getItem('prixy_currencies_' + provider);
        if (cached) {
            try {
                const data = JSON.parse(cached);
                fillCurrencySelect(data.currencies, provider);
                return;
            } catch(e) {
                console.log('Cache inválido, cargando desde API');
            }
        }
        
        // Llamar a la API
        callCurrencyAPI(provider);
        currentProvider = provider;
    });
    
    // Guardar selección de moneda
    $currency.on('change', function() {
        if (currentProvider && $(this).val()) {
            localStorage.setItem('prixy_last_currency_' + currentProvider, $(this).val());
            
            const selectedOption = $(this).find('option:selected');
            const rateValue = selectedOption.data('rate') || selectedOption.attr('data-rate');
            const originRateField = $('#prixy_origin_exchange_rate');
            const isManual = $('input[name="prixy_settings[rate_generation_method]"]:checked').val() === 'manual';
            
            if (rateValue && !isNaN(parseFloat(rateValue)) && originRateField.length > 0) {
                // Si el modo es manual, NO actualizar automáticamente
                if (isManual || !originRateField.prop('readonly')) {
                    console.log('Modo manual detectado, ignorando actualización automática');
                    return;
                }

                const numericRate = parseFloat(rateValue);
                const currentRateValue = parseFloat(originRateField.val());
                
                if (isNaN(currentRateValue) || Math.abs(numericRate - currentRateValue) > 0.0001) {
                    originRateField.val(numericRate);
                    
                    const notification = $('<div id="rate-update-notice" class="notice notice-success inline" style="margin: 5px 0; padding: 5px 10px; font-size: 13px; border-radius: 4px; display: block; width: fit-content;">')
                        .html('✅ Tasa actualizada a <strong>' + numericRate.toFixed(4) + '</strong>');
                    
                    $('#rate-update-notice').remove();
                    originRateField.closest('.prixy-rate-field-group').after(notification);
                    
                    setTimeout(function() {
                        notification.fadeOut(300, function() { $(this).remove(); });
                    }, 3000);
                }
            }
        }
    });
    
    // Manejar la edición manual del campo de tasa con lápiz
    function handleManualRateEditingWithPencil() {
        const originRateField = $('#prixy_origin_exchange_rate');
        const editToggle = $('#prixy_edit_rate_toggle');
        const syncIndicator = $('#prixy_rate_sync_indicator');
        const rateMethodRadios = $('input[name="prixy_settings[rate_generation_method]"]');
        
        // Función central para cambiar entre modos
        function setRateMode(mode) {
            $('#manual-mode-notice').remove();
            
            if (mode === 'manual') {
                // MODO MANUAL
                originRateField.prop('readonly', false).css({
                    'background-color': '#fff',
                    'border-color': '#007cba',
                    'transition': 'all 0.3s ease'
                });
                
                editToggle.removeClass('dashicons-edit').addClass('dashicons-yes').css('color', '#46b450').attr('title', 'Bloquear y guardar');
                syncIndicator.fadeOut(200);
                
                // Sincronizar Radio
                rateMethodRadios.filter('[value="manual"]').prop('checked', true);
                
                // Agregar aviso
                originRateField.closest('.prixy-rate-field-group').after('<div id="manual-mode-notice" class="notice notice-warning inline" style="margin: 5px 0; padding: 5px 10px; font-size: 11px; display: block; width: fit-content; border-radius: 4px;">⚠️ Modo manual: Ingresa el valor que tenían tus productos originalmente.</div>');
            } else {
                // MODO API (Sincronizado)
                originRateField.prop('readonly', true).css({
                    'background-color': '#f0f0f0',
                    'border-color': '#ccc',
                    'transition': 'all 0.3s ease'
                });
                
                editToggle.removeClass('dashicons-yes').addClass('dashicons-edit').css('color', '#007cba').attr('title', 'Editar manualmente');
                syncIndicator.fadeIn(200);
                
                // Sincronizar Radio
                rateMethodRadios.filter('[value="api"]').prop('checked', true);
            }
        }

        if (editToggle.length > 0) {
            // Evento clic en el lápiz
            editToggle.on('click', function(e) {
                e.preventDefault();
                const isReadonly = originRateField.prop('readonly');
                const newMode = isReadonly ? 'manual' : 'api';
                
                console.log('Cambiando modo desde lápiz a:', newMode);
                setRateMode(newMode);
                
                if (newMode === 'manual') {
                    originRateField.focus();
                }
            });

            // Evento cambio en los Radios (Sincronización inversa)
            rateMethodRadios.on('change', function() {
                console.log('Cambiando modo desde Radio a:', $(this).val());
                setRateMode($(this).val());
            });

            // Inicializar estado según el radio seleccionado al cargar
            const initialMode = rateMethodRadios.filter(':checked').val() || 'api';
            setRateMode(initialMode);
        }
    }
    
    // Inicializar el manejo de edición manual con lápiz
    handleManualRateEditingWithPencil();
    const initialProvider = $provider.val();
    if (initialProvider) {
        currentProvider = initialProvider;
        
        // Pequeño delay para asegurar que el DOM esté listo
        setTimeout(() => {
            const cached = localStorage.getItem('prixy_currencies_' + initialProvider);
            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    fillCurrencySelect(data.currencies, initialProvider);
                } catch(e) {
                    callCurrencyAPI(initialProvider);
                }
            } else {
                callCurrencyAPI(initialProvider);
            }
        }, 100);
    }
});