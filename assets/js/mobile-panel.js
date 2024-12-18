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
            this.initPriceSlider();
        }

        updateSpinnerImplementation() {
            $('.woocommerce button.button').each(function() {
                const $button = $(this);
                
                const $spinner = $('<span>', {
                    class: 'spinner-border spinner-border-sm me-2',
                    role: 'status',
                    'aria-hidden': 'true'
                }).hide();
                
                $button.prepend($spinner);
                
                $button.on('loading.wc', function() {
                    $spinner.show();
                    $button.prop('disabled', true);
                });
                
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
            $('.widget-area .widget_apf_filter, .widget-area .widget_apf_price_filter').each((index, widget) => {
                const clone = $(widget).clone(true);
                clone.find('.reset-button, .reset-filters').remove();
                this.panel.append(clone);
            });
        }

        initPriceSlider() {
            if (!$('#price-slider').length) return;

            const $slider = $('#price-slider');
            const $form = $slider.closest('form');
            const $minInput = $form.find('.min-price-input');
            const $maxInput = $form.find('.max-price-input');
            const $minDisplay = $form.find('.price-from');
            const $maxDisplay = $form.find('.price-to');

            $slider.slider({
                range: true,
                min: parseFloat($slider.data('min')),
                max: parseFloat($slider.data('max')),
                step: parseFloat($slider.data('step')),
                values: [
                    parseFloat($minInput.val()),
                    parseFloat($maxInput.val())
                ],
                slide: (event, ui) => {
                    const [min, max] = ui.values;
                    this.updatePriceDisplay(min, max, $minDisplay, $maxDisplay);
                    $minInput.val(min);
                    $maxInput.val(max);
                },
                stop: (event, ui) => {
                    if (typeof apf_update_products === 'function') {
                        apf_update_products($form);
                    }
                }
            });
        }

        updatePriceDisplay(min, max, $minDisplay, $maxDisplay) {
            const formatPrice = (price) => {
                return this.woocommerce_price_format(price, priceSliderData.currency_symbol);
            };

            $minDisplay.html(formatPrice(min));
            $maxDisplay.html(formatPrice(max));
        }

        woocommerce_price_format(price, symbol) {
            const formatted = price.toFixed(2);
            const format = priceSliderData.price_format;
            return format.replace('%1$s', symbol).replace('%2$s', formatted);
        }

        resetFilters() {
            this.panel.find('.apf-filter-checkbox:checked').prop('checked', false);
            $('.widget-area .apf-filter-checkbox:checked').prop('checked', false);
            
            // Reset price slider if it exists
            const $slider = $('#price-slider');
            if ($slider.length) {
                const min = parseFloat($slider.data('min'));
                const max = parseFloat($slider.data('max'));
                $slider.slider('values', [min, max]);
                
                const $form = $slider.closest('form');
                const $minInput = $form.find('.min-price-input');
                const $maxInput = $form.find('.max-price-input');
                const $minDisplay = $form.find('.price-from');
                const $maxDisplay = $form.find('.price-to');
                
                $minInput.val(min);
                $maxInput.val(max);
                this.updatePriceDisplay(min, max, $minDisplay, $maxDisplay);
            }
            
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
                            this.initPriceSlider(); // Reinitialize price slider after view change
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