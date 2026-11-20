<?php
include 'db.php';
session_start();

// INSECURE: No login check — anyone can access checkout
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$product_id = $_POST["product_id"] ?? $_GET["product_id"] ?? null;
$quantity = $_POST["quantity"] ?? 1;

if (!$product_id) {
    die("No product selected.");
}

// INSECURE: Raw SQL
$sql = "SELECT * FROM products WHERE id = '$product_id'";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    die("Product not found.");
}

$total_price = $product["price"] * $quantity;
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["confirm"])) {
    // INSECURE: No CSRF token, no stock check
    $sql_order = "INSERT INTO orders (user_id, total_price, status) 
                  VALUES ('$user_id', '$total_price', 'pending')";
    mysqli_query($conn, $sql_order);
    $order_id = mysqli_insert_id($conn);

    $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                 VALUES ('$order_id', '$product_id', '$quantity', '$total_price')";
    mysqli_query($conn, $sql_item);

    $success = "Order placed successfully! Your order ID is #" . $order_id;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout – ShopEasy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">🛒 ShopEasy</div>
    <div class="nav-links">
        <span>Hello, <strong><?= $_SESSION["username"] ?></strong></span>
        <a href="orders.php">My Orders</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="auth-container">
    <h2>Checkout</h2>

    <?php if ($success): ?>
        <div class="alert success"><?= $success ?></div>
        <a href="index.php" class="btn">Continue Shopping</a>
        <a href="orders.php" class="btn">View My Orders</a>
    <?php else: ?>
        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Product:</strong> <?= $product["name"] ?></p>
            <p><strong>Quantity:</strong> <?= $quantity ?></p>
            <p><strong>Total:</strong> RM <?= number_format($total_price, 2) ?></p>
        </div>

        <!-- INSECURE: No CSRF token in form -->
        <form method="POST" action="">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            <input type="hidden" name="quantity" value="<?= $quantity ?>">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn">Confirm Order</button>
            <a href="index.php" class="btn btn-outline">Cancel</a>
        </form>
    <?php endif; ?>
</div>

</body>
</html>