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
            $('.dpuwoo-tab').removeClass('dpu-tab--active');
            $('.dpuwoo-tab[data-tab="' + tab + '"]').addClass('dpu-tab--active');

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