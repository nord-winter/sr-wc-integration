<?php
/**
 * Template Name: SR Checkout
 */

defined('ABSPATH') || exit;

get_header('checkout');
?>

<div class="sr-checkout-container<?php echo wp_is_mobile() ? ' sr-mobile' : ''; ?>">
    <form id="sr-checkout-form" method="post">
        <?php wp_nonce_field('sr_checkout_process', 'sr_checkout_nonce'); ?>
        
        <?php if (!wp_is_mobile()): ?>
        <!-- Desktop Layout -->
        <div class="sr-checkout-grid">
            <!-- Left Column -->
            <div class="sr-checkout-left">
                <!-- Step 1: Product Selection -->
                <div class="sr-section" id="sr-product-selection">
                    <h2><?php esc_html_e('Select Product', 'sr-integration'); ?></h2>
                    <div class="sr-products-grid">
                        <?php 
                        // Render product options (4x, 3x, 2x, 1x)
                        $this->render_product_options();
                        ?>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="sr-section" id="sr-personal-info">
                    <h2><?php esc_html_e('Personal Information', 'sr-integration'); ?></h2>
                    <div class="sr-form-group">
                        <input type="text" name="first_name" placeholder="<?php esc_attr_e('First Name', 'sr-integration'); ?>" required>
                        <input type="text" name="last_name" placeholder="<?php esc_attr_e('Last Name', 'sr-integration'); ?>" required>
                        <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'sr-integration'); ?>" required>
                        <div class="sr-phone-input">
                            <select name="phone_code">
                                <option value="+66">+66</option>
                                <!-- Add other country codes -->
                            </select>
                            <input type="tel" name="phone" placeholder="<?php esc_attr_e('Phone Number', 'sr-integration'); ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="sr-checkout-right">
                <!-- Step 3: Shipping Information -->
                <div class="sr-section" id="sr-shipping-info">
                    <h2><?php esc_html_e('Shipping Information', 'sr-integration'); ?></h2>
                    <div class="sr-form-group">
                        <select name="country" required>
                            <option value=""><?php esc_html_e('Select Country', 'sr-integration'); ?></option>
                            <?php $this->render_country_options(); ?>
                        </select>
                        <input type="text" name="city" placeholder="<?php esc_attr_e('City', 'sr-integration'); ?>" required>
                        <textarea name="address" placeholder="<?php esc_attr_e('Address', 'sr-integration'); ?>" required></textarea>
                        <input type="text" name="postcode" placeholder="<?php esc_attr_e('Postal Code', 'sr-integration'); ?>" required>
                    </div>
                </div>

                <!-- Step 4: Payment -->
                <div class="sr-section" id="sr-payment">
                    <h2><?php esc_html_e('Payment Information', 'sr-integration'); ?></h2>
                    <div id="opn-payment-container">
                        <!-- OPN Payment Form will be injected here -->
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Mobile Layout -->
        <div class="sr-checkout-mobile">
            <!-- Steps in vertical order -->
            <div class="sr-section" id="sr-product-selection">
                <!-- Product Selection -->
            </div>
            
            <div class="sr-section" id="sr-personal-info">
                <!-- Personal Information -->
            </div>
            
            <div class="sr-section" id="sr-shipping-info">
                <!-- Shipping Information -->
            </div>
            
            <div class="sr-section" id="sr-payment">
                <!-- Payment Information -->
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Summary -->
        <div class="sr-order-summary">
            <!-- Display order details and total -->
        </div>

        <!-- Submit Button -->
        <div class="sr-checkout-submit">
            <button type="submit" class="sr-submit-button">
                <?php esc_html_e('Complete Order', 'sr-integration'); ?>
            </button>
        </div>
    </form>
</div>

<?php get_footer('checkout'); ?>