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
                            <div class="sr-form-fd-row">
                                <input type="text" name="first_name"
                                    placeholder="<?php esc_attr_e('First Name', 'sr-integration'); ?>" required>
                                <input type="text" name="last_name"
                                    placeholder="<?php esc_attr_e('Last Name', 'sr-integration'); ?>" required>
                            </div>
                            <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'sr-integration'); ?>"
                                required>
                            <div class="sr-phone-input">
                                <select name="phone_code">
                                    <option value="+66">+66</option>
                                    <!-- Add other country codes -->
                                </select>
                                <input type="tel" name="phone"
                                    placeholder="<?php esc_attr_e('Phone Number', 'sr-integration'); ?>" required>
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
                            <input type="text" name="city" placeholder="<?php esc_attr_e('City', 'sr-integration'); ?>"
                                required>
                            <textarea name="address" placeholder="<?php esc_attr_e('Address', 'sr-integration'); ?>"
                                required></textarea>
                            <input type="text" name="postcode"
                                placeholder="<?php esc_attr_e('Postal Code', 'sr-integration'); ?>" required>
                        </div>
                    </div>

                    <!-- Step 4: Payment -->
                    <div class="sr-section" id="sr-payment">
                        <h2><?php esc_html_e('Payment Information', 'sr-integration'); ?></h2>
                        <div id="opn-payment-container">
                            <!-- Выбор способа оплаты -->
                            <div class="sr-payment-methods">
                                <div class="sr-payment-method active" data-method="credit_card">
                                    <div class="sr-method-icon">
                                        <i class="sr-icon-card"></i>
                                    </div>
                                    <div class="sr-method-title">
                                        <?php esc_html_e('Credit / Debit Card', 'sr-integration'); ?>
                                    </div>
                                </div>

                                <div class="sr-payment-method" data-method="promptpay">
                                    <div class="sr-method-icon">
                                        <i class="sr-icon-qr"></i>
                                    </div>
                                    <div class="sr-method-title">
                                        <?php esc_html_e('PromptPay', 'sr-integration'); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Форма для кредитной карты -->
                            <div id="credit-card-form" class="sr-payment-form active">
                                <div id="card-element"></div>
                                <div id="card-errors" role="alert"></div>
                            </div>

                            <!-- QR код для PromptPay -->
                            <div id="promptpay-form" class="sr-payment-form">
                                <div id="qr-container"></div>
                                <div class="promptpay-instructions">
                                    <ol>
                                        <li><?php esc_html_e('Open your banking app', 'sr-integration'); ?></li>
                                        <li><?php esc_html_e('Scan the QR code', 'sr-integration'); ?></li>
                                        <li><?php esc_html_e('Confirm the payment', 'sr-integration'); ?></li>
                                    </ol>
                                </div>
                            </div>

                            <input type="hidden" name="payment_method" id="selected-payment-method" value="credit_card">
                            <input type="hidden" name="payment_token" id="payment-token">
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