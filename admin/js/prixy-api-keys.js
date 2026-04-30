jQuery(document).ready(function($) {
    'use strict';
    
    // Load currencies on page load
    var currentProvider = $('#dpuwoo-api-provider-input').val() || 'dolarapi';
    loadCurrenciesForProvider(currentProvider);
    
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

// Providers that don't require API key
var noKeyProviders = ['dolarapi', 'jsdelivr', 'cryptoprice'];

// Global functions for provider selection
function selectProvider(provider) {
    // Update hidden input
    document.getElementById('dpuwoo-api-provider-input').value = provider;
    
    // Update card styles
    document.querySelectorAll('.dpuwoo-provider-card').forEach(function(card) {
        card.classList.remove('dpuwoo-provider-card--selected');
        card.querySelector('.dpuwoo-provider-card__radio-inner').classList.remove('dpuwoo-provider-card__radio-inner--checked');
        if (card.dataset.provider === provider) {
            card.classList.add('dpuwoo-provider-card--selected');
            card.querySelector('.dpuwoo-provider-card__radio-inner').classList.add('dpuwoo-provider-card__radio-inner--checked');
        }
    });
    
    // Hide all API key panels first
    document.querySelectorAll('.dpuwoo-api-key-panel').forEach(function(panel) {
        panel.style.display = 'none';
    });
    
    // Show panel only for providers that require API key
    if (!noKeyProviders.includes(provider)) {
        var panel = document.getElementById('panel-' + provider);
        if (panel) {
            panel.style.display = 'block';
        }
    }
    
    // Reload currencies for reference currency select
    loadCurrenciesForProvider(provider);
}

function getCurrencySymbol(code) {
    var symbols = {
        // Crypto
        'BTC': '₿', 'ETH': 'Ξ', 'BNB': '◎', 'XRP': '✕', 'USDT': '₮',
        'SOL': '◎', 'ADA': '₳', 'DOGE': 'Ð', 'DOT': '●', 'MATIC': '⬡',
        'LTC': 'Ł', 'AVAX': '▲', 'LINK': '⬡', 'UNI': '🦄', 'ATOM': '⚛',
        'XLM': '✦', 'ALGO': 'Algo', 'FIL': '⨎', 'TRX': '✳', 'ETC': 'Ξ',
        'XMR': 'ɱ', 'NEAR': 'Near', 'AR': 'Ar', 'LDO': 'LDO', 'QNT': 'QNT',
        'GRT': 'GRT', 'RNDR': 'RNDR', 'OP': 'OP', 'ARB': 'ARB', 'INJ': 'INJ',
        'SUI': 'SUI', 'SEI': 'SEI', 'TIA': 'TIA', 'PEPE': 'Pepe', 'SHIB': 'Shib',
        'WIF': 'WIF', 'BONK': 'Bonk', 'FET': 'FET', 'GALA': 'GALA', 'IMX': 'IMX',
        'APT': 'APT', 'STX': 'STX', 'RUNE': 'RUNE', 'KAVA': 'KAVA', 'FTM': 'FTM',
        'CAKE': 'CAKE', 'MINA': 'MINA', 'ROSE': 'ROSE', 'ZIL': 'ZIL', 'ONE': 'ONE',
        'CELO': 'CELO', 'QTUM': 'QTUM', 'NEO': 'NEO', 'EOS': 'EOS', 'XTZ': 'XTZ',
        'FLOW': 'FLOW', 'HBAR': 'ħ', 'THETA': 'Θ', 'SHIB': 'Shib',
        
        // Fiat
        'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'ARS': '$',
        'BRL': 'R$', 'CLP': '$', 'MXN': '$', 'COP': '$', 'UYU': '$U',
        'PEN': 'S/', 'VES': 'Bs', 'BOB': 'Bs.', 'CRC': '₡', 'GTQ': 'Q',
        'HNL': 'L', 'NIO': 'C$', 'PAB': 'B/.', 'DOP': 'RD$', 'CUP': '₱',
        'CAD': 'C$', 'AUD': 'A$', 'NZD': 'NZ$', 'CHF': 'Fr', 'CNY': '¥',
        'INR': '₹', 'RUB': '₽', 'KRW': '₩', 'SGD': 'S$', 'HKD': 'HK$',
        'SEK': 'kr', 'NOK': 'kr', 'DKK': 'kr', 'PLN': 'zł', 'TRY': '₺',
        'ZAR': 'R', 'AED': 'د.إ', 'SAR': '﷼', 'ILS': '₪', 'THB': '฿',
        'MYR': 'RM', 'IDR': 'Rp', 'PHP': '₱', 'VND': '₫', 'TWD': 'NT$',
        'KWD': 'د.ك', 'BHD': '.د.ب', 'OMR': 'ر.ع.', 'QAR': 'ر.ق'
    };
    return symbols[code.toUpperCase()] || code;
}

function initSelect2() {
    var currencySelect = document.getElementById('dpuwoo-ref-currency');
    if (currencySelect && typeof jQuery.fn.select2 !== 'undefined') {
        jQuery(currencySelect).select2({
            theme: 'default',
            width: '100%',
            placeholder: 'Buscar moneda...',
            allowClear: true,
            language: {
                noResults: function() { return 'No se encontraron resultados'; },
                searching: function() { return 'Buscando...'; }
            }
        });
    }
}

function loadCurrenciesForProvider(provider) {
    var currencySelect = document.getElementById('dpuwoo-ref-currency');
    if (!currencySelect) return;
    
    currencySelect.innerHTML = '<option value="">Cargando...</option>';
    
    // Destroy select2 if exists before repopulating
    if (typeof jQuery.fn.select2 !== 'undefined') {
        try { jQuery(currencySelect).select2('destroy'); } catch(e) {}
    }
    
    jQuery.post(dpuwoo_ajax.ajax_url, {
        action: 'dpuwoo_get_currencies',
        provider: provider,
        nonce: dpuwoo_ajax.nonce
    }, function(response) {
        if (response.success && response.data && response.data.currencies) {
            var currencies = response.data.currencies;
            currencySelect.innerHTML = '';
            
            // Add all currencies in a flat list (no grouping)
            currencies.forEach(function(c) {
                var code = c.code || c.key || '';
                var name = c.name || code;
                var symbol = c.symbol || '';
                
                // Format: "Dólar Oficial ($)" or "Bitcoin (₿)"
                var displayText = symbol ? name + ' (' + symbol + ')' : name;
                
                var option = document.createElement('option');
                option.value = code;
                option.textContent = displayText;
                
                if (code === currencySelect.dataset.selected) {
                    option.selected = true;
                }
                
                currencySelect.appendChild(option);
            });
        } else {
            currencySelect.innerHTML = '<option value="">No disponible</option>';
        }
    }).fail(function() {
        currencySelect.innerHTML = '<option value="">Error</option>';
    }).always(function() {
        initSelect2();
    });
}

function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

function testApiConnection(api) {
    var resultDiv = document.getElementById('dpuwoo-api-test-result');
    var apiKeyInput;
    
    if (api === 'currencyapi') {
        apiKeyInput = document.getElementById('input-currencyapi');
    } else if (api === 'exchangerate') {
        apiKeyInput = document.getElementById('input-exchangerate');
    }
    
    var apiKey = apiKeyInput ? apiKeyInput.value : '';
    
    if (!apiKey) {
        resultDiv.className = 'dpuwoo-api-test-result show dpuwoo-api-test-result--error';
        resultDiv.innerHTML = '<strong>Error:</strong> Por favor ingresá tu API Key primero.';
        return;
    }
    
    resultDiv.className = 'dpuwoo-api-test-result show';
    resultDiv.innerHTML = '<span style="color: #6b7280;">Probando conexión...</span>';
    
    // Map API names to provider names
    var providerMap = {
        'currencyapi': 'currencyapi',
        'exchangerate': 'exchangerate'
    };
    var provider = providerMap[api] || api;
    
    // Make AJAX request
    var data = {
        action: 'dpuwoo_test_api',
        api: provider,
        api_key: apiKey,
        nonce: dpuwoo_ajax.nonce
    };
    
    jQuery.post(dpuwoo_ajax.ajax_url, data, function(response) {
        if (response.success) {
            resultDiv.className = 'dpuwoo-api-test-result show dpuwoo-api-test-result--success';
            resultDiv.innerHTML = '<strong>✓ Conexión exitosa!</strong> ' + (response.data.message || 'API Key válida.');
        } else {
            resultDiv.className = 'dpuwoo-api-test-result show dpuwoo-api-test-result--error';
            resultDiv.innerHTML = '<strong>✗ Error de conexión:</strong> ' + (response.data.message || 'Verificá tu API Key.');
        }
    }).fail(function() {
        resultDiv.className = 'dpuwoo-api-test-result show dpuwoo-api-test-result--error';
        resultDiv.innerHTML = '<strong>✗ Error:</strong> No se pudo conectar al servidor.';
    });
}

function toggleApiKey(prefix) {
    var input = document.querySelector('input[name="dpuwoo_settings[' + prefix + '_api_key]"]');
    if (input) {
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
}