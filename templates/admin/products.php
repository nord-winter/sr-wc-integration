<?php
if (!defined('ABSPATH')) {
    exit;
}

// Save product settings
if (isset($_POST['sr_save_product_settings'])) {
    check_admin_referer('sr_product_settings');
    
    $product_mappings = array();
    $package_settings = array();
    
    // Save product mappings
    if (isset($_POST['sr_product_mapping'])) {
        foreach ($_POST['sr_product_mapping'] as $wc_product_id => $sr_data) {
            if (!empty($sr_data['item_id'])) {
                $product_mappings[$wc_product_id] = array(
                    'sr_item_id' => sanitize_text_field($sr_data['item_id']),
                    'variants' => array(
                        '1x' => array(
                            'price' => floatval($sr_data['1x_price']),
                            'quantity' => intval($sr_data['1x_quantity'])
                        ),
                        '2x' => array(
                            'price' => floatval($sr_data['2x_price']),
                            'quantity' => intval($sr_data['2x_quantity'])
                        ),
                        '3x' => array(
                            'price' => floatval($sr_data['3x_price']),
                            'quantity' => intval($sr_data['3x_quantity'])
                        ),
                        '4x' => array(
                            'price' => floatval($sr_data['4x_price']),
                            'quantity' => intval($sr_data['4x_quantity'])
                        )
                    )
                );
            }
        }
    }
    
    update_option('sr_product_mappings', $product_mappings);
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Product settings saved successfully.', 'sr-integration') . '</p></div>';
}

// Get current settings
$product_mappings = get_option('sr_product_mappings', array());

// Get all WooCommerce products
$products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish'
));
?>

