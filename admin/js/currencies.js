jQuery(document).ready(function ($) {
    const $provider = $('#dpuwoo_api_provider');
    const $currency = $('#dpuwoo_reference_currency');
    const $spinner = $('#dpuwoo_currency_loading');
    const $desc = $('#dpuwoo_currency_description');
    
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
            url: dpuwoo_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'dpuwoo_get_currencies',
                provider: provider,
                nonce: dpuwoo_ajax.nonce
            },
            success: function(response) {
                $spinner.hide();
                
                if (response.success && response.data && response.data.currencies) {
                    // Guardar en cache local
                    currencyCache[provider] = response.data;
                    localStorage.setItem('dpuwoo_currencies_' + provider, JSON.stringify(response.data));
                    
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
        
        // Intentar cargar selección anterior
        const lastCurrency = localStorage.getItem('dpuwoo_last_currency_' + provider);
        if (lastCurrency) {
            $currency.val(lastCurrency);
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
            localStorage.setItem('dpuwoo_last_currency_' + currentProvider, $currency.val());
        }
        
        // Verificar cache primero
        const cached = localStorage.getItem('dpuwoo_currencies_' + provider);
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
            localStorage.setItem('dpuwoo_last_currency_' + currentProvider, $(this).val());
            
            // Extraer y asignar el valor de la tasa automáticamente
            const selectedOption = $(this).find('option:selected');
            const rateValue = selectedOption.data('rate') || selectedOption.attr('data-rate');
            
            if (rateValue && !isNaN(parseFloat(rateValue))) {
                const numericRate = parseFloat(rateValue);
                const originRateField = $('#dpuwoo_origin_exchange_rate');
                
                if (originRateField.length > 0) {
                    const currentRate = parseFloat(originRateField.val());
                    
                    // Solo actualizar si el valor es diferente
                    if (isNaN(currentRate) || Math.abs(numericRate - currentRate) > 0.0001) {
                        originRateField.val(numericRate);
                        
                        // Mostrar notificación de actualización
                        const notification = $('<div class="notice notice-success inline" style="margin: 5px 0; padding: 5px 10px; font-size: 13px;">')
                            .html('✅ Tasa de cambio de origen actualizada a <strong>' + numericRate.toFixed(4) + '</strong>');
                        
                        // Insertar notificación después del campo
                        originRateField.parent().find('.notice').remove(); // Remover notificaciones anteriores
                        originRateField.parent().append(notification);
                        
                        // Remover notificación después de 3 segundos
                        setTimeout(function() {
                            notification.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }, 3000);
                        
                        console.log('Tasa de cambio actualizada automáticamente:', numericRate);
                    } else {
                        // Mostrar notificación de que el valor ya estaba correcto
                        const notification = $('<div class="notice notice-info inline" style="margin: 5px 0; padding: 5px 10px; font-size: 13px;">')
                            .html('ℹ️ La tasa de cambio de origen ya está configurada correctamente (<strong>' + currentRate.toFixed(4) + '</strong>)');
                        
                        // Insertar notificación después del campo
                        originRateField.parent().find('.notice').remove(); // Remover notificaciones anteriores
                        originRateField.parent().append(notification);
                        
                        // Remover notificación después de 2 segundos
                        setTimeout(function() {
                            notification.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }, 2000);
                    }
                }
            }
            
            // Automáticamente seleccionar "Usar por API" cuando se elige una moneda
            const rateMethodRadios = $('input[name="dpuwoo_settings[rate_generation_method]"]');
            const apiRadio = rateMethodRadios.filter('[value="api"]');
            
            if (apiRadio.length > 0 && !apiRadio.is(':checked')) {
                apiRadio.prop('checked', true);
                console.log('Automáticamente seleccionado: Usar por API');
                
                // Remover el código que deshabilita el campo automáticamente
                // El campo debe permanecer habilitado para que se envíe con el formulario
                /*
                const originRateField = $('#dpuwoo_origin_exchange_rate');
                if (originRateField.length > 0) {
                    originRateField.prop('disabled', true).css('background-color', '#f0f0f0');
                    originRateField.parent().find('.description').text('Campo deshabilitado: la tasa se obtendrá automáticamente por API.');
                }
                */
            }
        }
    });
    
    // Manejar la edición manual del campo de tasa con lápiz
    function handleManualRateEditingWithPencil() {
        const originRateField = $('#dpuwoo_origin_exchange_rate');
        const originRateContainer = $('#dpuwoo_origin_rate_container');
        
        // Agregar lápiz de edición
        if (originRateContainer.length > 0 && $('.edit-pencil').length === 0) {
            const pencilIcon = $('<span>', {
                class: 'edit-pencil dashicons dashicons-edit',
                title: 'Editar tasa manualmente',
                style: 'cursor: pointer; margin-left: 8px; color: #0073aa; vertical-align: middle;'
            });
            
            originRateContainer.find('label').append(pencilIcon);
            
            // Por defecto, dejar el campo habilitado para que se envíe con el formulario
            // originRateField.prop('disabled', true).css('background-color', '#f0f0f0');
            originRateField.parent().find('.description').text('Ingresa la tasa de cambio histórica de tus productos. Esta tasa se usará como referencia para calcular variaciones de precio.');
            
            // Manejar clic en el lápiz
            pencilIcon.on('click', function() {
                if (originRateField.prop('disabled')) {
                    // Habilitar edición
                    originRateField.prop('disabled', false).css('background-color', '');
                    originRateField.focus();
                    originRateField.parent().find('.description').text('Campo habilitado para edición manual. Una vez editado, no se harán consultas adicionales.');
                    
                    // Cambiar icono a guardar
                    $(this).removeClass('dashicons-edit').addClass('dashicons-yes')
                           .attr('title', 'Guardar cambios')
                           .css('color', '#46b450');
                    
                    console.log('Modo edición manual activado');
                } else {
                    // Deshabilitar edición (guardar)
                    originRateField.prop('disabled', true).css('background-color', '#f0f0f0');
                    originRateField.parent().find('.description').text('Tasa editada manualmente. No se realizarán más consultas automáticas.');
                    
                    // Volver a icono de edición
                    $(this).removeClass('dashicons-yes').addClass('dashicons-edit')
                           .attr('title', 'Editar tasa manualmente')
                           .css('color', '#0073aa');
                    
                    console.log('Cambios guardados - modo manual fijado');
                }
            });
        }
    }
    
    // Inicializar el manejo de edición manual con lápiz
    handleManualRateEditingWithPencil();
    const initialProvider = $provider.val();
    if (initialProvider) {
        currentProvider = initialProvider;
        
        // Pequeño delay para asegurar que el DOM esté listo
        setTimeout(() => {
            const cached = localStorage.getItem('dpuwoo_currencies_' + initialProvider);
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