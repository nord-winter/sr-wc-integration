<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Billing form template
 */
?>
<div class="sr-billing-form">
    <div class="sr-form-row">
        <div class="sr-form-group">
            <label for="billing_first_name"><?php esc_html_e('First Name', 'sr-integration'); ?> <span class="required">*</span></label>
            <input type="text" 
                   class="sr-input" 
                   name="billing_first_name" 
                   id="billing_first_name" 
                   placeholder="<?php esc_attr_e('First name', 'sr-integration'); ?>"
                   required>
        </div>
        <div class="sr-form-group">
            <label for="billing_last_name"><?php esc_html_e('Last Name', 'sr-integration'); ?> <span class="required">*</span></label>
            <input type="text" 
                   class="sr-input" 
                   name="billing_last_name" 
                   id="billing_last_name" 
                   placeholder="<?php esc_attr_e('Last name', 'sr-integration'); ?>"
                   required>
        </div>
    </div>

    <div class="sr-form-group">
        <label for="billing_email"><?php esc_html_e('Email Address', 'sr-integration'); ?> <span class="required">*</span></label>
        <input type="email" 
               class="sr-input" 
               name="billing_email" 
               id="billing_email" 
               placeholder="<?php esc_attr_e('Email address', 'sr-integration'); ?>"
               required>
    </div>

    <div class="sr-form-group">
        <label for="billing_phone"><?php esc_html_e('Phone', 'sr-integration'); ?> <span class="required">*</span></label>
        <div class="sr-phone-input">
            <select name="billing_phone_code" id="billing_phone_code" class="sr-select">
                <option value="+66">+66</option>
                <option value="+60">+60</option>
                <option value="+65">+65</option>
            </select>
            <input type="tel" 
                   class="sr-input" 
                   name="billing_phone" 
                   id="billing_phone" 
                   placeholder="<?php esc_attr_e('Phone number', 'sr-integration'); ?>"
                   pattern="[0-9]{9,10}"
                   required>
        </div>
        <small class="sr-form-help"><?php esc_html_e('Example: 0812345678', 'sr-integration'); ?></small>
    </div>

    <div class="sr-form-group">
        <label for="billing_address_1"><?php esc_html_e('Street Address', 'sr-integration'); ?> <span class="required">*</span></label>
        <input type="text" 
               class="sr-input" 
               name="billing_address_1" 
               id="billing_address_1" 
               placeholder="<?php esc_attr_e('House number and street name', 'sr-integration'); ?>"
               required>
    </div>

    <div class="sr-form-row">
        <div class="sr-form-group">
            <label for="billing_city"><?php esc_html_e('City', 'sr-integration'); ?> <span class="required">*</span></label>
            <input type="text" 
                   class="sr-input" 
                   name="billing_city" 
                   id="billing_city" 
                   placeholder="<?php esc_attr_e('City', 'sr-integration'); ?>"
                   required>
        </div>
        <div class="sr-form-group">
            <label for="billing_postcode"><?php esc_html_e('Postal Code', 'sr-integration'); ?> <span class="required">*</span></label>
            <input type="text" 
                   class="sr-input" 
                   name="billing_postcode" 
                   id="billing_postcode" 
                   placeholder="<?php esc_attr_e('Postal code', 'sr-integration'); ?>"
                   required>
        </div>
    </div>

    <div class="sr-form-group">
        <label for="billing_country"><?php esc_html_e('Country', 'sr-integration'); ?> <span class="required">*</span></label>
        <select name="billing_country" id="billing_country" class="sr-select" required>
            <option value=""><?php esc_html_e('Select a country', 'sr-integration'); ?></option>
            <?php 
            foreach (WC()->countries->get_allowed_countries() as $code => $name) {
                echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
            }
            ?>
        </select>
    </div>
</div>