<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping form template
 */
?>
<div class="sr-shipping-form">
    <div class="sr-form-group">
        <label>
            <input type="checkbox" 
                   name="ship_to_different_address" 
                   id="ship_to_different_address" 
                   value="1">
            <?php esc_html_e('Ship to a different address?', 'sr-integration'); ?>
        </label>
    </div>

    <div id="shipping_address_fields" style="display: none;">
        <div class="sr-form-row">
            <div class="sr-form-group">
                <label for="shipping_first_name"><?php esc_html_e('First Name', 'sr-integration'); ?> <span class="required">*</span></label>
                <input type="text" 
                       class="sr-input" 
                       name="shipping_first_name" 
                       id="shipping_first_name" 
                       placeholder="<?php esc_attr_e('First name', 'sr-integration'); ?>">
            </div>
            <div class="sr-form-group">
                <label for="shipping_last_name"><?php esc_html_e('Last Name', 'sr-integration'); ?> <span class="required">*</span></label>
                <input type="text" 
                       class="sr-input" 
                       name="shipping_last_name" 
                       id="shipping_last_name" 
                       placeholder="<?php esc_attr_e('Last name', 'sr-integration'); ?>">
            </div>
        </div>

        <div class="sr-form-group">
            <label for="shipping_address_1"><?php esc_html_e('Street Address', 'sr-integration'); ?> <span class="required">*</span></label>
            <input type="text" 
                   class="sr-input" 
                   name="shipping_address_1" 
                   id="shipping_address_1" 
                   placeholder="<?php esc_attr_e('House number and street name', 'sr-integration'); ?>">
        </div>

        <div class="sr-form-row">
            <div class="sr-form-group">
                <label for="shipping_city"><?php esc_html_e('City', 'sr-integration'); ?> <span class="required">*</span></label>
                <input type="text" 
                       class="sr-input" 
                       name="shipping_city" 
                       id="shipping_city" 
                       placeholder="<?php esc_attr_e('City', 'sr-integration'); ?>">
            </div>
            <div class="sr-form-group">
                <label for="shipping_postcode"><?php esc_html_e('Postal Code', 'sr-integration'); ?> <span class="required">*</span></label>
                <input type="text" 
                       class="sr-input" 
                       name="shipping_postcode" 
                       id="shipping_postcode" 
                       placeholder="<?php esc_attr_e('Postal code', 'sr-integration'); ?>">
            </div>
        </div>

        <div class="sr-form-group">
            <label for="shipping_country"><?php esc_html_e('Country', 'sr-integration'); ?> <span class="required">*</span></label>
            <select name="shipping_country" id="shipping_country" class="sr-select">
                <option value=""><?php esc_html_e('Select a country', 'sr-integration'); ?></option>
                <?php 
                foreach (WC()->countries->get_shipping_countries() as $code => $name) {
                    echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="sr-form-group">
            <label for="order_comments"><?php esc_html_e('Order Notes', 'sr-integration'); ?></label>
            <textarea class="sr-textarea" 
                      name="order_comments" 
                      id="order_comments" 
                      placeholder="<?php esc_attr_e('Notes about your order, e.g. special notes for delivery.', 'sr-integration'); ?>"
                      rows="4"></textarea>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var shipToDifferentAddress = document.getElementById('ship_to_different_address');
    var shippingFields = document.getElementById('shipping_address_fields');

    if (shipToDifferentAddress && shippingFields) {
        shipToDifferentAddress.addEventListener('change', function() {
            shippingFields.style.display = this.checked ? 'block' : 'none';
            
            // Toggle required attributes on shipping fields
            var requiredFields = shippingFields.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(function(field) {
                field.required = this.checked;
            }, this);
        });
    }
});
</script>