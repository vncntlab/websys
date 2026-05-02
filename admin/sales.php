<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$username = $_SESSION['user']['username'];

// Database connection
$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");


// POST HANDLERS

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add sale
    if (isset($_POST['add_sale'])) {
        $product_id      = intval($_POST['product_id']);
        $qty             = intval($_POST['quantity']);
        $selected_addons = $_POST['addon_ids'] ?? [];

        if ($product_id <= 0 || $qty <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid product or quantity.'];
            header("Location: sales.php"); exit();
        }

        // Fetch product details
        $stmt = $conn->prepare("SELECT product_name, price, image FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->bind_result($product_name, $price, $image);
        $stmt->fetch();
        $stmt->close();

        if (!$product_name) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Product not found.'];
            header("Location: sales.php"); exit();
        }

        // Fetch selected add-ons and compute add-on total
        $addon_total = 0;
        $addons_data = [];
        if (!empty($selected_addons)) {
            $ids = implode(',', array_map('intval', $selected_addons));
            $res = $conn->query("SELECT addon_id, addon_name, price FROM addons WHERE addon_id IN ($ids) AND status='Available'");
            while ($a = $res->fetch_assoc()) {
                $addon_total += $a['price'];
                $addons_data[] = $a;
            }
        }

        // Compute total: (base price + addons) * quantity
        $total = ($qty * $price) + ($addon_total * $qty);

        // Insert sale record
        $stmt = $conn->prepare(
            "INSERT INTO sales (product_id, product_name, product_image, quantity, total, status)
             VALUES (?, ?, ?, ?, ?, 'Completed')"
        );
        $stmt->bind_param("issid", $product_id, $product_name, $image, $qty, $total);
        $stmt->execute();
        $sale_id = $stmt->insert_id;
        $stmt->close();

        // Insert add-ons for this sale if any were selected
        if (!empty($addons_data)) {
            $stmt = $conn->prepare(
                "INSERT INTO sale_addons (sale_id, addon_id, addon_name, price) VALUES (?, ?, ?, ?)"
            );
            foreach ($addons_data as $a) {
                $stmt->bind_param("iisd", $sale_id, $a['addon_id'], $a['addon_name'], $a['price']);
                $stmt->execute();
            }
            $stmt->close();
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Sale recorded: {$product_name} x {$qty}"];
        header("Location: sales.php"); exit();
    }

    // Cancel sale (sets status to Cancelled, only if currently Completed)
    if (isset($_POST['cancel_sale'])) {
        $id = intval($_POST['id']);

        $stmt = $conn->prepare("SELECT sales_id FROM sales WHERE sales_id = ? AND status = 'Completed'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($found_id);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            $stmt = $conn->prepare("UPDATE sales SET status = 'Cancelled' WHERE sales_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Order cancelled.'];
        }

        header("Location: sales.php"); exit();
    }

    // Void sale and log the reason
    if (isset($_POST['void_sale'])) {
        $id     = intval($_POST['id']);
        $reason = trim($_POST['reason'] ?? '');

        if ($id <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid sale.'];
            header("Location: sales.php"); exit();
        }

        $stmt = $conn->prepare("UPDATE sales SET status = 'VOID' WHERE sales_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO sales_void (sale_id, reason) VALUES (?, ?)");
        $stmt->bind_param("is", $id, $reason);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Sale voided.'];
        header("Location: sales.php"); exit();
    }

    // Reset all sales history
    if (isset($_POST['reset_sales'])) {
        $conn->query("DELETE FROM sale_addons");
        $conn->query("DELETE FROM sales_void");
        $conn->query("DELETE FROM sales");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'All sales history reset.'];
        header("Location: sales.php"); exit();
    }
}


// GET DATA

// Search and date filters
$search = $_GET['search'] ?? '';
$date   = $_GET['date']   ?? '';
$where  = "1=1";

