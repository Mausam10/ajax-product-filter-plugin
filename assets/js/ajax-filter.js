jQuery(function($) {
    'use strict';

    class AjaxProductFilter {
        constructor() {
            this.filterForm = $('.apf-filter-form');
            this.productsContainer = $('.products');
            this.isLoading = false;
            this.debounceTimeout = null;
            this.checkboxes = this.filterForm.find('.apf-filter-checkbox');
            this.resetButton = this.filterForm.find('.apf-reset-filter');

            if (this.filterForm.length && this.productsContainer.length) {
                this.init();
            }
        }

        init() {
            // Handle checkbox changes
            this.filterForm.on('change', '.apf-filter-checkbox', () => {
                if (this.isLoading) return;
                
                clearTimeout(this.debounceTimeout);
                this.debounceTimeout = setTimeout(() => {
                    this.performAjaxFilter();
                }, 500);
            });

            // Handle reset button
            this.filterForm.on('click', '.apf-reset-filter', (e) => {
                e.preventDefault();
                if (!this.isLoading) {
                    this.resetFilters();
                }
            });

            // Handle form submission (if applicable)
            this.filterForm.on('submit', (e) => {
                e.preventDefault();
                if (!this.isLoading) {
                    this.performAjaxFilter();
                }
            });
        }

        gatherFilters() {
            const filters = {};
            this.filterForm.find('input:checked').each(function() {
                const taxonomy = $(this).attr('name').match(/filter\[(.*?)\]/)[1];
                const value = $(this).val();
                
                if (!filters[taxonomy]) {
                    filters[taxonomy] = [];
                }
                filters[taxonomy].push(value);
            });
            return filters;
        }

        performAjaxFilter() {
            if (this.isLoading) return;
        
            this.isLoading = true;
            this.setLoadingState(true);
            
            const filters = this.gatherFilters();
            
            $.ajax({
                url: apfAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'apf_filter_products',
                    nonce: apfAjax.nonce,
                    filters: filters
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.updateProducts(response.data);
                    } else {
                        console.error('Invalid AJAX response:', response);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                },
                complete: () => {
                    this.setLoadingState(false);
                    this.isLoading = false;
                }
            });
        }

        updateProducts(data) {
            // Update the products container
            if (data.html) {
                this.productsContainer.html(data.html);
            }
            
            // Update result count if available
            if (data.found_posts !== undefined) {
                const countText = data.found_posts === 1 
                    ? '1 product found' 
                    : `${data.found_posts} products found`;
                $('.woocommerce-result-count').text(countText);
            }

            // Trigger WooCommerce scripts refresh
            $(document.body).trigger('wc_fragment_refresh');
            
            // Scroll to products container if it's out of view
            if (this.productsContainer.length && !this.isElementInViewport(this.productsContainer[0])) {
                $('html, body').animate({
                    scrollTop: this.productsContainer.offset().top - 100
                }, 500);
            }
        }

        setLoadingState(isLoading) {
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
        showError(message) {
            // You can implement custom error display logic here
            // For example, show a notification or message above the products
            const errorDiv = $('<div>')
                .addClass('wc-block-components-notice-banner is-error')
                .text(message);
            
            this.productsContainer.before(errorDiv);
            setTimeout(() => errorDiv.fadeOut('slow', function() { $(this).remove(); }), 5000);
        }

        isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }

        resetFilters() {
            this.filterForm.find('input:checked').prop('checked', false);
            this.performAjaxFilter();
        }
    }
    function setLoadingState(isLoading) {
        if (isLoading) {
            document.body.classList.add('filter-loading');
            // Disable all filter inputs while loading
            this.filterForm.find('input, button').prop('disabled', true);
        } else {
            document.body.classList.remove('filter-loading');
            // Re-enable all filter inputs after loading
            this.filterForm.find('input, button').prop('disabled', false);
        }
    }
    // Initialize the filter
    new AjaxProductFilter();
});