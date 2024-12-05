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
            this.bindEvents();
        }

        setupMobileView() {
            if (!this.filtersMovedToPanel) {
                this.moveFiltersToPanel();
                this.filtersMovedToPanel = true;
            }
        }

        moveFiltersToPanel() {
            // Clone widgets to panel
            $('.widget-area .widget_apf_filter').each((index, widget) => {
                const clone = $(widget).clone(true);
                this.panel.append(clone);
            });
        }

        bindEvents() {
            // Open panel
            this.toggle.on('click', () => this.openPanel());

            // Close panel
            this.closeBtn.on('click', () => this.closePanel());
            this.overlay.on('click', () => this.closePanel());

            // Handle filter checkbox changes
            this.panel.on('change', '.apf-filter-checkbox', (e) => {
                const checkbox = $(e.target);
                const form = checkbox.closest('form');
                
                // Small delay to show checkbox state before panel closes
                setTimeout(() => {
                    this.closePanel();
                    
                    // Trigger the filter update
                    if (typeof apf_update_products === 'function') {
                        apf_update_products(form);
                    }
                }, 150);
            });

            // Handle back button
            $(window).on('popstate', () => this.closePanel());

            // Handle resize events
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

            // Add loading indicator
            $(document).on('apf_filter_start', () => {
                $('body').addClass('apf-loading');
            });

            $(document).on('apf_filter_complete', () => {
                $('body').removeClass('apf-loading');
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

    // Initialize on document ready
    $(document).ready(() => {
        new APFMobilePanel();
    });

})(jQuery);