if (!empty($search)) {
    $safe   = $conn->real_escape_string($search);
    $where .= " AND s.product_name LIKE '%$safe%'";
}
if (!empty($date)) {
    $safe   = $conn->real_escape_string($date);
    $where .= " AND DATE(s.created_at) = '$safe'";
}

// Fetch sales with product category
$sales = $conn->query("
    SELECT s.*, p.category AS product_category
    FROM sales s
    LEFT JOIN products p ON p.product_id = s.product_id
    WHERE $where
    ORDER BY s.created_at DESC
");

// Fetch all sale add-ons grouped by sale_id
$all_addons = [];
$res = $conn->query("SELECT sale_id, addon_name, price FROM sale_addons ORDER BY sale_id");
while ($row = $res->fetch_assoc()) {
    $all_addons[$row['sale_id']][] = $row;
}

// Summary totals (Completed sales only)
$totalSales = $conn->query("
    SELECT SUM(total) as total FROM sales WHERE status = 'Completed'
")->fetch_assoc()['total'] ?? 0;

$totalTransactions = $conn->query("
    SELECT COUNT(*) as c FROM sales WHERE status = 'Completed'
")->fetch_assoc()['c'] ?? 0;

// Products available for the sale entry form
$products = $conn->query(
    "SELECT product_id, product_name, category, price
     FROM products WHERE status = 'Available' ORDER BY category, product_name"
);

// Add-ons available for the sale entry form
$addons_res  = $conn->query(
    "SELECT addon_id, addon_name, price FROM addons WHERE status = 'Available' ORDER BY addon_name"
);
$addons_list = [];
while ($a = $addons_res->fetch_assoc()) $addons_list[] = $a;

// Pull flash message and clear it from session
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales — Al Coffee</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; }

        /* Layout */
        .title-bar { background: #222; color: white; padding: 15px 20px; }
        .container  { display: flex; }
        .sidebar    { width: 220px; background: #111; color: white; min-height: 100vh; padding: 15px; }
        .sidebar a  { color: white; text-decoration: none; display: block; margin: 10px 0; }
        .sidebar a:hover { color: #ccc; }
        .main { flex: 1; padding: 20px; }

        /* Flash messages */
        .flash         { padding: 12px 18px; border-radius: 6px; margin-bottom: 16px; font-weight: 600; font-size: 14px; }
        .flash-success { background: #e6f7ef; color: #1a7f4b; border: 1px solid #b2dfdb; }
        .flash-error   { background: #fff0ef; color: #c0392b; border: 1px solid #ffcdd2; }

        /* Summary cards */
        .summary    { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .card       { background: white; padding: 16px 22px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .card-label { font-size: 12px; color: #888; margin-bottom: 4px; }
        .card-value { font-size: 22px; font-weight: 700; color: #222; }

        /* Sale entry form */
        .sale-form    { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #e0e0e0; }
        .sale-form h3 { margin: 0 0 16px; font-size: 15px; color: #333; }

        /* Entry table */
        .entry-table    { width: 100%; border-collapse: collapse; background: transparent; }
        .entry-table th {
            background: #f5f5f5;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
            white-space: nowrap;
        }
        .entry-table .hint { font-weight: normal; color: #bbb; font-size: 11px; margin-left: 4px; }
        .entry-cell        { padding: 14px; vertical-align: top; text-align: left; border: none; }

        .entry-cell select,
        .entry-cell input[type="number"] {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: #fafafa;
        }
        .entry-cell input[type="number"] { width: 75px; }

        /* Add-on chips */
        .addon-list  { display: flex; flex-wrap: wrap; gap: 6px; }
        .addon-chip input[type="checkbox"] { display: none; }
        .addon-chip label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 13px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 12px;
            color: #555;
            background: #f9f9f9;
            cursor: pointer;
        }
        .addon-chip input[type="checkbox"]:checked + label {
            background: #e6f7ef;
            border-color: #1a7f4b;
            color: #1a7f4b;
            font-weight: 700;
        }
        .addon-price { font-size: 11px; color: #888; }
        .addon-chip input[type="checkbox"]:checked + label .addon-price { color: #1a7f4b; }

        /* Buttons */
        .btn-primary { background: #222; color: white; border: none; padding: 9px 22px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 600; white-space: nowrap; }
        .btn-primary:hover { background: #444; }
        .btn-danger  { background: #e74c3c; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-reset   { background: #c0392b; color: white; border: none; padding: 7px 16px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; font-size: 13px; }

        /* Sales table */
        table.sales-table    { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        table.sales-table th { background: #222; color: white; padding: 11px 10px; font-size: 13px; }
        table.sales-table td { padding: 10px; text-align: center; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 13px; }
        table.sales-table tr:last-child td { border-bottom: none; }

        /* Product cell */
        .product-cell              { display: flex; align-items: center; gap: 12px; text-align: left; }
        .product-thumb             { width: 46px; height: 46px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
        .product-thumb-placeholder { width: 46px; height: 46px; border-radius: 8px; background: #d0d4e8; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .product-name              { font-weight: 600; font-size: 14px; color: #1e2235; }
        .product-category          { font-size: 11px; color: #888; margin-top: 2px; }

        /* Status badges */
        .badge                   { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge::before           { content: ''; width: 7px; height: 7px; border-radius: 50%; }
        .badge-completed         { background: #e6f7ef; color: #1a7f4b; }
        .badge-completed::before { background: #1a7f4b; }
        .badge-void,
        .badge-cancelled         { background: #fff0ef; color: #c0392b; }
        .badge-void::before,
        .badge-cancelled::before { background: #c0392b; }
        .badge-pending           { background: #fff8e6; color: #9a6c00; }
        .badge-pending::before   { background: #e5a000; }

        /* Add-on tags in table */
        .addon-tag { display: inline-block; background: #e6f7ef; color: #1a7f4b; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin: 2px; white-space: nowrap; }
        .no-data   { color: #ccc; font-size: 12px; }
    </style>
</head>
<body>

<div class="title-bar">Welcome <?= htmlspecialchars($username) ?> — Sales Management</div>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>MENU</h2>
        <a href="/login/home_page.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="inventory.php">Inventory</a>
        <a href="sales.php">Sales</a>
        <a href="reports_analysis.php">Reports</a>
        <a href="admin.php">Admin</a>
        <a href="/login/logout.php" style="color: red;">Logout</a>
    </div>

    <div class="main">

        <h1>Sales Management</h1>

        <!-- Summary cards -->
        <div class="summary">
            <div class="card">
                <div class="card-label">Total Sales</div>
                <div class="card-value">&#8369;<?= number_format($totalSales, 2) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Transactions</div>
                <div class="card-value"><?= $totalTransactions ?></div>
            </div>
        </div>

        <!-- Reset all sales history -->
        <form method="POST" onsubmit="return confirm('Reset all sales history? This cannot be undone.');">
            <button type="submit" name="reset_sales" class="btn-reset">Reset Sales History</button>
        </form>

        <!-- New sale entry form -->
        <div class="sale-form">
            <h3>New Sale Entry</h3>
            <form method="POST">
                <table class="entry-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Add-ons <span class="hint">(optional)</span></th>
                            <th>Quantity</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>

                            <!-- Product dropdown grouped by category -->
                            <td class="entry-cell">
                                <select name="product_id" required>
                                    <option value="">— Select Product —</option>
                                    <?php
                                    $currentCategory = '';
                                    while ($p = $products->fetch_assoc()):
                                        if ($p['category'] !== $currentCategory):
                                            if ($currentCategory !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($p['category']) . '">';
                                            $currentCategory = $p['category'];
                                        endif;
                                    ?>
                                        <option value="<?= $p['product_id'] ?>">
                                            <?= htmlspecialchars($p['product_name']) ?> — &#8369;<?= number_format($p['price'], 2) ?>
                                        </option>
                                    <?php endwhile; if ($currentCategory !== '') echo '</optgroup>'; ?>
                                </select>
                            </td>

                            <!-- Add-on checkboxes -->
                            <td class="entry-cell">
                                <?php if (!empty($addons_list)): ?>
                                    <div class="addon-list">
                                        <?php foreach ($addons_list as $a): ?>
                                            <div class="addon-chip">
                                                <input type="checkbox"
                                                       name="addon_ids[]"
                                                       value="<?= $a['addon_id'] ?>"
                                                       id="addon_<?= $a['addon_id'] ?>">
                                                <label for="addon_<?= $a['addon_id'] ?>">
                                                    <?= htmlspecialchars($a['addon_name']) ?>
                                                    <span class="addon-price">+&#8369;<?= number_format($a['price'], 2) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-data">No add-ons available</span>
                                <?php endif; ?>
                            </td>

                            <!-- Quantity input -->
                            <td class="entry-cell">
                                <input type="number" name="quantity" min="1" value="1" required>
                            </td>

                            <!-- Submit -->
                            <td class="entry-cell">
                                <button type="submit" name="add_sale" class="btn-primary">Add Sale</button>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Sales list -->
        <h2>Sales List</h2>

        <table class="sales-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th style="text-align: left; padding-left: 16px;">Product</th>
                    <th>Add-ons</th>
                    <th>Unit Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $sales->fetch_assoc()):
                $status     = strtolower($row['status']);
                $badgeClass = match($status) {
                    'completed'             => 'badge-completed',
                    'void'                  => 'badge-void',
                    'cancelled', 'canceled' => 'badge-cancelled',
                    default                 => 'badge-pending',
                };
                $imageFile   = $row['product_image'] ?? '';
                $sale_addons = $all_addons[$row['sales_id']] ?? [];
            ?>
                <tr>
                    <!-- Sale ID -->
                    <td><?= $row['sales_id'] ?></td>

                    <!-- Product name and image -->
                    <td style="text-align: left; padding-left: 16px;">
                        <div class="product-cell">
                            <?php if (!empty($imageFile)): ?>
                                <img src="uploads/<?= htmlspecialchars($imageFile) ?>"
                                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                                     class="product-thumb">
                            <?php else: ?>
                                <div class="product-thumb-placeholder">&#9749;</div>
                            <?php endif; ?>
                            <div>
                                <div class="product-name"><?= htmlspecialchars($row['product_name']) ?></div>
                                <?php if (!empty($row['product_category'])): ?>
                                    <div class="product-category"><?= htmlspecialchars($row['product_category']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Add-ons for this sale -->
                    <td>
                        <?php if (!empty($sale_addons)): ?>
                            <?php foreach ($sale_addons as $sa): ?>
                                <span class="addon-tag">
                                    <?= htmlspecialchars($sa['addon_name']) ?> +&#8369;<?= number_format($sa['price'], 2) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="no-data">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Unit price (total divided by quantity) -->
                    <td>&#8369;<?= number_format($row['total'] / max($row['quantity'], 1), 2) ?></td>

                    <!-- Quantity -->
                    <td><?= $row['quantity'] ?></td>

                    <!-- Total -->
                    <td>&#8369;<?= number_format($row['total'], 2) ?></td>

                    <!-- Date -->
                    <td><?= $row['created_at'] ?></td>

                    <!-- Status badge -->
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </td>

                    <!-- Cancel action (only available for Completed sales) -->
                    <td>
                        <?php if ($row['status'] === 'Completed'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $row['sales_id'] ?>">
                                <button type="submit" name="cancel_sale" class="btn-danger">Cancel</button>
                            </form>
                        <?php else: ?>
                            <span class="no-data">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>

</body>
</html>