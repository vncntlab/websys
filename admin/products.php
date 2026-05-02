<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");

// Generate CSRF token if not yet set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// Validate CSRF token for all POST requests
function validateCsrf() {
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

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($_FILES['image']['type'], $allowed_types)) {
        die("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
    }

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("File upload failed.");
    }

    $filename = time() . "_" . basename($_FILES['image']['name']);
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

// Add new product
if (isset($_POST['add_product'])) {
    validateCsrf();

    $name     = trim($_POST['name']);
    $price    = floatval($_POST['price']);
    $category = $_POST['category'];
    $status   = $_POST['status'];
    $image    = handleUpload();

    $stmt = $conn->prepare(
        "INSERT INTO products (product_name, price, category, status, image)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sdsss", $name, $price, $category, $status, $image);
    $stmt->execute();
    $stmt->close();

    header("Location: products.php");
    exit();
}

// Delete product
if (isset($_POST['delete'])) {
    validateCsrf();

    $id = intval($_POST['id']);

    // Fetch old image before deleting
    $stmt = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        deleteImage($result['image']);
    }

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: products.php");
    exit();
}

// Fetch product data for edit form
$editProduct = null;

if (isset($_POST['edit'])) {
    validateCsrf();

    $id = intval($_POST['id']);

    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editProduct = $result->fetch_assoc();
    $stmt->close();
}

// Save edited product
if (isset($_POST['save_edit'])) {
    validateCsrf();

    $id       = intval($_POST['id']);
    $name     = trim($_POST['name']);
    $price    = floatval($_POST['price']);
    $category = $_POST['category'];
    $status   = $_POST['status'];

    // Check if a new image was uploaded
    $newImage = handleUpload();

    if (!empty($newImage)) {
        // Delete old image before replacing
        $stmt = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        deleteImage($old['image']);

        // Update with new image
        $stmt = $conn->prepare(
            "UPDATE products
             SET product_name = ?, price = ?, category = ?, status = ?, image = ?
             WHERE product_id = ?"
        );
        $stmt->bind_param("sdsssi", $name, $price, $category, $status, $newImage, $id);
    } else {
        // Update without changing image
        $stmt = $conn->prepare(
            "UPDATE products
             SET product_name = ?, price = ?, category = ?, status = ?
             WHERE product_id = ?"
        );
        $stmt->bind_param("sdssi", $name, $price, $category, $status, $id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: products.php");
    exit();
}

// Get search and filter values
$search           = trim($_GET['search']   ?? '');
$selectedCategory = trim($_GET['category'] ?? '');
$selectedStatus   = trim($_GET['status']   ?? '');

// Build query using prepared statement with dynamic filters
$conditions = [];
$params     = [];
$types      = "";

if (!empty($search)) {
    $conditions[] = "product_name LIKE ?";
    $params[]     = "%" . $search . "%";
    $types       .= "s";
}

if (!empty($selectedCategory)) {
    $conditions[] = "category = ?";
    $params[]     = $selectedCategory;
    $types       .= "s";
}

if (!empty($selectedStatus)) {
    $conditions[] = "status = ?";
    $params[]     = $selectedStatus;
    $types       .= "s";
}

$sql = "SELECT * FROM products";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$products = $stmt->get_result();

if (!$products) {
    die("Query failed: " . $conn->error);
}

$stmt->close();

// Category and status options (reused in multiple places)
$categories = ["Hot Coffee", "Iced Coffee", "Matcha Series", "Non-Coffee", "Snacks", "Add Ons"];
$statuses   = ["Available", "Unavailable"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Management</title>
    <link rel="stylesheet" href="../resources/main_css.css">

    <style>
        img {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }
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
        <ul>
            <li><a href="/login/home_page.php">Dashboard</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="inventory.php">Inventory</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="reports_analysis.php">Reports</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li>
                <a href="/login/logout.php"
                   style="color:red;"
                   onclick="return confirm('Are you sure you want to log out?')">
                    Logout
                </a>
            </li>
        </ul>
    </div>

    <div class="main">

        <h1>PRODUCT REGISTRY</h1>

        <!-- Add product form -->
        <h2>Add Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <input type="text" name="name" placeholder="Product Name" required>
            <input type="number" step="0.01" name="price" placeholder="Price" required>

            <select name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <button name="add_product">Add Product</button>
        </form>

        <!-- Search and filter form -->
        <h2>Search & Filter</h2>
        <form method="GET">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search...">

            <select name="category">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                        <?= $selectedCategory === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">Select Status</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"
                        <?= $selectedStatus === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button>Apply</button>
            <a href="products.php">Clear</a>
        </form>

        <!-- Edit product form, shown only when a product is selected for editing -->
        <?php if ($editProduct): ?>
        <h2>Edit Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= intval($editProduct['product_id']) ?>">

            <input type="text" name="name"
                   value="<?= htmlspecialchars($editProduct['product_name']) ?>" required>
            <input type="number" step="0.01" name="price"
                   value="<?= htmlspecialchars($editProduct['price']) ?>" required>

            <select name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                        <?= $editProduct['category'] === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"
                        <?= $editProduct['status'] === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <button name="save_edit">Save Changes</button>
        </form>
        <?php endif; ?>

        <!-- Product list table -->
        <h2>Product List</h2>
        <table border="1" width="100%">
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Price</th>
                <th>Category</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = $products->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php if (!empty($row['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($row['image']) ?>"
                             alt="<?= htmlspecialchars($row['product_name']) ?>">
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td>₱<?= number_format($row['price'], 2) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>

                <td>
                    <?= $row['status'] === "Available"
                        ? '<span style="color:green; font-weight:bold;">Available</span>'
                        : '<span style="color:red; font-weight:bold;">Unavailable</span>' ?>
                </td>

                <td>
                    <!-- Edit button -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id" value="<?= intval($row['product_id']) ?>">
                        <button name="edit">Edit</button>
                    </form>

                    <!-- Delete button with confirmation -->
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Delete this product?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id" value="<?= intval($row['product_id']) ?>">
                        <button name="delete">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

    </div>
</div>

</body>
</html>