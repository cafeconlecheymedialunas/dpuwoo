jQuery(document).ready(function ($) {
    const providerSelect = $('#dpuwoo_api_provider');
    const currencySelect = $('#dpuwoo_reference_currency');
    const loadingSpinner = $('#dpuwoo_currency_loading');
    const baseCountry = $('#dpuwoo_base_country').val();
    const baseCurrency = dpuwoo_ajax?.base_currency || '';
    
    // Store the saved reference currency value
    let savedReferenceCurrency = currencySelect.val() || '';

    // Función para decodificar entidades HTML
    function decodeHtmlEntities(text) {
        if (!text) return text;
        
        // Usar el DOM para decodificar
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // Función para mostrar/ocultar el campo de tasa de cambio de origen
    function toggleOriginRateField() {
        const hasSelectedCurrency = currencySelect.val() && currencySelect.val() !== '';
        const $container = $('#dpuwoo_origin_rate_container');
        
        if (hasSelectedCurrency) {
            $container.slideDown(300);
        } else {
            $container.slideUp(300);
        }
    }
    
    // Función para adjuntar eventos de selección (se llama después de cada reinitialización de Select2)
    function attachSelectEvents() {
        // Remover eventos anteriores para evitar duplicados
        $('#dpuwoo_reference_currency').off('select2:select select2:selecting change');
        
        // Agregar evento Select2 select
        $('#dpuwoo_reference_currency').on('select2:select', function(e) {
            const selectedData = e.params.data;
            
            // Verificar que tenemos los datos de moneda correctos
            if (selectedData && selectedData.currency) {
                const currencyData = selectedData.currency;
                
                // Extraer el valor directamente del objeto currency
                let rateValue = null;
                
                // El valor está directamente en currencyData.value
                if (currencyData.value !== undefined && currencyData.value !== null) {
                    rateValue = currencyData.value;
                } else if (typeof currencyData === 'number') {
                    rateValue = currencyData;
                }
                
                if (rateValue && !isNaN(parseFloat(rateValue))) {
                    const numericRate = parseFloat(rateValue);
                    
                    // Obtener el campo de tasa de cambio de origen
                    const originRateInput = $('#dpuwoo_origin_exchange_rate');
                    
                    if (originRateInput.length > 0) {
                        const currentRate = parseFloat(originRateInput.val());
                        
                        // Solo actualizar si el valor es diferente
                        if (isNaN(currentRate) || Math.abs(numericRate - currentRate) > 0.0001) {
                            originRateInput.val(numericRate);
                            
                            // Mostrar notificación de actualización
                            const notification = $('<div class="notice notice-success inline" style="margin: 5px 0; padding: 5px 10px; font-size: 13px;">')
                                .html('✅ Tasa de cambio de origen actualizada a <strong>' + numericRate.toFixed(4) + '</strong>');
                            
                            // Insertar notificación después del campo
                            originRateInput.parent().find('.notice').remove(); // Remover notificaciones anteriores
                            originRateInput.parent().append(notification);
                            
                            // Remover notificación después de 3 segundos
                            setTimeout(function() {
                                notification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }, 3000);
                        } else {
                            // Mostrar notificación de que el valor ya estaba correcto
                            const notification = $('<div class="notice notice-info inline" style="margin: 5px 0; padding: 5px 10px; font-size: 13px;">')
                                .html('ℹ️ La tasa de cambio de origen ya está configurada correctamente (<strong>' + currentRate.toFixed(4) + '</strong>)');
                            
                            // Insertar notificación después del campo
                            originRateInput.parent().find('.notice').remove(); // Remover notificaciones anteriores
                            originRateInput.parent().append(notification);
                            
                            // Remover notificación después de 2 segundos
                            setTimeout(function() {
                                notification.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }, 2000);
                        }
                    }
                }
            }
        });
        
        // También agregar evento change tradicional como fallback
        $('#dpuwoo_reference_currency').on('change', function() {
            const selectedValue = $(this).val();
            
            // Mostrar/ocultar campo de tasa según selección
            toggleOriginRateField();
        });
        
    }
    
    // Adjuntar eventos iniciales
    attachSelectEvents();
    
    // Establecer visibilidad inicial del campo de tasa de cambio
    toggleOriginRateField();
    // Inicializar Select2 en el select de moneda
    currencySelect.select2({
        placeholder: 'Seleccione un proveedor primero',
        allowClear: false,
        width: '100%',
        disabled: true,
        dropdownParent: currencySelect.parent(),
        language: {
            noResults: function() {
                return "No se encontraron monedas";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    }).on('select2:open', function() {
    }).on('select2:close', function() {
    }).on('select2:selecting', function(e) {
    });
    
    // IMPORTANTE: Establecer el valor guardado después de inicializar Select2
    // Esto asegura que Select2 muestre correctamente la opción seleccionada
    const initialValue = currencySelect.val();
    if (initialValue && initialValue !== '') {
        // Esperar un momento para que Select2 se inicialice completamente
        setTimeout(function() {
            currencySelect.val(initialValue).trigger('change');
        }, 100);
    }
    
    // Función para mostrar estado de carga en el select
    function showLoadingState(providerName = '') {
        const loadingText = providerName ? 
            `Cargando monedas de ${providerName}...` : 
            'Cargando monedas...';
        
        const loadingOptions = [{
            id: '',
            text: loadingText,
            disabled: true
        }];
        
        currencySelect.empty().select2({
            data: loadingOptions,
            placeholder: loadingText,
            disabled: true,
            minimumResultsForSearch: -1 // Ocultar búsqueda durante carga
        });
        
        // Mantener el valor guardado durante la carga
        $('#dpuwoo_currency_description').text(loadingText);
    }

    // Función para limpiar y deshabilitar el select
    function clearAndDisableSelect() {
        const disabledOptions = [{
            id: '',
            text: 'Seleccione un proveedor',
            disabled: true
        }];
        
        currencySelect.empty().select2({
            data: disabledOptions,
            placeholder: 'Seleccione un proveedor primero',
            disabled: true,
            minimumResultsForSearch: -1
        });
        
        // Mantener el valor guardado incluso cuando se deshabilita
        $('#dpuwoo_currency_description').text('Seleccione un proveedor para cargar las monedas disponibles.');
    }

    // Función para cargar monedas
    function loadCurrenciesForProvider(provider) {
        // Mostrar indicador de carga visual
        loadingSpinner.addClass('is-active');
        
        // Obtener nombre del proveedor para mostrar en mensaje
        const providerName = providerSelect.find('option:selected').text() || provider;
        
        // Mostrar estado de carga en el select
        showLoadingState(providerName);

        $.ajax({
            url: dpuwoo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dpuwoo_get_currencies',
                provider: provider,
                country: baseCountry,
                nonce: dpuwoo_ajax.nonce
            },
            dataType: 'json',
            beforeSend: function() {
                // Ya mostramos el estado de carga en showLoadingState()
            },
            success: function(response) {
                if (response.success && response.data) {
                    
                    // Preparar datos para Select2
                    let currencyOptions = [];
                    
                    // Si hay monedas en la respuesta
                    if (Array.isArray(response.data.currencies) && response.data.currencies.length > 0) {
                        // Procesar cada moneda
                        $.each(response.data.currencies, function(index, currency) {
                            // Decodificar nombres de moneda
                            if (currency.name) {
                                currency.name = decodeHtmlEntities(currency.name);
                            }
                            
                            // No incluir la moneda base
                            if (currency.code === baseCurrency || currency.key === baseCurrency) {
                                return;
                            }
                            
                            // Determinar texto según el tipo
                            let optionText, optionValue;
                            
                            if (currency.type === 'dolar') {
                                optionText = 'Dólar ' + (currency.name || currency.key);
                                optionValue = 'dolar_' + currency.key;
                            } else {
                                // Decodificar también el código si es necesario
                                const decodedCode = decodeHtmlEntities(currency.code);
                                
                                // Remover el prefijo de moneda de la tienda del código para mostrar
                                let displayCode = decodedCode;
                                if (baseCurrency && decodedCode.startsWith(baseCurrency + '_')) {
                                    displayCode = decodedCode.substring(baseCurrency.length + 1);
                                }
                                
                                optionText = (currency.name || displayCode);
                                optionValue = 'moneda_' + decodedCode;
                            }
                            
                            currencyOptions.push({
                                id: optionValue,
                                text: optionText,
                                currency: currency
                            });
                        });
                    }
                    
                    // Si no hay monedas, mostrar mensaje
                    if (currencyOptions.length === 0) {
                        const noDataOptions = [{
                            id: '',
                            text: 'No hay monedas disponibles',
                            disabled: true
                        }];
                        
                        currencySelect.empty().select2({
                            data: noDataOptions,
                            placeholder: 'Sin monedas disponibles',
                            disabled: true
                        });
                        
                        $('#dpuwoo_currency_description').text(
                            `El proveedor ${providerName} no proporciona monedas disponibles.`
                        );
                        return;
                    }
                    
                    // Ordenar opciones alfabéticamente
                    currencyOptions.sort((a, b) => a.text.localeCompare(b.text));
                    
                    // Añadir una opción inicial por defecto
                    const defaultOption = {
                        id: '',
                        text: '-- Seleccione una moneda --',
                        disabled: false
                    };
                    
                    currencyOptions.unshift(defaultOption);
                    
                    // Actualizar el select con las nuevas opciones manteniendo las estáticas
                    // Primero obtener las opciones estáticas existentes
                    const staticOptions = [];
                    currencySelect.find('option').each(function() {
                        const $opt = $(this);
                        // Solo mantener las opciones estáticas (no las cargadas dinámicamente)
                        if (!$opt.attr('data-dynamic')) {
                            staticOptions.push({
                                id: $opt.val(),
                                text: $opt.text(),
                                selected: $opt.is(':selected')
                            });
                        }
                    });
                    
                    // Combinar opciones estáticas con las dinámicas
                    const allOptions = [...staticOptions];
                    
                    // Agregar las opciones dinámicas
                    currencyOptions.forEach(opt => {
                        // Marcar como dinámicas para identificación futura
                        opt.dynamic = true;
                        allOptions.push(opt);
                    });
                    
                    // Re-inicializar Select2 con todas las opciones
                    currencySelect.empty().select2({
                        data: allOptions,
                        placeholder: 'Seleccione una moneda o escriba para buscar',
                        allowClear: false,
                        disabled: false,
                        templateResult: formatCurrencyOption,
                        templateSelection: formatCurrencySelection,
                        escapeMarkup: function(markup) {
                            return markup;
                        }
                    });
                    
                    // IMPORTANTE: Re-attach events after Select2 reinitialization
                    attachSelectEvents();
                    
                    // Restaurar el valor guardado si existe y está disponible
                    let valueToSelect = '';
                    if (savedReferenceCurrency) {
                        // Verificar si el valor guardado está disponible en las opciones
                        const availableOptions = currencyOptions.filter(opt => opt.id === savedReferenceCurrency);
                        if (availableOptions.length > 0) {
                            valueToSelect = savedReferenceCurrency;
                        }
                    }
                    
                    // Si no hay valor guardado o no está disponible, usar la opción por defecto
                    currencySelect.val(valueToSelect).trigger('change');
                    
                    // Actualizar descripción
                    $('#dpuwoo_currency_description').html(
                        `<strong>${providerName}</strong> - ${currencyOptions.length - 1} monedas disponibles. ` +
                        (baseCurrency ? 
                            `La moneda base (${baseCurrency}) está excluida.` : 
                            '')
                    );
                    
                    // Mostrar/ocultar campo de tasa de cambio de origen según si hay moneda seleccionada
                    toggleOriginRateField();
                    
                } else {
                    // Error en la respuesta
                    showCurrencyError(providerName);
                }
            },
            error: function (xhr, status, error) {
                
                showCurrencyError(providerSelect.find('option:selected').text());
            },
            complete: function () {
                // Ocultar indicador de carga
                loadingSpinner.removeClass('is-active');
            }
        });
    }

    // Función para formatear la opción en el dropdown
    function formatCurrencyOption(currency) {
        if (!currency.id) {
            return currency.text;
        }
        
        if (currency.disabled) {
            return $('<span class="text-muted">' + currency.text + '</span>');
        }
        
        const $container = $('<span class="currency-option"></span>');
        
        // Para la opción por defecto
        if (currency.id === '') {
            $container.text(currency.text).addClass('text-muted');
            return $container;
        }
        
        // Mostrar solo el texto sin la tasa
        $container.text(currency.text);
        
        // Almacenar la tasa en atributo data para uso posterior
        if (currency.currency && currency.currency.value) {
            $container.attr('data-rate', currency.currency.value);
            $container.attr('data-buy', currency.currency.buy || currency.currency.value);
            $container.attr('data-sell', currency.currency.sell || currency.currency.value);
            $container.attr('data-updated', currency.currency.updated || '');
        }
        
        return $container;
    }

    // Función para formatear la selección actual
    function formatCurrencySelection(currency) {
        if (!currency.id) {
            return currency.text;
        }
        
        if (currency.disabled) {
            return $('<span class="text-muted">' + currency.text + '</span>');
        }
        
        // Para la opción por defecto
        if (currency.id === '') {
            return $('<span class="text-muted">' + currency.text + '</span>');
        }
        
        // Mostrar solo el nombre en la selección
        return $('<span class="currency-selection"></span>').text(currency.text);
    }

    // Función para mostrar error
    function showCurrencyError(providerName = '') {
        const errorOptions = [{
            id: '',
            text: 'Error al cargar monedas',
            disabled: true
        }, {
            id: 'USD',
            text: 'USD - Dólar Estadounidense (usar como respaldo)'
        }];
        
        currencySelect.empty().select2({
            data: errorOptions,
            placeholder: 'Error al cargar - Seleccione USD',
            disabled: false
        });
        
        // Restaurar el valor guardado si existe, de lo contrario usar USD
        let valueToSelect = 'USD'; // Valor por defecto
        if (savedReferenceCurrency && savedReferenceCurrency !== '') {
            // Verificar si el valor guardado está disponible
            if (savedReferenceCurrency === 'USD' || currencySelect.find('option[value="' + savedReferenceCurrency + '"]').length > 0) {
                valueToSelect = savedReferenceCurrency;
            }
        }
        
        currencySelect.val(valueToSelect).trigger('change');
        
        const errorMsg = providerName ? 
            `Error al cargar monedas de ${providerName}. Se usará USD por defecto.` :
            'Error al cargar las monedas disponibles. Se usará USD por defecto.';
        
        $('#dpuwoo_currency_description').text(errorMsg);
    }

    // Cargar monedas al cambiar el proveedor
    providerSelect.on('change', function () {
        const selectedProvider = $(this).val();
        
        // Actualizar el valor guardado cuando cambia
        savedReferenceCurrency = currencySelect.val() || '';
        
        // Limpiar selección anterior de moneda
        localStorage.removeItem('dpuwoo_last_currency');
        
        if (selectedProvider) {
            // Limpiar opciones actuales y mostrar estado de carga
            showLoadingState($(this).find('option:selected').text());
            
            // Hacer la consulta después de un pequeño delay para que se vea el estado de carga
            setTimeout(function() {
                loadCurrenciesForProvider(selectedProvider);
            }, 300);
        } else {
            // Si no hay proveedor seleccionado, limpiar y deshabilitar
            clearAndDisableSelect();
        }
    });

    // Cargar monedas automáticamente al cargar la página si hay un proveedor seleccionado
    if (providerSelect.val()) {
        // Actualizar el valor guardado
        savedReferenceCurrency = currencySelect.val() || '';
        
        // Mostrar estado de carga inmediatamente
        showLoadingState(providerSelect.find('option:selected').text());
        
        // Pequeño delay para asegurar que Select2 esté inicializado
        setTimeout(function () {
            loadCurrenciesForProvider(providerSelect.val());
        }, 500);
    } else {
        // Si no hay proveedor seleccionado, mostrar estado inicial
        clearAndDisableSelect();
    }

    // CSS adicional para mejorar la apariencia
    const customCSS = `
        .currency-option {
            font-weight: 500;
        }
        .currency-selection {
            font-weight: 600;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #007cba;
            color: white;
        }
        .select2-container--default .select2-results__option[aria-disabled=true] {
            color: #999;
            font-style: italic;
        }
        .select2-container--default .select2-results__option:first-child {
            color: #666;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }
        #dpuwoo_currency_loading {
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }
        .select2-container--default.select2-container--disabled .select2-selection--single {
            background-color: #f5f5f5;
            border-color: #ddd;
        }
    `;
    
    // Agregar CSS solo si no existe
    if (!$('#dpuwoo-select2-css').length) {
        $('<style id="dpuwoo-select2-css">').text(customCSS).appendTo('head');
    }

    // Manejar el evento de guardado de formulario
    $('#dpuwoo_settings_form').on('submit', function (e) {
        // Asegurar que el valor de la moneda se capture antes de enviar
        if (currencySelect.val() && currencySelect.val() !== '') {
            localStorage.setItem('dpuwoo_last_currency', currencySelect.val());
        }
        
        // También asegurar que el valor esté disponible para el guardado
        const referenceCurrencyValue = currencySelect.val();
        if (referenceCurrencyValue) {
            // Crear un campo oculto temporal para asegurar que el valor se envíe
            const $hiddenField = $('<input type="hidden" name="dpuwoo_settings[reference_currency]" value="' + referenceCurrencyValue + '">');
            $(this).append($hiddenField);
            
            // Remover el campo oculto después de un corto tiempo
            setTimeout(() => {
                $hiddenField.remove();
            }, 100);
        }
    });

    // Intentar restaurar la última selección (solo si hay un proveedor seleccionado)
    const lastCurrency = localStorage.getItem('dpuwoo_last_currency');
    if (lastCurrency && providerSelect.val()) {
        // Esperar a que las opciones estén cargadas
        setTimeout(function () {
            if (currencySelect.find('option[value="' + lastCurrency + '"]').length > 0) {
                currencySelect.val(lastCurrency).trigger('change');
            }
        }, 1500);
    }
});
