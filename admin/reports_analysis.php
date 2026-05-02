<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$username = $_SESSION['user']['username'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");


// DATE FILTER

$filter = $_GET['filter'] ?? 'daily';

if ($filter === 'daily') {
    $dateCondition = "DATE(created_at) = CURDATE()";
} elseif ($filter === 'weekly') {
    $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'monthly') {
    $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} else {
    $dateCondition = "1=1";
}


// FETCH SALES DATA

// Summary totals based on selected filter
$salesData = $conn->query("
    SELECT SUM(total) as total_sales, COUNT(*) as total_transactions
    FROM sales WHERE status='Completed' AND $dateCondition
")->fetch_assoc();
$totalSales        = $salesData['total_sales']        ?? 0;
$totalTransactions = $salesData['total_transactions'] ?? 0;

// Fixed stat strip values
$weeklySales = $conn->query("
    SELECT SUM(total) as total FROM sales
    WHERE status='Completed'
    AND created_at >= CURDATE() - INTERVAL 6 DAY
    AND created_at < CURDATE() + INTERVAL 1 DAY
")->fetch_assoc()['total'] ?? 0;

$monthlySales = $conn->query("
    SELECT SUM(total) as total FROM sales
    WHERE status='Completed'
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at)  = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

$todayOrders = $conn->query("
    SELECT COUNT(*) as c FROM sales
    WHERE status='Completed' AND DATE(created_at) = CURDATE()
")->fetch_assoc()['c'] ?? 0;

// Sales transaction rows for the current filter (used in Excel export)
$salesRows = [];
$res = $conn->query("
    SELECT product_name, quantity, total, created_at, status
    FROM sales WHERE status='Completed' AND $dateCondition
    ORDER BY created_at DESC
");
while ($row = $res->fetch_assoc()) $salesRows[] = $row;


// TOP SELLING PRODUCTS (daily / weekly / monthly)

$topDaily = [];
$res = $conn->query("
    SELECT s.product_id, s.product_name,
           SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.product_id = s.product_id
    WHERE s.status='Completed' AND DATE(s.created_at) = CURDATE()
    GROUP BY s.product_id, s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topDaily[] = $row;

$topWeekly = [];
$res = $conn->query("
    SELECT s.product_id, s.product_name,
           SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.product_id = s.product_id
    WHERE s.status='Completed'
    AND s.created_at >= CURDATE() - INTERVAL 6 DAY
    AND s.created_at < CURDATE() + INTERVAL 1 DAY
    GROUP BY s.product_id, s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topWeekly[] = $row;

$topMonthly = [];
$res = $conn->query("
    SELECT s.product_id, s.product_name,
           SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.product_id = s.product_id
    WHERE s.status='Completed'
    AND MONTH(s.created_at) = MONTH(CURDATE())
    AND YEAR(s.created_at)  = YEAR(CURDATE())
    GROUP BY s.product_id, s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topMonthly[] = $row;


// BEST SELLING CATEGORY

$categoryRows = [];
$res = $conn->query("
    SELECT p.category, SUM(s.quantity) as total_quantity, SUM(s.total) as total_sales
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    WHERE s.status='Completed'
    GROUP BY p.category
    ORDER BY total_sales DESC
");
while ($row = $res->fetch_assoc()) $categoryRows[] = $row;


// INVENTORY STATUS

$allIngredients = [];
$res = $conn->query("SELECT * FROM ingredients ORDER BY stock ASC");
while ($row = $res->fetch_assoc()) $allIngredients[] = $row;

// Build unit category list for filter dropdown
$allUnitCats = [];
foreach ($allIngredients as $i) {
    $unit = ucfirst($i['unit'] ?? 'Other') . '-based';
    if (!in_array($unit, $allUnitCats)) $allUnitCats[] = $unit;
}

// Inventory filters from GET
$invStatus   = $_GET['inv_status']   ?? '';
$invCategory = $_GET['inv_category'] ?? '';

// Filter ingredients by status and/or category
$filteredIngredients = [];
foreach ($allIngredients as $i) {
    $thr = $i['low_stock_threshold'] ?? 5;

    if ($i['stock'] <= $thr)         $cls = 'badge-bad';
    elseif ($i['stock'] <= $thr * 3) $cls = 'badge-mid';
    else                             $cls = 'badge-good';

    $unitLabel = ucfirst($i['unit'] ?? 'Other') . '-based';

    if ($invStatus   !== '' && $cls       !== $invStatus)   continue;
    if ($invCategory !== '' && $unitLabel !== $invCategory) continue;

    $i['_cls']       = $cls;
    $i['_unitLabel'] = $unitLabel;
    $filteredIngredients[] = $i;
}


// EXCEL EXPORT

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filterLabel   = ucfirst($filter);
    $dateGenerated = date('Y-m-d H:i:s');

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Report_' . $filterLabel . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel"
               xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" style="border-collapse:collapse;font-family:Arial;font-size:13px;">';
    echo '<tr><td colspan="5" style="background:#222;color:white;font-size:15px;font-weight:bold;padding:10px;">Al Coffee\'s Sales and Inventory Management System</td></tr>';
    echo '<tr><td colspan="5" style="padding:6px;">Report Period: <b>' . $filterLabel . '</b> &nbsp; Generated: ' . $dateGenerated . '</td></tr>';
    echo '<tr><td colspan="5"></td></tr>';

    echo '<tr><td colspan="5" style="background:#444;color:white;font-weight:bold;padding:8px;">SALES SUMMARY</td></tr>';
    echo '<tr><td style="background:#ddd;font-weight:bold;">Total Sales</td><td style="background:#ddd;font-weight:bold;">Total Transactions</td><td colspan="3"></td></tr>';
    echo '<tr><td>&#8369;' . number_format($totalSales, 2) . '</td><td>' . $totalTransactions . '</td><td colspan="3"></td></tr>';
    echo '<tr><td colspan="5"></td></tr>';

    echo '<tr><td colspan="5" style="background:#444;color:white;font-weight:bold;padding:8px;">SALES TRANSACTIONS (' . $filterLabel . ')</td></tr>';
    echo '<tr><td style="background:#ddd;font-weight:bold;">Product</td><td style="background:#ddd;font-weight:bold;">Qty</td><td style="background:#ddd;font-weight:bold;">Total</td><td style="background:#ddd;font-weight:bold;">Date</td><td style="background:#ddd;font-weight:bold;">Status</td></tr>';
    if (empty($salesRows)) {
        echo '<tr><td colspan="5" style="color:gray;">No transactions for this period.</td></tr>';
    } else {
        foreach ($salesRows as $row) {
            echo '<tr>'
               . '<td>' . htmlspecialchars($row['product_name']) . '</td>'
               . '<td>' . $row['quantity'] . '</td>'
               . '<td>&#8369;' . number_format($row['total'], 2) . '</td>'
               . '<td>' . $row['created_at'] . '</td>'
               . '<td>' . $row['status'] . '</td>'
               . '</tr>';
        }
    }
    echo '</table></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports & Analysis — Al Coffee</title>
<style>
body { margin: 0; font-family: Arial; background: #f4f4f4; }

/* Title bar */
.title-bar {
    background: #222;
    color: white;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.title-bar .filter-links a {
    color: white;
    text-decoration: none;
    font-size: 13px;
    padding: 5px 12px;
    border: 1px solid #555;
    border-radius: 4px;
    margin-left: 6px;
}
.title-bar .filter-links a.active-filter {
    background: white;
    color: #222;
    border-color: white;
    font-weight: bold;
}
.title-bar .filter-links a.btn-excel {
    background: #1d6f42;
    border-color: #1d6f42;
    color: white;
    font-weight: bold;
}
.title-bar .filter-links a.btn-excel:hover { background: #155233; }

/* Layout */
.container { display: flex; }
.sidebar   { width: 220px; background: #111; color: white; min-height: 100vh; padding: 15px; }
.sidebar a { display: block; color: white; margin: 10px 0; text-decoration: none; }
.sidebar a:hover { color: #ccc; }
.main { flex: 1; padding: 20px; }

/* Hero */
.hero        { text-align: center; padding: 20px 0 10px; border-bottom: 2px solid #ddd; margin-bottom: 16px; }
.hero-label  { font-size: 12px; color: #888; letter-spacing: 2px; text-transform: uppercase; }
.hero-amount { font-size: 46px; font-weight: bold; color: #111; margin: 4px 0 0; }

/* Stat strip */
.stat-strip              { display: flex; border-top: 2px solid #222; border-bottom: 2px solid #222; background: white; margin-bottom: 24px; }
.stat-strip .stat-item   { flex: 1; padding: 14px 18px; border-right: 1px solid #ddd; }
.stat-strip .stat-item:last-child { border-right: none; }
.stat-item .s-label      { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; }
.stat-item .s-value      { font-size: 22px; font-weight: bold; color: #111; margin-top: 2px; }

/* Section titles */
.section-title   { font-size: 15px; font-weight: bold; border-left: 4px solid #222; padding-left: 8px; margin: 20px 0 10px; }
.section-title a { float: right; font-size: 12px; font-weight: normal; color: #888; text-decoration: none; }
.period-heading  { font-size: 11px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #555; background: #eee; padding: 6px 10px; margin-top: 16px; }

/* Tables */
table { width: 100%; background: white; border-collapse: collapse; margin-bottom: 4px; }
th    { background: #222; color: white; padding: 10px; font-size: 13px; }
td    { padding: 10px; border-bottom: 1px solid #ddd; text-align: center; font-size: 13px; }
tr:last-child td { border-bottom: none; }

.prod-img-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; vertical-align: middle; margin-right: 8px; }

/* Stock text colors */
.stock-bad  { color: #e53935; font-weight: bold; }
.stock-mid  { color: #fb8c00; font-weight: bold; }
.stock-good { color: #43a047; font-weight: bold; }

/* Inventory filter bar */
.inv-filters           { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
.inv-filters select    { padding: 7px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; background: white; cursor: pointer; }
.inv-filters select:hover { border-color: #222; }
.inv-submit-btn        { background: #222; color: white; border: none; border-radius: 6px; padding: 7px 14px; font-size: 13px; font-weight: bold; cursor: pointer; }
.inv-submit-btn:hover  { background: #444; }
.inv-clear-btn         { background: white; color: #888; border: 1px solid #ddd; border-radius: 6px; padding: 7px 14px; font-size: 13px; cursor: pointer; text-decoration: none; }
.inv-clear-btn:hover   { border-color: #222; color: #222; }

/* Active filter tags */
.filter-active-tag   { font-size: 12px; background: #222; color: white; border-radius: 20px; padding: 3px 10px; display: inline-flex; align-items: center; gap: 6px; }
.filter-active-tag a { color: #aaa; text-decoration: none; font-weight: bold; }
.filter-active-tag a:hover { color: white; }

/* Stock status badges */
.badge-bad  { background: #fef2f2; color: #e53935; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 20px; }
.badge-mid  { background: #fff8f0; color: #fb8c00; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 20px; }
.badge-good { background: #f1f8f1; color: #43a047; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 20px; }

.no-data { color: #aaa; font-size: 13px; padding: 12px 0; display: block; }
</style>
</head>
<body>

<!-- TITLE BAR + FILTER LINKS -->
<div class="title-bar">
    <span>Al Coffee — Reports &amp; Analysis &nbsp;|&nbsp; Welcome <?= htmlspecialchars($username) ?></span>
    <div class="filter-links">
        <a href="?filter=daily"    class="<?= $filter === 'daily'   ? 'active-filter' : '' ?>">Daily</a>
        <a href="?filter=weekly"   class="<?= $filter === 'weekly'  ? 'active-filter' : '' ?>">Weekly</a>
        <a href="?filter=monthly"  class="<?= $filter === 'monthly' ? 'active-filter' : '' ?>">Monthly</a>
        <a href="reports_analysis.php" class="<?= !in_array($filter, ['daily','weekly','monthly']) ? 'active-filter' : '' ?>">All</a>
        <a href="?filter=<?= $filter ?>&export=excel" class="btn-excel">&#11015; Excel</a>
    </div>
</div>

<div class="container">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2>MENU</h2>
        <a href="/login/home_page.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="inventory.php">Inventory</a>
        <a href="sales.php">Sales</a>
        <a href="reports_analysis.php">Reports</a>
        <a href="admin.php">Admin</a>
        <a href="/login/logout.php" style="color:red;"
           onclick="return confirm('Are you sure you want to log out?')">Logout</a>
    </div>

    <div class="main">

        <!-- HERO -->
        <div class="hero">
            <div class="hero-label"><?= ucfirst($filter) ?>'s Sales</div>
            <div class="hero-amount">&#8369;<?= number_format($totalSales, 0) ?></div>
        </div>

        <!-- STAT STRIP -->
        <div class="stat-strip">
            <div class="stat-item">
                <div class="s-label">This Week's Sales</div>
                <div class="s-value">&#8369;<?= number_format($weeklySales, 0) ?></div>
            </div>
            <div class="stat-item">
                <div class="s-label">This Month's Sales</div>
                <div class="s-value">&#8369;<?= number_format($monthlySales, 0) ?></div>
            </div>
            <div class="stat-item">
                <div class="s-label">Today's Orders</div>
                <div class="s-value"><?= $todayOrders ?></div>
            </div>
            <div class="stat-item">
                <div class="s-label">Total Transactions</div>
                <div class="s-value"><?= $totalTransactions ?></div>
            </div>
        </div>

        <!-- BEST SELLING CATEGORY -->
        <div class="section-title">Best Selling Category</div>
        <table>
            <tr>
                <th style="width:60px;">Rank</th>
                <th style="text-align:left;">Category</th>
                <th>Quantity</th>
                <th>Total Sales</th>
            </tr>
            <?php $rank = 1; foreach ($categoryRows as $row): ?>
            <tr>
                <td style="font-weight:bold; color:#888;">#<?= $rank++ ?></td>
                <td style="text-align:left;"><?= htmlspecialchars($row['category']) ?></td>
                <td><?= number_format($row['total_quantity']) ?></td>
                <td>&#8369;<?= number_format($row['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- TOP SELLING PRODUCTS -->
        <div class="section-title" style="margin-top:28px;">Top Selling Products</div>

        <?php
        // Reusable helper to render a top products table
        $renderTopTable = function(array $products, string $emptyMsg) {
        ?>
            <?php if (empty($products)): ?>
                <span class="no-data"><?= $emptyMsg ?></span>
            <?php else: ?>
                <table>
                    <tr>
                        <th style="text-align:left;">Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Sold</th>
                        <th>Revenue</th>
                    </tr>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="text-align:left;">
                            <?php if (!empty($p['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="prod-img-thumb">
                            <?php endif; ?>
                            <?= htmlspecialchars($p['product_name']) ?>
                        </td>
                        <td><?= htmlspecialchars($p['category'] ?? '—') ?></td>
                        <td>&#8369;<?= number_format($p['price'] ?? 0, 2) ?></td>
                        <td><?= $p['qty_sold'] ?></td>
                        <td>&#8369;<?= number_format($p['revenue'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif;
        };
        ?>

        <div class="period-heading">Daily</div>
        <?php $renderTopTable($topDaily, 'No data for today.'); ?>

        <div class="period-heading">Weekly</div>
        <?php $renderTopTable($topWeekly, 'No data this week.'); ?>

        <div class="period-heading">Monthly</div>
        <?php $renderTopTable($topMonthly, 'No data this month.'); ?>

        <!-- INVENTORY STATUS -->
        <div class="section-title" style="margin-top:28px;">
            Inventory Status
            <a href="inventory.php">Manage All</a>
        </div>

        <!-- Inventory filter form -->
        <form method="GET" action="reports_analysis.php" style="margin:0;">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <div class="inv-filters">
                <select name="inv_status">
                    <option value="">Filter Status</option>
                    <option value="badge-bad"  <?= $invStatus === 'badge-bad'  ? 'selected' : '' ?>>⚠ Low</option>
                    <option value="badge-mid"  <?= $invStatus === 'badge-mid'  ? 'selected' : '' ?>>⚠ Medium</option>
                    <option value="badge-good" <?= $invStatus === 'badge-good' ? 'selected' : '' ?>>✓ Good</option>
                </select>
                <select name="inv_category">
                    <option value="">All Categories</option>
                    <?php foreach ($allUnitCats as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $invCategory === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="inv-submit-btn">Apply Filter</button>
                <?php if ($invStatus !== '' || $invCategory !== ''): ?>
                    <a href="?filter=<?= htmlspecialchars($filter) ?>" class="inv-clear-btn">✕ Clear</a>
                <?php endif; ?>
            </div>

            <!-- Active filter tags -->
            <?php if ($invStatus !== '' || $invCategory !== ''): ?>
            <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($invStatus !== ''):
                    $statusLabel = ['badge-bad' => '⚠ Low', 'badge-mid' => '⚠ Medium', 'badge-good' => '✓ Good'][$invStatus] ?? $invStatus;
                ?>
                <span class="filter-active-tag">
                    Status: <?= htmlspecialchars($statusLabel) ?>
                    <a href="?filter=<?= htmlspecialchars($filter) ?>&inv_category=<?= urlencode($invCategory) ?>">×</a>
                </span>
                <?php endif; ?>
                <?php if ($invCategory !== ''): ?>
                <span class="filter-active-tag">
                    Category: <?= htmlspecialchars($invCategory) ?>
                    <a href="?filter=<?= htmlspecialchars($filter) ?>&inv_status=<?= urlencode($invStatus) ?>">×</a>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>

        <!-- Inventory table -->
        <?php if (empty($filteredIngredients)): ?>
            <span class="no-data">No ingredients match the selected filters.</span>
        <?php else: ?>
        <table>
            <tr>
                <th style="text-align:left;">Ingredient</th>
                <th>Unit</th>
                <th>Stock</th>
                <th>Limit</th>
                <th>Status</th>
            </tr>
            <?php foreach ($filteredIngredients as $i):
                $cls = $i['_cls'];
                $thr = $i['low_stock_threshold'] ?? 5;

                if ($cls === 'badge-bad')     { $icon = '⚠️'; $label = 'Low';  $sc = 'stock-bad'; }
                elseif ($cls === 'badge-mid') { $icon = '⚠️'; $label = 'Mid';  $sc = 'stock-mid'; }
                else                         { $icon = '✅'; $label = 'Good'; $sc = 'stock-good'; }
            ?>
            <tr>
                <td style="text-align:left;">
                    <?php if (!empty($i['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($i['image']) ?>"
                             style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;">
                    <?php endif; ?>
                    <?= htmlspecialchars($i['ingredient_name']) ?>
                </td>
                <td><?= htmlspecialchars($i['unit'] ?? '') ?></td>
                <td><?= $i['stock'] ?></td>
                <td><?= $thr ?></td>
                <td><span class="<?= $sc ?>"><?= $icon ?> <?= $label ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

    </div>
</div>

</body>
</html>