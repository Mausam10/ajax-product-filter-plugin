// assets/js/price-slider.js
(function($) {
    'use strict';

    class PriceSlider {
        constructor(element, options) {
            this.$wrapper = $(element);
            this.$slider = this.$wrapper.find('#price-slider');
            this.$form = this.$wrapper.find('.price-filter-form');
            this.$minInput = this.$wrapper.find('input[name="min_price"]');
            this.$maxInput = this.$wrapper.find('input[name="max_price"]');
            this.$minLabel = this.$wrapper.find('.price-from');
            this.$maxLabel = this.$wrapper.find('.price-to');
            this.$productsContainer = $('.products');

            this.options = {
                minPrice: parseFloat(this.$slider.data('min')) || 0,
                maxPrice: parseFloat(this.$slider.data('max')) || 1000,
                stepSize: parseFloat(this.$slider.data('step')) || 1,
                currencySymbol: priceSliderData.currency_symbol || '$'
            };

            this.init();
        }

        init() {
            this.initSlider();
            this.initEvents();
        }

        initSlider() {
            const self = this;

            this.$slider.slider({
                range: true,
                min: this.options.minPrice,
                max: this.options.maxPrice,
                step: this.options.stepSize,
                values: [this.options.minPrice, this.options.maxPrice],
                create: function() {
                    self.updateDisplay(self.options.minPrice, self.options.maxPrice);
                },
                slide: function(event, ui) {
                    self.updateDisplay(ui.values[0], ui.values[1]);
                },
                change: function(event, ui) {
                    if (event.originalEvent) {
                        self.filterProducts(ui.values[0], ui.values[1]);
                    }
                }
            });

            // Add custom handles with tooltips
            this.$slider.find('.ui-slider-handle').first().html('<span class="tooltip"></span>');
            this.$slider.find('.ui-slider-handle').last().html('<span class="tooltip"></span>');
        }

        initEvents() {
            this.$form.on('submit', (e) => {
                e.preventDefault();
                const values = this.$slider.slider('values');
                this.filterProducts(values[0], values[1]);
            });

            // Handle keyboard accessibility
            this.$slider.find('.ui-slider-handle').on('keydown', (e) => {
                const $handle = $(e.target);
                const value = this.$slider.slider('values', $handle.index());
                const step = this.options.stepSize;

                switch(e.keyCode) {
                    case 37: // Left arrow
                    case 40: // Down arrow
                        this.$slider.slider('values', $handle.index(), value - step);
                        e.preventDefault();
                        break;
                    case 39: // Right arrow
                    case 38: // Up arrow
                        this.$slider.slider('values', $handle.index(), value + step);
                        e.preventDefault();
                        break;
                }
            });
        }

        updateDisplay(minPrice, maxPrice) {
            // Update hidden inputs
            this.$minInput.val(minPrice);
            this.$maxInput.val(maxPrice);

            // Update labels
            this.$minLabel.text(this.formatPrice(minPrice));
            this.$maxLabel.text(this.formatPrice(maxPrice));

            // Update tooltips
            this.$slider.find('.ui-slider-handle:first .tooltip')
                .text(this.formatPrice(minPrice));
            this.$slider.find('.ui-slider-handle:last .tooltip')
                .text(this.formatPrice(maxPrice));
        }

        formatPrice(price) {
            return this.options.currencySymbol + price.toFixed(2);
        }
    filterProducts(minPrice, maxPrice) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        document.body.classList.add('filter-loading'); // Apply to body instead
        
        $.ajax({
            url: priceSliderData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'filter_by_price',
                    nonce: priceSliderData.nonce,
                    min_price: minPrice,
                    max_price: maxPrice
                },
                success: (response) => {
                    if (response.success) {
                        this.$productsContainer.html(response.data.html);
                        
                        // Update product count
                        if (response.data.count) {
                            $('.woocommerce-result-count').text(
                                `Showing all ${response.data.count} results`
                            );
                        }

                        // Trigger event for other scripts
                        $(document).trigger('priceFilter:updated', [response.data]);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error filtering products:', error);
                },
         
            complete: () => {
                document.body.classList.remove('filter-loading');
                this.isLoading = false;
            }
        });
    }
}
    // Initialize on document ready
    $(document).ready(function() {
        $('.price-filter-wrapper').each(function() {
            new PriceSlider(this);
        });
    });
    function setLoadingState(isLoading) {
        const html = document.documentElement;
        const body = document.body;
        
        if (isLoading) {
            // Store current scroll position using modern scrollY
            this.scrollPosition = window.scrollY;
            // Add loading classes
            html.classList.add('loading-active');
            body.classList.add('filter-loading');
            // Fix the body in place
            body.style.top = `-${this.scrollPosition}px`;
        } else {
            // Remove loading classes
            html.classList.remove('loading-active');
            body.classList.remove('filter-loading');
            // Restore position without movement
            body.style.top = '';
            window.scrollTo(0, this.scrollPosition);
        }
    }

})(jQuery);

