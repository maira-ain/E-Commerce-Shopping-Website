<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();

$search = trim($_GET['search'] ?? '');
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$products = [];
$likeSearch = '%' . $search . '%';
$sql = 'SELECT id, name, description, price FROM products WHERE name LIKE ? OR description LIKE ?';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ss', $likeSearch, $likeSearch);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShopEasy Secure – Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="nav-brand">🛒 ShopEasy Secure</a>
    <div class="nav-links">
        <?php if (isset($_SESSION['username'])): ?>
            <span>Hello, <strong><?= e($_SESSION['username']) ?></strong></span>
            <a href="orders.php">My Orders</a>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="admin.php" style="color:#e94560;font-weight:700;">⚙️ Admin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="hero">
    <h1>Welcome to ShopEasy Secure</h1>
    <p>Find the best tech products at great prices</p>
</div>

<div class="search-container">
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Search products..." value="<?= e($search) ?>" maxlength="100">
        <button type="submit" class="btn">Search</button>
    </form>
</div>

<?php if ($search !== ''): ?>
    <p class="search-info">Showing results for: <?= e($search) ?></p>
<?php endif; ?>

<div class="products-grid">
    <?php if (!empty($products)): ?>
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-img">🖥️</div>
                <div class="product-info">
                    <h3><?= e($product['name']) ?></h3>
                    <p><?= e($product['description']) ?></p>
                    <div class="product-footer">
                        <span class="price">RM <?= number_format((float) $product['price'], 2) ?></span>
                        <a href="product.php?id=<?= (int) $product['id'] ?>" class="btn">View</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-results">No products found.</p>
    <?php endif; ?>
</div>
</body>
</html>
