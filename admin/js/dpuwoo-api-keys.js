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
    
    // Remover la lógica que deshabilita el campo de tasa
    // El campo debe permanecer habilitado para que se envíe con el formulario
    /*
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
    */
    
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
    
    // Traditional form submission will be used instead of AJAX
    // All settings will be saved through WordPress's built-in settings API
    
    // Event listeners
    $apiProviderSelect.on('change', function() {
        toggleApiKeyRows();
    });
    
    // Remove AJAX submission - use traditional form submission instead
    // Form will submit normally and page will reload
    
    // Handle button click to trigger form submission
    $('#dpuwoo-save-settings').on('click', function(e) {
        DPUWOO_Utils.btnLoading(this, 'Guardando…');
        // Form submits naturally — page will reload, no need to reset
    });
    
    // Test button removed - using traditional form submission
    // Using standard WordPress form submission - no workarounds needed
    
    // Inicializar al cargar la página
    toggleApiKeyRows();
    
    // También manejar posibles cambios dinámicos
    $(document).on('dpuwoo:providerChanged', function(event, newProvider) {
        if (newProvider) {
            $apiProviderSelect.val(newProvider).trigger('change');
        }
    });
});