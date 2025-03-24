<?php
/*
Plugin Name: Event Guest List for WooCommerce
Description: Custom plugin to generate guest lists for ticketed WooCommerce products.
Version: 1.1
Date: 2025/03/24
Author: William Yell
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Guest List',
        'Guest List',
        'manage_woocommerce',
        'guest-list',
        'egl_render_guest_list_page'
    );
});

function egl_render_guest_list_page()
{
    ?>
    <div class="wrap">
        <h1>Event Guest List</h1>
        <form method="get">
            <input type="hidden" name="page" value="guest-list" />
            <label for="product_id">Select Ticket Product:</label>
            <select name="product_id" id="product_id">
                <option value="">-- Choose a Product --</option>
                <?php
                $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
                foreach ($products as $product) {
                    $selected = (isset($_GET['product_id']) && $_GET['product_id'] == $product->get_id()) ? 'selected' : '';
                    echo "<option value='{$product->get_id()}' $selected>{$product->get_name()}</option>";
                }
                ?>
            </select>
            <button type="submit" class="button button-primary">Show Guest List</button>
        </form>

        <?php if (!empty($_GET['product_id'])): ?>
            <hr>
            <h2>Guest List</h2>
            <form method="post">
                <input type="hidden" name="egl_export_csv" value="1">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($_GET['product_id']); ?>">
                <button type="submit" class="button">Export CSV</button>
            </form>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Quantity</th>
                        <th>Product Option</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $orders = wc_get_orders([
                    'limit' => 500,
                    'status' => ['completed', 'processing', 'on-hold'],
                ]);
                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        if ($item->get_product_id() == $_GET['product_id']) {
                            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                            $email = $order->get_billing_email();
                            $qty = $item->get_quantity();
                            $option = $item->get_meta('Product Option');
                            $date_created = $order->get_date_created();
                            $date = $date_created ? $date_created->date('Y-m-d H:i') : '';
                            echo '<tr>';
                            echo '<td>' . esc_html($order->get_id()) . '</td>';
                            echo '<td>' . esc_html($name) . '</td>';
                            echo '<td>' . esc_html($email) . '</td>';
                            echo '<td>' . esc_html($qty) . '</td>';
                            echo '<td>' . esc_html($option) . '</td>';
                            echo '<td>' . esc_html($date) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
                ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_init', function () {
    if (!current_user_can('manage_woocommerce') || empty($_POST['egl_export_csv']) || empty($_POST['product_id'])) return;

    $product_id = intval($_POST['product_id']);
    $filename = 'guest-list-' . $product_id . '-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename={$filename}");
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Name', 'Email', 'Quantity', 'Product Option', 'Purchase Date']);

    $orders = wc_get_orders([
        'limit' => 500,
        'status' => ['completed', 'processing', 'on-hold'],
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $email = $order->get_billing_email();
                $qty = $item->get_quantity();
                $option = $item->get_meta('Product Option');
                $date_created = $order->get_date_created();
                $date = $date_created ? $date_created->date('Y-m-d H:i') : '';
                fputcsv($output, [
                    $order->get_id(),
                    $name,
                    $email,
                    $qty,
                    $option,
                    $date
                ]);
            }
        }
    }

    fclose($output);
    exit;
});

