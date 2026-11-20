<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();
require_login();

$user_id = (int) $_SESSION['user_id'];
$order_ref = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['ref'] ?? ''));

if ($order_ref !== '') {
    $orderSql = 'SELECT id, order_ref, user_id, total_price, status, shipping_address, created_at FROM orders WHERE order_ref = ? AND user_id = ? LIMIT 1';
    $orderStmt = mysqli_prepare($conn, $orderSql);
    mysqli_stmt_bind_param($orderStmt, 'si', $order_ref, $user_id);
    mysqli_stmt_execute($orderStmt);
    $orderResult = mysqli_stmt_get_result($orderStmt);
    $order = mysqli_fetch_assoc($orderResult);
    mysqli_stmt_close($orderStmt);

    $items = [];
    if ($order) {
        $itemsSql = 'SELECT order_items.quantity, order_items.price, products.name
                     FROM order_items
                     JOIN products ON order_items.product_id = products.id
                     WHERE order_items.order_id = ?';
        $itemsStmt = mysqli_prepare($conn, $itemsSql);
        mysqli_stmt_bind_param($itemsStmt, 'i', $order['id']);
        mysqli_stmt_execute($itemsStmt);
        $itemsResult = mysqli_stmt_get_result($itemsStmt);

        while ($row = mysqli_fetch_assoc($itemsResult)) {
            $items[] = $row;
        }

        mysqli_stmt_close($itemsStmt);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= e($order['order_ref'] ?? 'Order') ?> – ShopEasy Secure</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <nav class="navbar">
        <a href="index.php" class="nav-brand">🛒 ShopEasy Secure</a>
        <div class="nav-links">
            <span>Hello, <strong><?= e($_SESSION['username']) ?></strong></span>
            <a href="orders.php">My Orders</a>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="admin.php" style="color:#e94560;font-weight:700;">⚙️ Admin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <div class="auth-container">
        <h2>Order Details</h2>

        <?php if ($order): ?>
            <div class="order-summary">
                <p><strong>Order Ref:</strong> <?= e($order['order_ref']) ?></p>
                <p><strong>Status:</strong> <?= e($order['status']) ?></p>
                <p><strong>Total:</strong> RM <?= number_format((float) $order['total_price'], 2) ?></p>
                <p><strong>Shipping Address:</strong> <?= e($order['shipping_address']) ?></p>
                <p><strong>Date:</strong> <?= e($order['created_at']) ?></p>
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
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td>RM <?= number_format((float) $item['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert error">Order not found or access denied.</div>
        <?php endif; ?>

        <a href="orders.php" class="btn" style="margin-top:20px;">Back to My Orders</a>
    </div>
    </body>
    </html>
    <?php
    exit();
}

$orders = [];
$listSql = 'SELECT order_ref, total_price, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC';
$listStmt = mysqli_prepare($conn, $listSql);
mysqli_stmt_bind_param($listStmt, 'i', $user_id);
mysqli_stmt_execute($listStmt);
$listResult = mysqli_stmt_get_result($listStmt);

while ($row = mysqli_fetch_assoc($listResult)) {
    $orders[] = $row;
}

mysqli_stmt_close($listStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders – ShopEasy Secure</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="nav-brand">🛒 ShopEasy Secure</a>
    <div class="nav-links">
        <span>Hello, <strong><?= e($_SESSION['username']) ?></strong></span>
        <a href="orders.php">My Orders</a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="admin.php" style="color:#e94560;font-weight:700;">⚙️ Admin</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="page-container">
    <h2>My Orders</h2>

    <?php if (!empty($orders)): ?>
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
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['order_ref']) ?></td>
                        <td>RM <?= number_format((float) $order['total_price'], 2) ?></td>
                        <td><?= e($order['status']) ?></td>
                        <td><?= e($order['created_at']) ?></td>
                        <td><a href="orders.php?ref=<?= e($order['order_ref']) ?>" class="btn">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have no orders yet. <a href="index.php">Start shopping!</a></p>
    <?php endif; ?>
</div>
</body>
</html>
