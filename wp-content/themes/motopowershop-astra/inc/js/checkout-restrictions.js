document.addEventListener('DOMContentLoaded', function () {
    // Check if Local Pickup is required based on PHP settings passed to JS
    if (pickupOnlySettings.pickupRequired) {
        // Wait until the shipping methods are rendered on the page
        const observer = new MutationObserver(function(mutations, observer) {
            // Locate the WooCommerce Blocks container for the shipping options
            const shippingMethodContainer = document.querySelector('.wc-block-checkout__shipping-method-container');

            if (shippingMethodContainer) {
                // Remove any previously added messages to avoid duplicates
                const existingMessage = document.querySelector('.pickup-only-message');
                if (existingMessage) {
                    existingMessage.remove();
                }

                // Create a new div for our custom message
                const messageDiv = document.createElement('div');
                messageDiv.className = 'wc-block-components-shipping-rates-control__no-results-notice wc-block-components-notice-banner is-warning pickup-only-message';
                messageDiv.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>
                    </svg>
                    <div class="wc-block-components-notice-banner__content">
                        Shipping is unavailable due to items in your cart that require local pickup. Please select "Local Pickup" to proceed. If you wish to ship other items, please complete separate orders for shippable and pickup-only products.
                    </div>
                `;

                // Insert the custom message above the shipping options container
                shippingMethodContainer.parentNode.insertBefore(messageDiv, shippingMethodContainer);

                // Disconnect observer after message is inserted
                observer.disconnect();
            }
        });

        // Observe the body for changes to detect when the shipping methods load
        observer.observe(document.body, { childList: true, subtree: true });
    }
});