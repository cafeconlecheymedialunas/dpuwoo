(function ($) {
    'use strict';

    const DPUWOO_Tabs = {
        init: function() {
            this.setupTabs();
            this.restoreActiveTab();
        },

        setupTabs: function() {
            $('.prixy-tab').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                DPUWOO_Tabs.switchTab(tab);
            });
        },

        switchTab: function(tab) {
            $('.prixy-tab').removeClass('dpu-tab--active');
            $('.prixy-tab[data-tab="' + tab + '"]').addClass('dpu-tab--active');

            $('.prixy-tab-content').addClass('hidden');
            $('#prixy-tab-' + tab).removeClass('hidden');
            localStorage.setItem('prixy_active_tab', tab);
        },

        restoreActiveTab: function() {
            const savedTab = localStorage.getItem('prixy_active_tab');
            if (savedTab && $('.prixy-tab[data-tab="' + savedTab + '"]').length) {
                DPUWOO_Tabs.switchTab(savedTab);
            } else {
                // Por defecto, activar la primera tab
                const firstTab = $('.prixy-tab').first().data('tab');
                if (firstTab) {
                    DPUWOO_Tabs.switchTab(firstTab);
                }
            }
        },

        getActiveTab: function() {
            return localStorage.getItem('prixy_active_tab') || '';
        },

        resetTabs: function() {
            localStorage.removeItem('prixy_active_tab');
            const firstTab = $('.prixy-tab').first().data('tab');
            if (firstTab) {
                DPUWOO_Tabs.switchTab(firstTab);
            }
        }
    };

    // ── Acordeón ─────────────────────────────────────────────
    $(document).ready(function() {
        DPUWOO_Tabs.init();

        // Accordion toggle
        $(document).on('click', '.dpu-accordion__trigger', function() {
            var $trigger = $(this);
            var $body    = $('#' + $trigger.attr('aria-controls'));
            var isOpen   = $trigger.attr('aria-expanded') === 'true';

            if (isOpen) {
                $trigger.attr('aria-expanded', 'false');
                $body.attr('hidden', true);
            } else {
                $trigger.attr('aria-expanded', 'true');
                $body.removeAttr('hidden');
            }
        });
    });

    // Exponer al global scope para que otros módulos puedan usarlo
    window.DPUWOO_Tabs = DPUWOO_Tabs;

})(jQuery);