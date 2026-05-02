<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("Connection failed");

// Generate CSRF token if not yet set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// Validate CSRF token for all POST requests
function validateCsrf(): void {
    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die("Invalid CSRF token.");
    }
}

// Handle image upload, returns filename on success or empty string
function handleUpload(): string {
    if (empty($_FILES['image']['name'])) {
        return "";
    }

    // Check upload error
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("File upload failed.");
    }

    // Validate file size (max 2MB)
    $max_size = 2 * 1024 * 1024;
    if ($_FILES['image']['size'] > $max_size) {
        die("File too large. Max size is 2MB.");
    }

    // Validate actual MIME type using finfo (not spoofable unlike $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['image']['tmp_name']);

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_types)) {
        die("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
    }

    // Sanitize filename
    $filename    = time() . "_" . preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($_FILES['image']['name']));
    $destination = "uploads/" . $filename;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
        die("Failed to save uploaded file.");
    }

    return $filename;
}

// Delete image file from uploads folder
function deleteImage(string $image): void {
    $path = "uploads/" . $image;
    if (!empty($image) && file_exists($path)) {
        unlink($path);
    }
}

// Add new ingredient
if (isset($_POST['add_ingredient'])) {
    validateCsrf();

    $name  = trim($_POST['ingredient_name']);
    $stock = intval($_POST['stock']);
    $low   = intval($_POST['low_stock_threshold']);
    $unit  = $_POST['unit'];

    // Validate stock and threshold values
    if ($stock < 0 || $low < 0) {
        die("Stock and threshold values must not be negative.");
    }

    $image = handleUpload();

    $stmt = $conn->prepare("
        INSERT INTO ingredients (ingredient_name, stock, low_stock_threshold, unit, image)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("siiss", $name, $stock, $low, $unit, $image);
    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['type' => 'added', 'name' => $name];
    header("Location: inventory.php");
    exit();
}

// Delete an ingredient
if (isset($_POST['delete_ing'])) {
    validateCsrf();

    $delId = intval($_POST['id']);

    // Fetch ingredient name and image before deleting
    $stmt = $conn->prepare("SELECT ingredient_name, image FROM ingredients WHERE ingredient_id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $row     = $stmt->get_result()->fetch_assoc();
    $delName = $row ? $row['ingredient_name'] : 'Ingredient';
    $stmt->close();

    // Delete image file from server
    if ($row && !empty($row['image'])) {
        deleteImage($row['image']);
    }

    $stmt = $conn->prepare("DELETE FROM ingredients WHERE ingredient_id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['type' => 'deleted', 'name' => $delName];
    header("Location: inventory.php");
    exit();
}

// Load ingredient data for edit form
$editIng = null;

if (isset($_POST['edit_ing'])) {
    validateCsrf();

    $editId = intval($_POST['id']);

    $stmt = $conn->prepare("SELECT * FROM ingredients WHERE ingredient_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editIng = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Update an existing ingredient
if (isset($_POST['update_ing'])) {
    validateCsrf();

    $id    = intval($_POST['id']);
    $name  = trim($_POST['ingredient_name']);
    $stock = intval($_POST['stock']);
    $low   = intval($_POST['low_stock_threshold']);
    $unit  = $_POST['unit'];

    // Validate stock and threshold values
    if ($stock < 0 || $low < 0) {
        die("Stock and threshold values must not be negative.");
    }

    $newImage = handleUpload();

    if (!empty($newImage)) {
        // Fetch and delete old image before replacing
        $stmt = $conn->prepare("SELECT image FROM ingredients WHERE ingredient_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($old && !empty($old['image'])) {
            deleteImage($old['image']);
        }

        // Update with new image using a proper prepared statement
        $stmt = $conn->prepare("
            UPDATE ingredients
            SET ingredient_name = ?, stock = ?, low_stock_threshold = ?, unit = ?, image = ?
            WHERE ingredient_id = ?
        ");
        $stmt->bind_param("siissi", $name, $stock, $low, $unit, $newImage, $id);
    } else {
        // Update without changing image
        $stmt = $conn->prepare("
            UPDATE ingredients
            SET ingredient_name = ?, stock = ?, low_stock_threshold = ?, unit = ?
            WHERE ingredient_id = ?
        ");
        $stmt->bind_param("siisi", $name, $stock, $low, $unit, $id);
    }

    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['type' => 'updated', 'name' => $name];
    header("Location: inventory.php");
    exit();
}

// Get filter values from GET params
$search     = trim($_GET['search']      ?? '');
$stockLevel = trim($_GET['stock_level'] ?? '');
$category   = trim($_GET['category']    ?? '');

// Build dynamic WHERE clause for ingredient search/filter
$conditions = [];
$ingParams  = [];
$ingTypes   = "";

if (!empty($search)) {
    $conditions[] = "ingredient_name LIKE ?";
    $ingParams[]  = "%" . $search . "%";
    $ingTypes    .= "s";
}

if (!empty($category)) {
    $conditions[] = "unit = ?";
    $ingParams[]  = $category;
    $ingTypes    .= "s";
}

if ($stockLevel === 'low') {
    $conditions[] = "stock <= low_stock_threshold";
} elseif ($stockLevel === 'mid') {
    $conditions[] = "stock > low_stock_threshold AND stock <= low_stock_threshold * 3";
} elseif ($stockLevel === 'high') {
    $conditions[] = "stock > low_stock_threshold * 3";
}

$sql = "SELECT * FROM ingredients";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Fetch filtered ingredients
$ingStmt = $conn->prepare($sql);
if (!empty($ingParams)) {
    $ingStmt->bind_param($ingTypes, ...$ingParams);
}
$ingStmt->execute();
$ingredients = $ingStmt->get_result();

if (!$ingredients) {
    die("Query failed: " . $conn->error);
}

$ingStmt->close();

// Fetch low stock alerts
$lowStockAlerts = [];

$lowIngStmt = $conn->prepare("
    SELECT ingredient_name AS label, stock, low_stock_threshold 
    FROM ingredients 
    WHERE stock <= low_stock_threshold 
    ORDER BY ingredient_name
");
$lowIngStmt->execute();
$lowIngResult = $lowIngStmt->get_result();
$lowIngStmt->close();

while ($r = $lowIngResult->fetch_assoc()) {
    $lowStockAlerts[] = $r;
}

// Unit/category options
$unitOptions = ["Can-based", "Bottle-based", "Box-based", "Plastic-based", "Pack-based"];

// Read and clear session notification
$notif = null;
if (!empty($_SESSION['notif'])) {
    $notif = $_SESSION['notif'];
    unset($_SESSION['notif']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory</title>

    <link rel="stylesheet" href="../resources/main_css.css">
    <link rel="stylesheet" href="../resources/homepages.css">

    <style>
        input, select {
            padding: 5px;
            margin: 5px;
        }

        button {
            padding: 5px 10px;
            margin: 3px;
        }

        .sidebar-logout {
            color: red;
        }

        /* Low stock alert banner */
        #low-stock-alert {
            position: relative;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 5px solid #e65100;
            border-radius: 6px;
            padding: 14px 40px 14px 16px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        #low-stock-alert .alert-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 15px;
            color: #7a3a00;
            margin-bottom: 8px;
        }

        #low-stock-alert .alert-header .bell-icon {
            font-size: 18px;
            animation: ring 1s ease 0.5s 2;
            display: inline-block;
        }

        @keyframes ring {
            0%, 100% { transform: rotate(0); }
            20%       { transform: rotate(-20deg); }
            40%       { transform: rotate(20deg); }
            60%       { transform: rotate(-10deg); }
            80%       { transform: rotate(10deg); }
        }

        #low-stock-alert .alert-items {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        #low-stock-alert .alert-tag {
            background: #b71c1c;
            color: #fff;
            border-radius: 12px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        #low-stock-alert .alert-count {
            font-size: 13px;
            color: #7a3a00;
            margin-top: 6px;
        }

        #low-stock-alert .alert-count a {
            color: #7a3a00;
            font-weight: 600;
        }

        /* Badge shown on sidebar nav link */
        .alert-nav-badge {
            display: inline-block;
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            margin-left: 4px;
            vertical-align: middle;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .filter-bar input[type="text"] {
            padding: 7px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            min-width: 180px;
        }

        .filter-bar select {
            padding: 7px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .filter-search-btn {
            padding: 7px 16px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-clear-btn {
            padding: 7px 14px;
            background: #eee;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        /* Ingredient card list */
        .ingredient-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        .ingredient-card {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 16px;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .ingredient-card img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
            flex-shrink: 0;
        }

        .img-placeholder {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .card-info          { flex: 1; min-width: 0; }
        .card-name          { font-weight: 700; font-size: 15px; margin-bottom: 4px; color: #1a1a1a; }
        .card-stock-label   { font-size: 12px; color: #888; margin-bottom: 4px; }

        /* Stock progress bar */
        .stock-bar-wrap {
            background: #eee;
            border-radius: 6px;
            height: 6px;
            width: 100%;
            margin-bottom: 5px;
            overflow: hidden;
        }

        .stock-bar-fill { height: 6px; border-radius: 6px; }
        .bar-low        { background: #e53935; }
        .bar-mid        { background: #fb8c00; }
        .bar-high       { background: #43a047; }

        .card-stock-count {
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-stock-count.low  { color: #e53935; }
        .card-stock-count.mid  { color: #fb8c00; }
        .card-stock-count.high { color: #43a047; }

        .card-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        .card-limit { font-size: 12px; color: #888; }

        .card-unit {
            font-size: 11px;
            color: #aaa;
            background: #f4f4f4;
            border-radius: 8px;
            padding: 2px 8px;
        }

        .card-actions { display: flex; gap: 5px; }

        .btn-edit {
            padding: 4px 12px;
            background: #1565c0;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-delete {
            padding: 4px 12px;
            background: #c62828;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .form-inline { display: inline; }

        /* Toast notification container */
        #notif-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 340px;
            pointer-events: none;
        }

        .notif-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 40px 14px 16px;
            border-radius: 10px;
            border: 1.5px solid transparent;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            position: relative;
            font-size: 14px;
            pointer-events: all;
            animation: notifSlideIn 0.4s cubic-bezier(.4,0,.2,1) forwards;
        }

        @keyframes notifSlideIn {
            from { opacity: 0; transform: translateX(80px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* Notification color variants */
        .notif-deleted              { background: #fff0f0; border-color: #f5c2c2; }
        .notif-deleted .notif-icon  { color: #e53935; }
        .notif-deleted .notif-title { color: #c62828; }

        .notif-added,
        .notif-updated                  { background: #f0fff4; border-color: #a8e6b8; }
        .notif-added .notif-icon,
        .notif-updated .notif-icon      { color: #2e7d32; }
        .notif-added .notif-title,
        .notif-updated .notif-title     { color: #1b5e20; }

        .notif-lowstock                 { background: #fffbea; border-color: #ffe08a; }
        .notif-lowstock .notif-icon     { color: #f59e0b; }
        .notif-lowstock .notif-title    { color: #b45309; }

        .notif-icon  { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
        .notif-body  { flex: 1; }
        .notif-title { font-weight: 700; font-size: 13.5px; margin-bottom: 3px; }
        .notif-msg   { color: #444; font-size: 13px; line-height: 1.4; }

        .notif-close-link {
            position: absolute;
            top: 10px;
            right: 12px;
            font-size: 16px;
            line-height: 1;
            color: #999;
            text-decoration: none;
            font-weight: 700;
        }

        .notif-close-link:hover { color: #333; }

        /* Staggered animation delay per notification card */
        .notif-card:nth-child(1) { animation-delay: 0.00s; }
        .notif-card:nth-child(2) { animation-delay: 0.10s; }
        .notif-card:nth-child(3) { animation-delay: 0.20s; }
        .notif-card:nth-child(4) { animation-delay: 0.30s; }
        .notif-card:nth-child(5) { animation-delay: 0.40s; }
    </style>
</head>

<body>

<div class="title-bar">
    Al Coffee's Sales and Inventory Management System
</div>

<div class="container">

    <!-- Sidebar navigation -->
    <div class="sidebar">
        <h2>MENU</h2>
        <a href="/login/home_page.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="inventory.php">
            Inventory
            <?php if (count($lowStockAlerts) > 0): ?>
                <span class="alert-nav-badge"><?= count($lowStockAlerts) ?></span>
            <?php endif; ?>
        </a>
        <a href="sales.php">Sales</a>
        <a href="reports_analysis.php">Reports</a>
        <a href="admin.php">Admin</a>
        <a href="/login/logout.php" class="sidebar-logout"
           onclick="return confirm('Are you sure you want to log out?')">Logout</a>
    </div>

    <div class="main">

        <h1>INVENTORY</h1>

        <!-- Low stock alert banner -->
        <?php if (!empty($lowStockAlerts)): ?>
        <div id="low-stock-alert">
            <div class="alert-header">
                <span class="bell-icon">🔔</span>
                Low Stock Alert —
                <?= count($lowStockAlerts) ?> ingredient<?= count($lowStockAlerts) > 1 ? 's' : '' ?>
                need<?= count($lowStockAlerts) === 1 ? 's' : '' ?> restocking
            </div>
            <div class="alert-items">
                <?php foreach ($lowStockAlerts as $alert): ?>
                    <span class="alert-tag"
                          title="Stock: <?= intval($alert['stock']) ?> / Threshold: <?= intval($alert['low_stock_threshold']) ?>">
                        🧪 <?= htmlspecialchars($alert['label']) ?> (<?= intval($alert['stock']) ?>)
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="alert-count">
                <a href="inventory.php?stock_level=low">View all low stock →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and filter bar -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="🔍 Search ingredient...">

            <select name="category">
                <option value="">Select Category</option>
                <?php foreach ($unitOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"
                        <?= $category === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="stock_level">
                <option value="">Select Stock Level</option>
                <option value="low"  <?= $stockLevel === 'low'  ? 'selected' : '' ?>>🔴 Low</option>
                <option value="mid"  <?= $stockLevel === 'mid'  ? 'selected' : '' ?>>🟠 Mid</option>
                <option value="high" <?= $stockLevel === 'high' ? 'selected' : '' ?>>🟢 High</option>
            </select>

            <button type="submit" class="filter-search-btn">Search</button>
            <a href="inventory.php" class="filter-clear-btn">Clear</a>
        </form>

        <h2>Ingredients Inventory</h2>

        <!-- Add ingredient form -->
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <input type="text"   name="ingredient_name"    placeholder="Name"          required>
            <input type="number" name="stock"               placeholder="Stock"   min="0" required>
            <input type="number" name="low_stock_threshold" placeholder="Low Threshold" min="0" required>

            <select name="unit">
                <?php foreach ($unitOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <button name="add_ingredient">Add</button>
        </form>

        <!-- Edit ingredient form (shown only when edit is triggered) -->
        <?php if ($editIng): ?>
        <h3>Edit Ingredient</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= intval($editIng['ingredient_id']) ?>">

            <input type="text"   name="ingredient_name"    value="<?= htmlspecialchars($editIng['ingredient_name']) ?>" required>
            <input type="number" name="stock"               value="<?= intval($editIng['stock']) ?>"               min="0" required>
            <input type="number" name="low_stock_threshold" value="<?= intval($editIng['low_stock_threshold']) ?>" min="0" required>

            <select name="unit">
                <?php foreach ($unitOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"
                        <?= $editIng['unit'] === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <button name="update_ing">Save</button>
            <a href="inventory.php"><button type="button">Cancel</button></a>
        </form>
        <?php endif; ?>

        <!-- Ingredient cards list -->
        <div class="ingredient-grid">
            <?php while ($row = $ingredients->fetch_assoc()):

                // Determine stock level class and icon
                if ($row['stock'] <= $row['low_stock_threshold']) {
                    $cls      = 'low';
                    $barClass = 'bar-low';
                    $icon     = '⚠️';
                } elseif ($row['stock'] <= $row['low_stock_threshold'] * 3) {
                    $cls      = 'mid';
                    $barClass = 'bar-mid';
                    $icon     = '⚠️';
                } else {
                    $cls      = 'high';
                    $barClass = 'bar-high';
                    $icon     = '✅';
                }

                // Calculate stock bar fill percentage
                $maxRef = max($row['low_stock_threshold'] * 3, 1);
                $pct    = min(100, round(($row['stock'] / $maxRef) * 100));
            ?>

            <div class="ingredient-card">

                <!-- Ingredient image or placeholder -->
                <?php if (!empty($row['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($row['image']) ?>"
                         alt="<?= htmlspecialchars($row['ingredient_name']) ?>">
                <?php else: ?>
                    <div class="img-placeholder">📦</div>
                <?php endif; ?>

                <div class="card-info">
                    <div class="card-name"><?= htmlspecialchars($row['ingredient_name']) ?></div>
                    <div class="card-stock-label">Stock Level:</div>
                    <div class="stock-bar-wrap">
                        <div class="stock-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="card-stock-count <?= $cls ?>">
                        <?= $icon ?> <?= intval($row['stock']) ?> LEFT
                    </div>
                </div>

                <div class="card-right">
                    <div class="card-limit">Limit: <?= intval($row['low_stock_threshold']) ?></div>
                    <div class="card-unit"><?= htmlspecialchars($row['unit']) ?></div>
                    <div class="card-actions">

                        <!-- Edit button -->
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id" value="<?= intval($row['ingredient_id']) ?>">
                            <button name="edit_ing" class="btn-edit">Edit</button>
                        </form>

                        <!-- Delete button with confirmation -->
                        <form method="POST" class="form-inline"
                              onsubmit="return confirm('Delete ingredient?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id" value="<?= intval($row['ingredient_id']) ?>">
                            <button name="delete_ing" class="btn-delete">Delete</button>
                        </form>

                    </div>
                </div>

            </div>
            <?php endwhile; ?>
        </div>

    </div>
</div>

<!-- Toast notifications -->
<?php $hasNotifs = ($notif !== null) || !empty($lowStockAlerts); ?>
<?php if ($hasNotifs): ?>
<div id="notif-container">

    <!-- Action notification (added, updated, deleted) -->
    <?php if ($notif !== null): ?>
        <?php if ($notif['type'] === 'deleted'): ?>
        <div class="notif-card notif-deleted">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Deleted Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully removed.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>

        <?php elseif ($notif['type'] === 'added'): ?>
        <div class="notif-card notif-added">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Added Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully added.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>

        <?php elseif ($notif['type'] === 'updated'): ?>
        <div class="notif-card notif-updated">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Updated Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully updated.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Low stock notifications per ingredient -->
    <?php foreach ($lowStockAlerts as $alert): ?>
    <div class="notif-card notif-lowstock">
        <span class="notif-icon">ℹ️</span>
        <div class="notif-body">
            <div class="notif-title">Low Stock Alert!</div>
            <div class="notif-msg">
                <?= htmlspecialchars($alert['label']) ?> is running low.
                Only <strong><?= intval($alert['stock']) ?> units remaining.</strong>
            </div>
        </div>
        <a href="inventory.php?stock_level=low" class="notif-close-link">✕</a>
    </div>
    <?php endforeach; ?>

</div>
<?php endif; ?>

</body>
</html>