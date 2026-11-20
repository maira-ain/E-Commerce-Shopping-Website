<?php
include 'db.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShopEasy – Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="nav-brand">🛒 ShopEasy</div>
    <div class="nav-links">
        <?php if (isset($_SESSION["username"])): ?>
            <span>Hello, <strong><?= $_SESSION["username"] ?></strong></span>
            <a href="orders.php">My Orders</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero">
    <h1>Welcome to ShopEasy</h1>
    <p>Find the best tech products at great prices</p>
</div>

<!-- Search Bar — INSECURE: reflects input directly (XSS) -->
<div class="search-container">
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Search products..." 
               value="<?= $_GET['search'] ?? '' ?>">
        <button type="submit" class="btn">Search</button>
    </form>
</div>

<!-- Search Results Message — INSECURE: no sanitization -->
<?php if (isset($_GET['search']) && $_GET['search'] !== ''): ?>
    <p class="search-info">Showing results for: <?= $_GET['search'] ?></p>
<?php endif; ?>

<!-- Products Grid -->
<div class="products-grid">
<?php
$search = $_GET['search'] ?? '';

// INSECURE: Raw SQL with user input — vulnerable to SQL Injection
$sql = "SELECT * FROM products WHERE name LIKE '%$search%' OR description LIKE '%$search%'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while ($product = mysqli_fetch_assoc($result)) {
        echo '
        <div class="product-card">
            <div class="product-img">🖥️</div>
            <div class="product-info">
                <h3>' . $product["name"] . '</h3>
                <p>' . $product["description"] . '</p>
                <div class="product-footer">
                    <span class="price">RM ' . number_format($product["price"], 2) . '</span>
                    <a href="product.php?id=' . $product["id"] . '" class="btn">View</a>
                </div>
            </div>
        </div>';
    }
} else {
    echo '<p class="no-results">No products found.</p>';
}
?>
</div>

</body>
</html>