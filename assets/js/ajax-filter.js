jQuery(function($) {
    'use strict';

    class AjaxProductFilter {
        constructor() {
            this.filterForm = $('.apf-filter-form');
            this.productsContainer = $('.products');
            this.isLoading = false;
            this.debounceTimeout = null;

            if (this.filterForm.length && this.productsContainer.length) {
                this.init();
            }
        }

        init() {
            // Handle checkbox changes
            this.filterForm.on('change', '.apf-filter-checkbox', () => {
                clearTimeout(this.debounceTimeout);
                this.debounceTimeout = setTimeout(() => {
                    this.performAjaxFilter();
                }, 500);
            });

            // Handle reset button
            this.filterForm.on('click', '.apf-reset-filter', (e) => {
                e.preventDefault();
                this.resetFilters();
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
            this.showLoader();

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
                    console.log('AJAX Response:', response); // Debug log
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
                    this.hideLoader();
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
        }

        showLoader() {
            if (!this.loader) {
                this.loader = $('<div class="apf-loader"><div class="spinner"></div></div>');
                $('body').append(this.loader);  // Append to body instead of container
            }
            this.productsContainer.addClass('loading');
            this.loader.show();
        }
        
        hideLoader() {
            if (this.loader) {
                this.loader.hide();
                this.productsContainer.removeClass('loading');
            }
        }
        resetFilters() {
            this.filterForm.find('input:checked').prop('checked', false);
            this.performAjaxFilter();
        }
    }

    // Initialize the filter
    new AjaxProductFilter();
});