<?php
include 'db.php';
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// IDOR VULNERABILITY — user can change ?id= to see anyone's order
if (isset($_GET["id"])) {
    $order_id = $_GET["id"];

    // INSECURE: No check if this order belongs to the logged-in user
    $sql = "SELECT orders.*, users.username 
            FROM orders 
            JOIN users ON orders.user_id = users.id 
            WHERE orders.id = '$order_id'";
    $result = mysqli_query($conn, $sql);
    $order = mysqli_fetch_assoc($result);

    // Fetch order items
    $sql_items = "SELECT order_items.*, products.name 
                  FROM order_items 
                  JOIN products ON order_items.product_id = products.id 
                  WHERE order_items.order_id = '$order_id'";
    $items_result = mysqli_query($conn, $sql_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order #<?= $order_id ?> – ShopEasy</title>
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
    <h2>Order Details</h2>

    <?php if ($order): ?>
        <!-- INSECURE: Shows ANY user's order, not just the logged-in user's -->
        <div class="order-summary">
            <p><strong>Order ID:</strong> #<?= $order["id"] ?></p>
            <p><strong>Ordered by:</strong> <?= $order["username"] ?></p>
            <p><strong>Status:</strong> <?= $order["status"] ?></p>
            <p><strong>Total:</strong> RM <?= number_format($order["total_price"], 2) ?></p>
            <p><strong>Date:</strong> <?= $order["created_at"] ?></p>
        </div>

        <h3>Items</h3>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                <tr>
                    <td><?= $item["name"] ?></td>
                    <td><?= $item["quantity"] ?></td>
                    <td>RM <?= number_format($item["price"], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="orders.php" class="btn" style="margin-top:20px;">Back to My Orders</a>

    <?php else: ?>
        <div class="alert error">Order not found.</div>
    <?php endif; ?>
</div>

</body>
</html>

<?php
} else {
    // Show all orders — INSECURE: shows logged-in user's orders with visible IDs in URL
    $user_id = $_SESSION["user_id"];
    $sql = "SELECT * FROM orders WHERE user_id = '$user_id' ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders – ShopEasy</title>
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

<div class="page-container">
    <h2>My Orders</h2>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td>#<?= $order["id"] ?></td>
                    <td>RM <?= number_format($order["total_price"], 2) ?></td>
                    <td><?= $order["status"] ?></td>
                    <td><?= $order["created_at"] ?></td>
                    <!-- INSECURE: Order ID visible in URL — easy to enumerate -->
                    <td><a href="orders.php?id=<?= $order["id"] ?>" class="btn">View</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have no orders yet. <a href="index.php">Start shopping!</a></p>
    <?php endif; ?>
</div>

</body>
</html>

<?php } ?>