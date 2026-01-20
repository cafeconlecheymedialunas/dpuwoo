jQuery(document).ready(function($) {
    const providerSelect = $('#dpuwoo_api_provider');
    const currencySelect = $('#dpuwoo_reference_currency');
    const loadingSpinner = $('#dpuwoo_currency_loading');
    const baseCountry = $('#dpuwoo_base_country').val();
    const baseCurrency = dpuwoo_ajax?.base_currency || '';
    
    // Función para cargar monedas
    function loadCurrenciesForProvider(provider) {
        // Mostrar indicador de carga
        loadingSpinner.addClass('is-active');
        currencySelect.prop('disabled', true);
        
        // Guardar valor actual antes de actualizar
        const previousVal = currencySelect.val();
        
        $.ajax({
            url: dpuwoo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dpuwoo_get_currencies',
                provider: provider,
                country: baseCountry,
                nonce: dpuwoo_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    console.log('Monedas recibidas:', response);
                    // Limpiar select actual
                    currencySelect.empty();
                    
                    // Si no hay monedas, mostrar mensaje
                    if (Object.keys(response.data).length === 0) {
                        currencySelect.append(
                            $('<option>', {
                                value: '',
                                text: 'No hay monedas disponibles',
                                disabled: true,
                                selected: true
                            })
                        );
                        $('#dpuwoo_currency_description').text(
                            'El proveedor seleccionado no proporciona monedas disponibles.'
                        );
                        return;
                    }
                    
                    // Agregar cada moneda al select
                    $.each(response.data, function(code, name) {
                        // No incluir la moneda base (evitar conversión a sí misma)
                        if (code === baseCurrency) {
                            return;
                        }
                        
                        const optionText = code + ' - ' + name;
                        const option = $('<option>', {
                            value: code,
                            text: optionText
                        });
                        
                        // Restaurar selección anterior si coincide
                        if (code === previousVal) {
                            option.prop('selected', true);
                        }
                        
                        currencySelect.append(option);
                    });
                    
                    // Si no se seleccionó automáticamente, seleccionar la primera
                    if (currencySelect.val() === '' && currencySelect.find('option').length > 0) {
                        currencySelect.find('option:first').prop('selected', true);
                    }
                    
                    // Habilitar el select
                    currencySelect.prop('disabled', false);
                    
                    // Actualizar descripción
                    const providerName = providerSelect.find('option:selected').text();
                    $('#dpuwoo_currency_description').text(
                        'Monedas disponibles según ' + providerName + '. ' +
                        (baseCurrency ? 
                            'La moneda base (' + baseCurrency + ') está excluida.' : 
                            '')
                    );
                } else {
                    // Error en la respuesta
                    showCurrencyError();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error cargando monedas:', error);
                showCurrencyError();
            },
            complete: function() {
                // Ocultar indicador de carga
                loadingSpinner.removeClass('is-active');
            }
        });
    }
    
    // Función para mostrar error
    function showCurrencyError() {
        currencySelect.empty().append(
            $('<option>', {
                value: 'USD',
                text: 'USD - Error al cargar monedas',
                selected: true
            })
        );
        currencySelect.prop('disabled', false);
        $('#dpuwoo_currency_description').text(
            'Error al cargar las monedas disponibles. Se usará USD por defecto.'
        );
    }
    
    // Cargar monedas al cambiar el proveedor
    providerSelect.on('change', function() {
        const selectedProvider = $(this).val();
        if (selectedProvider) {
            loadCurrenciesForProvider(selectedProvider);
        }
    });
    
    // Cargar monedas automáticamente al cargar la página si hay un proveedor seleccionado
    if (providerSelect.val()) {
        // Pequeño delay para asegurar que todo esté cargado
        setTimeout(function() {
            loadCurrenciesForProvider(providerSelect.val());
        }, 300);
    } else {
        // Si no hay proveedor seleccionado, habilitar el select con un valor por defecto
        currencySelect.prop('disabled', false);
    }
    
    // Manejar el evento de guardado de formulario para mantener la selección
    $('#dpuwoo_settings_form').on('submit', function(e) {
        // Guardar la selección actual en localStorage para restaurarla después del refresh
        if (currencySelect.val()) {
            localStorage.setItem('dpuwoo_last_currency', currencySelect.val());
        }
    });
    
    // Intentar restaurar la última selección de moneda al cargar la página
    const lastCurrency = localStorage.getItem('dpuwoo_last_currency');
    if (lastCurrency && currencySelect.find('option[value="' + lastCurrency + '"]').length > 0) {
        setTimeout(function() {
            currencySelect.val(lastCurrency);
        }, 500);
    }
});