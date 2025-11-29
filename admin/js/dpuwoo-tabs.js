(function ($) {
    'use strict';

    const DPUWOO_Tabs = {
        init: function() {
            this.setupTabs();
            this.restoreActiveTab();
        },

        setupTabs: function() {
            $('.dpuwoo-tab').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                DPUWOO_Tabs.switchTab(tab);
            });
        },

        switchTab: function(tab) {
            $('.dpuwoo-tab')
                .removeClass('border-blue-600 text-blue-600 font-semibold')
                .addClass('border-transparent text-gray-500 font-medium');
            
            $('.dpuwoo-tab[data-tab="' + tab + '"]')
                .removeClass('border-transparent text-gray-500 font-medium')
                .addClass('border-blue-600 text-blue-600 font-semibold');

            $('.dpuwoo-tab-content').addClass('hidden');
            $('#dpuwoo-tab-' + tab).removeClass('hidden');
            localStorage.setItem('dpuwoo_active_tab', tab);
        },

        restoreActiveTab: function() {
            const savedTab = localStorage.getItem('dpuwoo_active_tab');
            if (savedTab && $('.dpuwoo-tab[data-tab="' + savedTab + '"]').length) {
                DPUWOO_Tabs.switchTab(savedTab);
            } else {
                // Por defecto, activar la primera tab
                const firstTab = $('.dpuwoo-tab').first().data('tab');
                if (firstTab) {
                    DPUWOO_Tabs.switchTab(firstTab);
                }
            }
        },

        getActiveTab: function() {
            return localStorage.getItem('dpuwoo_active_tab') || '';
        },

        resetTabs: function() {
            localStorage.removeItem('dpuwoo_active_tab');
            const firstTab = $('.dpuwoo-tab').first().data('tab');
            if (firstTab) {
                DPUWOO_Tabs.switchTab(firstTab);
            }
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        DPUWOO_Tabs.init();
    });

    // Exponer al global scope para que otros módulos puedan usarlo
    window.DPUWOO_Tabs = DPUWOO_Tabs;

})(jQuery);