<div class="wrap sr-products-settings">
    <h1><?php esc_html_e('Product Settings', 'sr-integration'); ?></h1>

    <div class="sr-product-settings-container">
        <form method="post" action="">
            <?php wp_nonce_field('sr_product_settings'); ?>

            <div class="sr-section">
                <h2><?php esc_html_e('Product Package Configuration', 'sr-integration'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure product packages and their mapping to SalesRender items.', 'sr-integration'); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-product"><?php esc_html_e('WooCommerce Product', 'sr-integration'); ?></th>
                            <th class="column-sr-id"><?php esc_html_e('SalesRender Item ID', 'sr-integration'); ?></th>
                            <th class="column-variants" colspan="2">
                                <?php esc_html_e('1x Package', 'sr-integration'); ?>
                            </th>
                            <th class="column-variants" colspan="2">
                                <?php esc_html_e('2x Package', 'sr-integration'); ?>
                            </th>
                            <th class="column-variants" colspan="2">
                                <?php esc_html_e('3x Package', 'sr-integration'); ?>
                            </th>
                            <th class="column-variants" colspan="2">
                                <?php esc_html_e('4x Package', 'sr-integration'); ?>
                            </th>
                        </tr>
                        <tr class="variant-headers">
                            <th colspan="2"></th>
                            <th><?php esc_html_e('Price', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Qty', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Price', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Qty', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Price', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Qty', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Price', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('Qty', 'sr-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php 
                            $product_id = $product->get_id();
                            $mapping = isset($product_mappings[$product_id]) ? $product_mappings[$product_id] : array();
                            $variants = isset($mapping['variants']) ? $mapping['variants'] : array();
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($product->get_name()); ?>
                                    <input type="hidden" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][product_id]" 
                                           value="<?php echo esc_attr($product_id); ?>">
                                </td>
                                <td>
                                    <input type="text" 
                                           class="regular-text" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][item_id]" 
                                           value="<?php echo esc_attr($mapping['sr_item_id'] ?? ''); ?>" 
                                           placeholder="<?php esc_attr_e('SalesRender ID', 'sr-integration'); ?>">
                                </td>

                                <!-- 1x Package -->
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           step="0.01" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][1x_price]" 
                                           value="<?php echo esc_attr($variants['1x']['price'] ?? ''); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][1x_quantity]" 
                                           value="<?php echo esc_attr($variants['1x']['quantity'] ?? ''); ?>">
                                </td>

                                <!-- 2x Package -->
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           step="0.01" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][2x_price]" 
                                           value="<?php echo esc_attr($variants['2x']['price'] ?? ''); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][2x_quantity]" 
                                           value="<?php echo esc_attr($variants['2x']['quantity'] ?? ''); ?>">
                                </td>

                                <!-- 3x Package -->
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           step="0.01" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][3x_price]" 
                                           value="<?php echo esc_attr($variants['3x']['price'] ?? ''); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][3x_quantity]" 
                                           value="<?php echo esc_attr($variants['3x']['quantity'] ?? ''); ?>">
                                </td>

                                <!-- 4x Package -->
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           step="0.01" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][4x_price]" 
                                           value="<?php echo esc_attr($variants['4x']['price'] ?? ''); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           class="small-text" 
                                           name="sr_product_mapping[<?php echo esc_attr($product_id); ?>][4x_quantity]" 
                                           value="<?php echo esc_attr($variants['4x']['quantity'] ?? ''); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sr-bulk-actions">
                    <button type="button" class="button sr-calculate-prices">
                        <?php esc_html_e('Auto-calculate Package Prices', 'sr-integration'); ?>
                    </button>
                    <button type="button" class="button sr-copy-settings">
                        <?php esc_html_e('Copy Settings from Product', 'sr-integration'); ?>
                    </button>
                </div>
            </div>

            <p class="submit">
                <input type="submit" 
                       name="sr_save_product_settings" 
                       class="button button-primary" 
                       value="<?php esc_attr_e('Save Product Settings', 'sr-integration'); ?>">
            </p>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Auto-calculate package prices
    $('.sr-calculate-prices').on('click', function() {
        $('tbody tr').each(function() {
            var $row = $(this);
            var basePrice = parseFloat($row.find('input[name*="1x_price"]').val()) || 0;
            
            // Calculate prices for other packages with discount
            if (basePrice > 0) {
                // 2x package (5% discount)
                $row.find('input[name*="2x_price"]').val((basePrice * 1.95).toFixed(2));
                
                // 3x package (10% discount)
                $row.find('input[name*="3x_price"]').val((basePrice * 2.90).toFixed(2));
                
                // 4x package (15% discount)
                $row.find('input[name*="4x_price"]').val((basePrice * 3.85).toFixed(2));
            }
        });
    });

    // Copy settings from another product
    $('.sr-copy-settings').on('click', function() {
        var $sourceRow = $('tbody tr:first');
        var settings = {
            '1x': {
                price: $sourceRow.find('input[name*="1x_price"]').val(),
                quantity: $sourceRow.find('input[name*="1x_quantity"]').val()
            },
            '2x': {
                price: $sourceRow.find('input[name*="2x_price"]').val(),
                quantity: $sourceRow.find('input[name*="2x_quantity"]').val()
            },
            '3x': {
                price: $sourceRow.find('input[name*="3x_price"]').val(),
                quantity: $sourceRow.find('input[name*="3x_quantity"]').val()
            },
            '4x': {
                price: $sourceRow.find('input[name*="4x_price"]').val(),
                quantity: $sourceRow.find('input[name*="4x_quantity"]').val()
            }
        };

        if (confirm('<?php esc_html_e('Copy settings from the first product to all others?', 'sr-integration'); ?>')) {
            $('tbody tr:not(:first)').each(function() {
                var $row = $(this);
                for (var variant in settings) {
                    $row.find('input[name*="' + variant + '_price"]').val(settings[variant].price);
                    $row.find('input[name*="' + variant + '_quantity"]').val(settings[variant].quantity);
                }
            });
        }
    });
});
</script>

<style>
.sr-product-settings-container {
    margin-top: 20px;
}

.sr-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.column-product {
    width: 20%;
}

.column-sr-id {
    width: 15%;
}

.column-variants {
    width: 8%;
}

.variant-headers th {
    text-align: center;
    font-size: 12px;
}

input.small-text {
    width: 70px !important;
}

.sr-bulk-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.sr-bulk-actions button {
    margin-right: 10px;
}

table input {
    max-width: 100%;
}

.description {
    margin-bottom: 20px;
}

/* Mobile responsiveness */
@media screen and (max-width: 782px) {
    .wp-list-table {
        display: block;
        overflow-x: auto;
    }
    
    input.small-text {
        width: 50px !important;
    }
    
    .column-product {
        width: 30%;
    }
}
</style>