<?php
/*
Plugin Name: Event Guest List for WooCommerce
Description: Custom plugin to generate guest lists for ticketed WooCommerce products.
Version: 1.6
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
            <label for="product_cat">Select Category:</label>
            <select name="product_cat" id="product_cat" onchange="this.form.submit()">
                <option value="">-- All Categories --</option>
                <?php
                $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                foreach ($categories as $cat) {
                    $selected = (isset($_GET['product_cat']) && $_GET['product_cat'] == $cat->term_id) ? 'selected' : '';
                    echo "<option value='{$cat->term_id}' $selected>{$cat->name}</option>";
                }
                ?>
            </select>

            <label for="filter_year">Year:</label>
            <select name="filter_year" id="filter_year">
                <option value="">-- Any Year --</option>
                <?php
                $current_year = date('Y');
                for ($y = $current_year; $y >= $current_year - 10; $y--) {
                    $selected = (isset($_GET['filter_year']) && $_GET['filter_year'] == $y) ? 'selected' : '';
                    echo "<option value='{$y}' $selected>{$y}</option>";
                }
                ?>
            </select>

            <label for="product_id">Select Ticket Product:</label>
            <select name="product_id" id="product_id">
                <option value="">-- Choose a Product --</option>
                <?php
                $args = ['limit' => -1, 'status' => 'publish'];
                if (!empty($_GET['product_cat'])) {
                    $args['category'] = [get_term($_GET['product_cat'])->slug];
                }
                $products = wc_get_products($args);
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
            <h2>Guest List Summary</h2>
            <?php
            $year_filter = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';
            $product_id = $_GET['product_id'];
            $summary = ['processing' => 0, 'on-hold' => 0, 'completed' => 0];

            $orders = wc_get_orders([
                'limit' => 500,
                'status' => ['completed', 'processing', 'on-hold'],
            ]);

            foreach ($orders as $order) {
                $date_created = $order->get_date_created();
                if ($year_filter && $date_created && $date_created->format('Y') != $year_filter) continue;

                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $product_id) {
                        $status = $order->get_status();
                        $summary[$status] += $item->get_quantity();
                    }
                }
            }

            $total_tickets = array_sum($summary);
            echo "<p><strong>Processing:</strong> {$summary['processing']} &nbsp; <strong>On-Hold:</strong> {$summary['on-hold']} &nbsp; <strong>Completed:</strong> {$summary['completed']} &nbsp; <strong>Total Tickets:</strong> {$total_tickets}</p>";
            ?>

            <h2>Guest List</h2>
            <form method="post">
                <input type="hidden" name="egl_export_csv" value="1">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($_GET['product_id']); ?>">
                <input type="hidden" name="filter_year" value="<?php echo esc_attr($_GET['filter_year'] ?? ''); ?>">
                <button type="submit" class="button">Export CSV</button>
            </form>
            <style>
                .egl-status-completed { background-color: #e6ffed; }
                .egl-status-on-hold { background-color: #ffe6e6; }
                .egl-status-processing { background-color: #fff3e0; }
            </style>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Quantity</th>
                        <th>Variation</th>
                        <th>Order Value</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($orders as $order) {
                    $date_created = $order->get_date_created();
                    if ($year_filter && $date_created && $date_created->format('Y') != $year_filter) continue;

                    foreach ($order->get_items() as $item) {
                        if ($item->get_product_id() == $_GET['product_id']) {
                            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                            $email = $order->get_billing_email();
                            $qty = $item->get_quantity();

                            $variation = '';
                            foreach ($item->get_meta_data() as $meta) {
                                if (strpos(strtolower($meta->key), 'option') !== false || strpos(strtolower($meta->key), 'attribute') !== false) {
                                    $variation .= sanitize_text_field(wp_strip_all_tags($meta->value)) . ' ';
                                }
                            }
                            $variation = trim($variation);

                            $total = wc_price($item->get_total());
                            $status = $order->get_status();
                            $status_label = ucfirst($status);
                            $row_class = 'egl-status-' . $status;
                            $date = $date_created ? $date_created->date('Y-m-d H:i') : '';
                            echo "<tr class='{$row_class}'>";
                            echo '<td>' . esc_html($order->get_id()) . '</td>';
                            echo '<td>' . esc_html($status_label) . '</td>';
                            echo '<td>' . esc_html($name) . '</td>';
                            echo '<td>' . esc_html($email) . '</td>';
                            echo '<td>' . esc_html($qty) . '</td>';
                            echo '<td>' . esc_html($variation) . '</td>';
                            echo '<td>' . $total . '</td>';
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
    $year_filter = isset($_POST['filter_year']) ? $_POST['filter_year'] : '';
    $filename = 'guest-list-' . $product_id . '-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename={$filename}");
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Status', 'Name', 'Email', 'Quantity', 'Variation', 'Order Value', 'Purchase Date']);

    $orders = wc_get_orders([
        'limit' => 500,
        'status' => ['completed', 'processing', 'on-hold'],
    ]);

    foreach ($orders as $order) {
        $date_created = $order->get_date_created();
        if ($year_filter && $date_created && $date_created->format('Y') != $year_filter) continue;

        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $email = $order->get_billing_email();
                $qty = $item->get_quantity();

                $variation = '';
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos(strtolower($meta->key), 'option') !== false || strpos(strtolower($meta->key), 'attribute') !== false) {
                        $variation .= sanitize_text_field(wp_strip_all_tags($meta->value)) . ' ';
                    }
                }
                $variation = trim($variation);

                $total = $item->get_total();
                $status = ucfirst($order->get_status());
                $date = $date_created ? $date_created->date('Y-m-d H:i') : '';

                fputcsv($output, [
                    $order->get_id(),
                    $status,
                    $name,
                    $email,
                    $qty,
                    $variation,
                    $total,
                    $date
                ]);
            }
        }
    }

    fclose($output);
    exit;
});

