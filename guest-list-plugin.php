<?php
/*
Plugin Name: Event Guest List for WooCommerce
Description: Custom plugin to generate guest lists for ticketed WooCommerce products.
Version: 2.1.1
Date: 2025/03/26
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
            $payment_summary = [];
            $variation_summary = [];
            $variation_counts = [];
            $total_revenue = 0.0;
            $guest_rows = '';

            $orders = wc_get_orders([
                'limit' => 500,
                'status' => ['completed', 'processing', 'on-hold'],
            ]);

            foreach ($orders as $order) {
                $date_created = $order->get_date_created();
                if ($year_filter && $date_created && $date_created->format('Y') != $year_filter) continue;

                $payment_method = $order->get_payment_method_title();
                $status = $order->get_status();
                $status_class = 'egl-status-' . $status;

                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $product_id) {
                        $qty = $item->get_quantity();
                        $item_total = (float) $item->get_total();

                        if (!isset($summary[$status])) $summary[$status] = 0;
                        $summary[$status] += $qty;
                        $total_revenue += $item_total;

                        if (!isset($payment_summary[$payment_method])) {
                            $payment_summary[$payment_method] = 0;
                        }
                        $payment_summary[$payment_method] += $item_total;

                        $variation = '';
                        foreach ($item->get_meta_data() as $meta) {
                            if (is_string($meta->key) && (strpos(strtolower($meta->key), 'option') !== false || strpos(strtolower($meta->key), 'attribute') !== false)) {
                                $variation .= sanitize_text_field(wp_strip_all_tags($meta->value)) . ' ';
                            }
                        }
                        $variation = trim($variation);
                        if ($variation === '') $variation = 'Standard';

                        if (!isset($variation_summary[$variation])) {
                            $variation_summary[$variation] = 0;
                            $variation_counts[$variation] = 0;
                        }
                        $variation_summary[$variation] += $item_total;
                        $variation_counts[$variation] += $qty;

                        $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        $email = $order->get_billing_email();
                        $date = $date_created ? $date_created->date('Y-m-d H:i') : '';

                        $guest_rows .= "<tr class='{$status_class}'>";
                        $guest_rows .= "<td>{$order->get_id()}</td><td>{$status}</td><td>{$name}</td><td>{$email}</td><td>{$qty}</td><td>{$variation}</td><td>" . wc_price($item_total) . "</td><td>{$date}</td>";
                        $guest_rows .= "</tr>";
                    }
                }
            }

            $total_tickets = array_sum($summary);
            echo "<p><strong>Processing:</strong> {$summary['processing']} &nbsp; <strong>On-Hold:</strong> {$summary['on-hold']} &nbsp; <strong>Completed:</strong> {$summary['completed']} &nbsp; <strong>Total Tickets:</strong> {$total_tickets}</p>";
            echo "<p><strong>Total Revenue:</strong> " . wc_price($total_revenue) . "</p>";
            echo "<p><strong>Revenue by Payment Method:</strong><br>";
            foreach ($payment_summary as $method => $amount) {
                echo esc_html($method) . ': ' . wc_price($amount) . '<br>';
            }
            echo '</p>';

            echo '<h3>Variation Breakdown</h3>';
            echo '<table class="widefat fixed striped">
                    <thead><tr><th>Variation</th><th>Sales Count</th><th>Total Value</th></tr></thead><tbody>';
            foreach ($variation_summary as $variation => $value) {
                $count = $variation_counts[$variation];
                echo '<tr><td>' . esc_html($variation) . '</td><td>' . intval($count) . '</td><td>' . wc_price($value) . '</td></tr>';
            }
            echo '</tbody></table>';

            echo '<form method="post">
                    <input type="hidden" name="egl_export_csv" value="1">
                    <input type="hidden" name="product_id" value="' . esc_attr($product_id) . '">
                    <input type="hidden" name="filter_year" value="' . esc_attr($year_filter) . '">
                    <button type="submit" class="button button-primary">Download Guest List (CSV)</button>
                </form>';

            if (!empty($guest_rows)) {
                echo '<style>
                .egl-status-completed { background-color: #e6ffed; }
                .egl-status-on-hold { background-color: #ffe6e6; }
                .egl-status-processing { background-color: #fff8e1; }
                </style>';
                echo '<h2>Guest List</h2>';
                echo '<table class="widefat fixed striped">
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
                        <tbody>' . $guest_rows . '</tbody>
                      </table>';
            } else {
                echo '<p>No matching guests found for this product and year.</p>';
            }
        endif;
        ?>
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
                    if (is_string($meta->key) && (strpos(strtolower($meta->key), 'option') !== false || strpos(strtolower($meta->key), 'attribute') !== false)) {
                        $variation .= sanitize_text_field(wp_strip_all_tags($meta->value)) . ' ';
                    }
                }
                $variation = trim($variation);
                if ($variation === '') $variation = 'Standard';

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
<?php
} // END function
