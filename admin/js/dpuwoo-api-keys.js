jQuery(document).ready(function($) {
    'use strict';
    
    // Elementos del DOM
    const $apiProviderSelect = $('#dpuwoo_api_provider');
    const $currencyApiContainer = $('#dpuwoo_currencyapi_key_container');
    const $exchangeRateContainer = $('#dpuwoo_exchangerate_key_container');
    const $settingsForm = $('#dpuwoo-settings-form');
    const $saveButton = $('#dpuwoo-save-settings');
    const $saveStatus = $('#dpuwoo-save-status');
    
    // Función para mostrar/ocultar filas de API key según el proveedor seleccionado
    function toggleApiKeyRows() {
        const selectedProvider = $apiProviderSelect.val();
        
        // Ocultar todas las filas de API keys y sus campos internos
        $('.dpuwoo-api-key-field').each(function() {
            const $fieldContainer = $(this);
            const $fieldRow = $fieldContainer.closest('tr');
            
            // Ocultar fila
            $fieldRow.addClass('dpuwoo-api-row-hidden').removeClass('dpuwoo-api-row-visible');
            // Ocultar campo interno
            $fieldContainer.removeClass('dpuwoo-api-key-visible').addClass('dpuwoo-api-key-hidden');
        });
        
        // Mostrar solo la fila y campo correspondientes al proveedor seleccionado
        switch(selectedProvider) {
            case 'currencyapi':
                const $currencyRow = $currencyApiContainer.closest('tr');
                $currencyRow.removeClass('dpuwoo-api-row-hidden').addClass('dpuwoo-api-row-visible');
                $currencyApiContainer.removeClass('dpuwoo-api-key-hidden').addClass('dpuwoo-api-key-visible');
                break;
                
            case 'exchangerate-api':
                const $exchangeRow = $exchangeRateContainer.closest('tr');
                $exchangeRow.removeClass('dpuwoo-api-row-hidden').addClass('dpuwoo-api-row-visible');
                $exchangeRateContainer.removeClass('dpuwoo-api-key-hidden').addClass('dpuwoo-api-key-visible');
                break;
                
            case 'dolarapi':
                // DolarAPI no requiere API key, así que mantenemos todas las filas y campos ocultos
                break;
                
            default:
                // Para cualquier otro proveedor, mantener todas las filas y campos ocultos
                break;
        }
    }
    
    // Manejar la lógica de dependencia entre métodos de generación y el campo de tasa
    function handleGenerationMethodDependency() {
        const generationMethodRadios = $('input[name="dpuwoo_settings[rate_generation_method]"]');
        const originRateField = $('#dpuwoo_origin_exchange_rate');
        const originRateContainer = $('#dpuwoo_origin_rate_container');
        
        function updateOriginRateState() {
            const selectedMethod = $('input[name="dpuwoo_settings[rate_generation_method]"]:checked').val();
            
            if (selectedMethod === 'api') {
                // API seleccionado: deshabilitar campo de tasa
                originRateField.prop('disabled', true).css('background-color', '#f0f0f0');
                originRateContainer.find('.description').text('Campo deshabilitado: la tasa se obtendrá automáticamente por API.');
            } else if (selectedMethod === 'manual') {
                // Manual seleccionado: habilitar campo de tasa
                originRateField.prop('disabled', false).css('background-color', '');
                originRateContainer.find('.description').text('Tasa de cambio utilizada como punto de partida para calcular variaciones de precios.');
            } else {
                // Por defecto (ninguno seleccionado): habilitar campo
                originRateField.prop('disabled', false).css('background-color', '');
                originRateContainer.find('.description').text('Tasa de cambio utilizada como punto de partida para calcular variaciones de precios.');
            }
        }
        
        // Escuchar cambios en los radio buttons
        generationMethodRadios.on('change', updateOriginRateState);
        
        // Ejecutar al cargar la página
        updateOriginRateState();
    }
    
    // Inicializar la lógica de dependencia
    handleGenerationMethodDependency();
    function collectFormData() {
        const formData = {};
        
        // Try multiple approaches to find the form
        let $form = $('#dpuwoo-settings-form');
        
        // Fallback: find form containing dpuwoo_settings fields
        if (!$form.length) {
            
            $form = $('form').filter(function() {
                return $(this).find('input[name^="dpuwoo_settings"]').length > 0;
            }).first();
        }
        
        // Another fallback
        if (!$form.length) {
            $form = $('form[id$="settings-form"]');
        }
        
        if (!$form.length) {       
            return formData;
        }
   
        
        // Count dpuwoo_settings fields specifically
        const dpuwooFields = $form.find('input[name^="dpuwoo_settings"], select[name^="dpuwoo_settings"], textarea[name^="dpuwoo_settings"]');
        
        
        // Collect all form elements with detailed logging
        dpuwooFields.each(function(index) {
            const $element = $(this);
            const name = $element.attr('name');
            const type = $element.attr('type') || $element.prop('tagName').toLowerCase();
            let value;
            
            // Special handling for disabled reference currency select
            if ($element.attr('id') === 'dpuwoo_reference_currency') {
                // Get value directly from Select2 regardless of disabled state
                value = $('#dpuwoo_reference_currency').val();
            } else {
                value = type === 'checkbox' ? ($element.is(':checked') ? 1 : 0) : $element.val();
            }
            
            
            
            // Extract field name
            if (name && name.startsWith('dpuwoo_settings[')) {
                const fieldName = name.replace('dpuwoo_settings[', '').replace(']', '');
                formData[fieldName] = value;
                
            }
        });
        
        
        return formData;
    }
    
    // Función para guardar configuración por AJAX
    function saveSettings(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Extra prevention of default submission
        if (e.originalEvent) {
            e.originalEvent.preventDefault();
            e.originalEvent.stopPropagation();
        }
        
        // Show loading state
        const $button = $('#dpuwoo-save-settings');
        const $btnText = $button.find('.btn-text');
        const $btnLoading = $button.find('.btn-loading');
        
        // Disable button and show loading spinner
        $button.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        
        $saveStatus.html('<span class="text-blue-600">Procesando...</span>');
        
        // Recolectar datos del formulario
        const settingsData = collectFormData();
        
        
        // DEBUG: Send all form data to see what's available
        const allFormData = {};
        $settingsForm.find('input, select, textarea').each(function() {
            const $element = $(this);
            const name = $element.attr('name');
            const value = $element.val();
            if (name) {
                allFormData[name] = value;
                
            }
        });
        
        
        $.ajax({
            url: dpuwoo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dpuwoo_save_settings',
                nonce: dpuwoo_ajax.nonce,
                // Send as nested object instead of flattened
                settings: settingsData
            },
            success: function(response) {
                
                if (response.success) {
                    $saveStatus.html('<span class="text-green-600">✓ Configuración guardada correctamente</span>');
                    
                    // Show success state
                    $btnLoading.hide();
                    $btnText.text('Guardado ✓').show();
                    $button.removeClass('bg-blue-600 hover:bg-blue-700').addClass('bg-green-600');
                    
                    // Resetear botón después de 2 segundos
                    setTimeout(function() {
                        $button.prop('disabled', false)
                              .removeClass('bg-green-600')
                              .addClass('bg-blue-600 hover:bg-blue-700');
                        $btnText.text('Guardar cambios');
                        $saveStatus.empty();
                    }, 2000);
                } else {
                    $saveStatus.html('<span class="text-red-600">✗ Error: ' + (response.data.message || 'Error desconocido') + '</span>');
                    
                    // Reset button on error
                    $btnLoading.hide();
                    $btnText.text('Reintentar').show();
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                $saveStatus.html('<span class="text-red-600">✗ Error de conexión: ' + error + '</span>');
                
                // Reset nobutton on error
                $btnLoading.hide();
                $btnText.text('Reintentar').show();
                $button.prop('disabled', false);
            },
            complete: function() {
                // Ensure loading state is cleared in all cases
                if (!$button.hasClass('bg-green-600')) {
                    $btnLoading.hide();
                    $btnText.show();
                }
            }
        });
    }
    
    // Event listeners
    $apiProviderSelect.on('change', function() {
        toggleApiKeyRows();
    });
    
    // Handle form submission with AJAX
    $settingsForm.on('submit', function(e) {
        saveSettings(e);
    });
    
    // Also handle direct button click
    $('#dpuwoo-save-settings').on('click', function(e) {
        if (!$settingsForm.length) {
            // If no form found, use direct click handler
            saveSettings(e);
        }
        // If form exists, let the form submit handler take care of it
    });
    
    // Test button that bypasses form submission
    $('#dpuwoo-test-ajax').on('click', function(e) {
        
        saveSettings(e);
    });
    
    // Direct save workaround - creates hidden form and submits traditionally
    $('#dpuwoo-direct-save').on('click', function(e) {
        
        
        // Prevent default
        e.preventDefault();
        
        // Collect form data
        const settingsData = collectFormData();
        
        
        if (Object.keys(settingsData).length === 0) {
            alert('No data collected!');
            return;
        }
        
        // Create hidden form
        const $hiddenForm = $('<form>', {
            'method': 'POST',
            'action': window.location.href,
            'style': 'display:none;'
        });
        
        // Add WordPress settings fields
        $hiddenForm.append($('<input>', {
            'type': 'hidden',
            'name': 'option_page',
            'value': 'dpuwoo_settings_group'
        }));
        
        $hiddenForm.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'update'
        }));
        
        $hiddenForm.append($('<input>', {
            'type': 'hidden',
            'name': '_wpnonce',
            'value': $('input[name="_wpnonce"]').val() || ''
        }));
        
        // Add all settings data as individual fields
        Object.keys(settingsData).forEach(function(key) {
            $hiddenForm.append($('<input>', {
                'type': 'hidden',
                'name': 'dpuwoo_settings[' + key + ']',
                'value': settingsData[key]
            }));
        });
        
        // Add submit button
        $hiddenForm.append($('<input>', {
            'type': 'submit',
            'value': 'Submit'
        }));
        
        // Append to body and submit
        $('body').append($hiddenForm);
        $hiddenForm.submit();
    });
    
    // Inicializar al cargar la página
    toggleApiKeyRows();
    
    // También manejar posibles cambios dinámicos
    $(document).on('dpuwoo:providerChanged', function(event, newProvider) {
        if (newProvider) {
            $apiProviderSelect.val(newProvider).trigger('change');
        }
    });
    
    
});
