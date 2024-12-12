(function($) {
    'use strict';

    class APFMobilePanel {
        constructor() {
            this.panel = $('.apf-filter-panel');
            this.overlay = $('.apf-filter-overlay');
            this.toggle = $('.apf-filter-toggle');
            this.closeBtn = $('.apf-panel-close');
            this.widgetArea = $('.widget-area');
            this.isMobile = window.innerWidth <= 991;
            this.filtersMovedToPanel = false;
            
            this.init();
        }

        init() {
            if (this.isMobile) {
                this.setupMobileView();
            }
            this.addResetButton();
            this.bindEvents();
            this.removeIndividualResetButtons();
            this.updateSpinnerImplementation();
        }

        // New method to handle spinner implementation
        updateSpinnerImplementation() {
            // Find all WooCommerce buttons that might need spinners
            $('.woocommerce button.button').each(function() {
                const $button = $(this);
                
                // Create spinner element
                const $spinner = $('<span>', {
                    class: 'spinner-border spinner-border-sm me-2',
                    role: 'status',
                    'aria-hidden': 'true'
                }).hide();
                
                // Add spinner to button
                $button.prepend($spinner);
                
                // Update loading state handler
                $button.on('loading.wc', function() {
                    $spinner.show();
                    $button.prop('disabled', true);
                });
                
                // Update complete state handler
                $button.on('complete.wc', function() {
                    $spinner.hide();
                    $button.prop('disabled', false);
                });
            });
        }

        removeIndividualResetButtons() {
            $('.widget_apf_filter .reset-button, .widget_apf_filter .reset-filters').remove();
        }

        addResetButton() {
            const resetButton = $('<button>', {
                class: 'apf-reset-filters',
                type: 'button'
            }).append(
                $('<span>', { text: 'Reset All Filters' })
            );
            
            this.panel.find('.apf-panel-header').after(resetButton);
        }

        setupMobileView() {
            if (!this.filtersMovedToPanel) {
                this.moveFiltersToPanel();
                this.filtersMovedToPanel = true;
            }
        }

        moveFiltersToPanel() {
            $('.widget-area .widget_apf_filter').each((index, widget) => {
                const clone = $(widget).clone(true);
                clone.find('.reset-button, .reset-filters').remove();
                this.panel.append(clone);
            });
        }

        resetFilters() {
            this.panel.find('.apf-filter-checkbox:checked').prop('checked', false);
            $('.widget-area .apf-filter-checkbox:checked').prop('checked', false);
            
            const forms = this.panel.find('form');
            forms.each((_, form) => {
                const $form = $(form);
                if (typeof apf_update_products === 'function') {
                    apf_update_products($form);
                }
            });
            
            this.closePanel();
        }

        bindEvents() {
            this.toggle.on('click', () => this.openPanel());
            this.closeBtn.on('click', () => this.closePanel());
            this.overlay.on('click', () => this.closePanel());

            this.panel.on('click', '.apf-reset-filters', (e) => {
                e.preventDefault();
                this.resetFilters();
            });

            this.panel.on('change', '.apf-filter-checkbox', (e) => {
                const checkbox = $(e.target);
                const form = checkbox.closest('form');
                
                const checkboxId = checkbox.attr('id');
                if (checkboxId) {
                    $('.widget-area').find(`#${checkboxId}`).prop('checked', checkbox.prop('checked'));
                }
                
                setTimeout(() => {
                    this.closePanel();
                    
                    if (typeof apf_update_products === 'function') {
                        apf_update_products(form);
                    }
                }, 150);
            });

            $(window).on('popstate', () => this.closePanel());

            let resizeTimer;
            $(window).on('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth <= 991;

                    if (wasMobile !== this.isMobile) {
                        if (this.isMobile) {
                            this.setupMobileView();
                        } else {
                            this.closePanel();
                        }
                    }
                }, 250);
            });

            $(document).on('apf_filter_start', () => {
                $('body').addClass('apf-loading');
            });

            $(document).on('apf_filter_complete', () => {
                $('body').removeClass('apf-loading');
                this.removeIndividualResetButtons();
            });
        }

        openPanel() {
            if (!this.isMobile) return;
            
            this.panel.addClass('active');
            this.overlay.addClass('active');
            $('body').addClass('apf-panel-open');
        }

        closePanel() {
            this.panel.removeClass('active');
            this.overlay.removeClass('active');
            $('body').removeClass('apf-panel-open');
        }
    }

    $(document).ready(() => {
        new APFMobilePanel();
    });

})(jQuery);