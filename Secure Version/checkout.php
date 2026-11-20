<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();
require_login();

$user_id = (int) $_SESSION['user_id'];
$product_id = positive_int($_POST['product_id'] ?? $_GET['product_id'] ?? null, 0);
$quantity = positive_int($_POST['quantity'] ?? 1, 1);
$success = '';
$error = '';
$order_id = 0;

if ($product_id < 1) {
    die('No valid product selected.');
}

$productSql = 'SELECT id, name, price, stock FROM products WHERE id = ? LIMIT 1';
$productStmt = mysqli_prepare($conn, $productSql);
mysqli_stmt_bind_param($productStmt, 'i', $product_id);
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);
$product = mysqli_fetch_assoc($productResult);
mysqli_stmt_close($productStmt);

if (!$product) {
    die('Product not found.');
}

if ($quantity > (int) $product['stock']) {
    $quantity = (int) $product['stock'];
}

if ($quantity < 1) {
    die('This product is out of stock.');
}

$total_price = (float) $product['price'] * $quantity;

$shipping_address = trim($_POST['shipping_address'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif ($shipping_address === '') {
        $error = 'Please enter a shipping address.';
    } elseif (mb_strlen($shipping_address) > 500) {
        $error = 'Shipping address is too long.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $lockSql = 'SELECT id, name, price, stock FROM products WHERE id = ? LIMIT 1 FOR UPDATE';
            $lockStmt = mysqli_prepare($conn, $lockSql);
            mysqli_stmt_bind_param($lockStmt, 'i', $product_id);
            mysqli_stmt_execute($lockStmt);
            $lockedResult = mysqli_stmt_get_result($lockStmt);
            $lockedProduct = mysqli_fetch_assoc($lockedResult);
            mysqli_stmt_close($lockStmt);

            if (!$lockedProduct || (int) $lockedProduct['stock'] < $quantity) {
                throw new Exception('Insufficient stock available.');
            }

            $finalTotal = (float) $lockedProduct['price'] * $quantity;

            // Generate unique order reference
            do {
                $order_ref = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $refCheck = mysqli_prepare($conn, 'SELECT id FROM orders WHERE order_ref = ? LIMIT 1');
                mysqli_stmt_bind_param($refCheck, 's', $order_ref);
                mysqli_stmt_execute($refCheck);
                mysqli_stmt_store_result($refCheck);
                $refExists = mysqli_stmt_num_rows($refCheck) > 0;
                mysqli_stmt_close($refCheck);
            } while ($refExists);

            $orderSql = 'INSERT INTO orders (order_ref, user_id, total_price, status, shipping_address) VALUES (?, ?, ?, ?, ?)';
            $status = 'pending';
            $orderStmt = mysqli_prepare($conn, $orderSql);
            mysqli_stmt_bind_param($orderStmt, 'sidss', $order_ref, $user_id, $finalTotal, $status, $shipping_address);
            mysqli_stmt_execute($orderStmt);
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($orderStmt);

            $itemSql = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
            $itemStmt = mysqli_prepare($conn, $itemSql);
            mysqli_stmt_bind_param($itemStmt, 'iiid', $order_id, $product_id, $quantity, $finalTotal);
            mysqli_stmt_execute($itemStmt);
            mysqli_stmt_close($itemStmt);

            $stockSql = 'UPDATE products SET stock = stock - ? WHERE id = ?';
            $stockStmt = mysqli_prepare($conn, $stockSql);
            mysqli_stmt_bind_param($stockStmt, 'ii', $quantity, $product_id);
            mysqli_stmt_execute($stockStmt);
            mysqli_stmt_close($stockStmt);

            mysqli_commit($conn);
            $success = 'Order placed successfully! Your order reference is ' . $order_ref;
            $product['stock'] = (int) $lockedProduct['stock'] - $quantity;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout – ShopEasy Secure</title>
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
    <h2>Checkout</h2>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
        <a href="index.php" class="btn">Continue Shopping</a>
        <a href="orders.php?ref=<?= e($order_ref) ?>" class="btn">View Order</a>
    <?php else: ?>
        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Product:</strong> <?= e($product['name']) ?></p>
            <p><strong>Quantity:</strong> <?= $quantity ?></p>
            <p><strong>Total:</strong> RM <?= number_format($total_price, 2) ?></p>
            <p><strong>Stock Available:</strong> <?= (int) $product['stock'] ?></p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            <input type="hidden" name="quantity" value="<?= $quantity ?>">
            <input type="hidden" name="confirm" value="1">
            <div class="form-group">
                <label>Shipping Address</label>
                <textarea name="shipping_address" rows="3" maxlength="500" placeholder="Enter your full shipping address..." required><?= e($shipping_address) ?></textarea>
            </div>
            <button type="submit" class="btn">Confirm Order</button>
            <a href="product.php?id=<?= $product_id ?>" class="btn btn-outline">Cancel</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
