<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment form template
 * 
 * @var array $gateway_settings OPN gateway settings
 */

$gateway_settings = get_option('sr_opn_settings', array());
$test_mode = isset($gateway_settings['test_mode']) && $gateway_settings['test_mode'] === 'yes';
$public_key = $test_mode ? $gateway_settings['test_public_key'] : $gateway_settings['public_key'];
$enable_qr = isset($gateway_settings['enable_qr']) && $gateway_settings['enable_qr'] === 'yes';
$enable_installments = isset($gateway_settings['enable_installments']) && $gateway_settings['enable_installments'] === 'yes';
?>

<div class="sr-payment-form" id="sr-payment-form">
    <!-- Payment Method Selection -->
    <div class="sr-payment-methods">
        <div class="sr-payment-method active" data-method="credit_card">
            <div class="sr-payment-method-icon">
                <i class="sr-icon-credit-card"></i>
            </div>
            <div class="sr-payment-method-label">
                <?php esc_html_e('Credit / Debit Card', 'sr-integration'); ?>
            </div>
            <div class="sr-payment-method-description">
                <?php esc_html_e('Secure payment with credit or debit card', 'sr-integration'); ?>
            </div>
        </div>

        <?php if ($enable_qr): ?>
        <div class="sr-payment-method" data-method="promptpay">
            <div class="sr-payment-method-icon">
                <i class="sr-icon-qr-code"></i>
            </div>
            <div class="sr-payment-method-label">
                <?php esc_html_e('PromptPay QR', 'sr-integration'); ?>
            </div>
            <div class="sr-payment-method-description">
                <?php esc_html_e('Scan QR code with your banking app', 'sr-integration'); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($enable_installments): ?>
        <div class="sr-payment-method" data-method="installment">
            <div class="sr-payment-method-icon">
                <i class="sr-icon-calendar"></i>
            </div>
            <div class="sr-payment-method-label">
                <?php esc_html_e('Installment Payment', 'sr-integration'); ?>
            </div>
            <div class="sr-payment-method-description">
                <?php esc_html_e('Pay in installments with your credit card', 'sr-integration'); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Credit Card Form Container -->
    <div id="sr-credit-card-form" class="sr-payment-method-form active">
        <div id="opn-card-element"></div>
        <div id="sr-card-errors" role="alert"></div>
    </div>

    <!-- PromptPay Container -->
    <?php if ($enable_qr): ?>
    <div id="sr-promptpay-form" class="sr-payment-method-form">
        <div class="sr-qr-container">
            <div id="sr-qr-code"></div>
            <div class="sr-qr-instructions">
                <ol>
                    <li><?php esc_html_e('Open your mobile banking app', 'sr-integration'); ?></li>
                    <li><?php esc_html_e('Scan the QR code', 'sr-integration'); ?></li>
                    <li><?php esc_html_e('Confirm the payment in your app', 'sr-integration'); ?></li>
                </ol>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Installment Form -->
    <?php if ($enable_installments): ?>
    <div id="sr-installment-form" class="sr-payment-method-form">
        <div class="sr-installment-options">
            <select name="installment_terms" id="sr-installment-terms" class="sr-select">
                <option value=""><?php esc_html_e('Select number of installments', 'sr-integration'); ?></option>
                <?php
                $terms = array(3, 6, 10);
                foreach ($terms as $term) {
                    printf(
                        '<option value="%1$d">%2$s</option>',
                        $term,
                        sprintf(
                            /* translators: %d: number of months */
                            esc_html__('%d months', 'sr-integration'),
                            $term
                        )
                    );
                }
                ?>
            </select>
            <div id="sr-installment-details"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment Information -->
    <div class="sr-payment-info">
        <div class="sr-secure-badge">
            <i class="sr-icon-lock"></i>
            <?php esc_html_e('Secure Payment by OPN Payments', 'sr-integration'); ?>
        </div>
        
        <?php if ($test_mode): ?>
        <div class="sr-test-mode-notice">
            <i class="sr-icon-info"></i>
            <?php esc_html_e('Test mode enabled - no real charges will be made', 'sr-integration'); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Hidden Fields -->
    <input type="hidden" name="payment_method" id="sr-payment-method" value="credit_card">
    <input type="hidden" name="opn_token" id="sr-opn-token">
    <input type="hidden" name="opn_source" id="sr-opn-source">
</div>

<script>
// Конфигурация OPN Payment
window.srPaymentConfig = {
    publicKey: '<?php echo esc_js($public_key); ?>',
    currency: '<?php echo esc_js(get_woocommerce_currency()); ?>',
    amount: <?php echo esc_js($this->get_order_total() * 100); ?>,
    testMode: <?php echo $test_mode ? 'true' : 'false'; ?>,
    locale: '<?php echo esc_js(determine_locale()); ?>',
    submitButtonText: '<?php echo esc_js(__('Pay', 'sr-integration')); ?>'
};
</script>