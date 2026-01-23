jQuery(document).ready(function ($) {
    const providerSelect = $('#dpuwoo_api_provider');
    const currencySelect = $('#dpuwoo_reference_currency');
    const loadingSpinner = $('#dpuwoo_currency_loading');
    const baseCountry = $('#dpuwoo_base_country').val();
    const baseCurrency = dpuwoo_ajax?.base_currency || '';

    // Función para decodificar entidades HTML
    function decodeHtmlEntities(text) {
        if (!text) return text;
        
        // Usar el DOM para decodificar
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

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
    });

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
                    console.log('Monedas recibidas:', response);
                    
                    // Preparar datos para Select2
                    let currencyOptions = [];
                    
                    // Si hay monedas en la respuesta
                    if (response.data.currencies && Object.keys(response.data.currencies).length > 0) {
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
                                optionText = (currency.name || decodedCode) + ' - ' + decodedCode;
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
                    
                    // Actualizar el select con las nuevas opciones
                    currencySelect.empty().select2({
                        data: currencyOptions,
                        placeholder: 'Seleccione una moneda o escriba para buscar',
                        allowClear: false,
                        disabled: false,
                        templateResult: formatCurrencyOption,
                        templateSelection: formatCurrencySelection,
                        escapeMarkup: function(markup) {
                            return markup;
                        }
                    });
                    
                    // Seleccionar la opción por defecto (vacía)
                    currencySelect.val('').trigger('change');
                    
                    // Actualizar descripción
                    $('#dpuwoo_currency_description').html(
                        `<strong>${providerName}</strong> - ${currencyOptions.length - 1} monedas disponibles. ` +
                        (baseCurrency ? 
                            `La moneda base (${baseCurrency}) está excluida.` : 
                            '')
                    );
                    
                } else {
                    // Error en la respuesta
                    showCurrencyError(providerName);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error cargando monedas:', xhr.responseText);
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
        
        // Seleccionar USD por defecto en caso de error
        currencySelect.val('USD').trigger('change');
        
        const errorMsg = providerName ? 
            `Error al cargar monedas de ${providerName}. Se usará USD por defecto.` :
            'Error al cargar las monedas disponibles. Se usará USD por defecto.';
        
        $('#dpuwoo_currency_description').text(errorMsg);
    }

    // Cargar monedas al cambiar el proveedor
    providerSelect.on('change', function () {
        const selectedProvider = $(this).val();
        
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
        if (currencySelect.val() && currencySelect.val() !== '') {
            localStorage.setItem('dpuwoo_last_currency', currencySelect.val());
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