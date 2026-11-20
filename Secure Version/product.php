<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();

$product_id = positive_int($_GET['id'] ?? null, 0);
if ($product_id < 1) {
    die('Invalid product selected.');
}

$productSql = 'SELECT id, name, description, price, stock FROM products WHERE id = ? LIMIT 1';
$productStmt = mysqli_prepare($conn, $productSql);
mysqli_stmt_bind_param($productStmt, 'i', $product_id);
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);
$product = mysqli_fetch_assoc($productResult);
mysqli_stmt_close($productStmt);

if (!$product) {
    die('Product not found.');
}

$review_success = '';
$review_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review'])) {
    require_login();

    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $review_error = 'Invalid request. Please try again.';
    } else {
        $review = trim($_POST['review']);
        if ($review === '') {
            $review_error = 'Review cannot be empty.';
        } elseif (mb_strlen($review) > 500) {
            $review_error = 'Review must be 500 characters or fewer.';
        } else {
            $user_id = (int) $_SESSION['user_id'];
            $reviewSql = 'INSERT INTO reviews (product_id, user_id, review) VALUES (?, ?, ?)';
            $reviewStmt = mysqli_prepare($conn, $reviewSql);
            mysqli_stmt_bind_param($reviewStmt, 'iis', $product_id, $user_id, $review);

            if (mysqli_stmt_execute($reviewStmt)) {
                $review_success = 'Review submitted successfully.';
            } else {
                $review_error = 'Unable to submit review right now.';
            }

            mysqli_stmt_close($reviewStmt);
        }
    }
}

$reviews = [];
$reviewsSql = 'SELECT reviews.review, users.username
               FROM reviews
               JOIN users ON reviews.user_id = users.id
               WHERE reviews.product_id = ?
               ORDER BY reviews.id DESC';
$reviewsStmt = mysqli_prepare($conn, $reviewsSql);
mysqli_stmt_bind_param($reviewsStmt, 'i', $product_id);
mysqli_stmt_execute($reviewsStmt);
$reviewsResult = mysqli_stmt_get_result($reviewsStmt);

while ($row = mysqli_fetch_assoc($reviewsResult)) {
    $reviews[] = $row;
}

mysqli_stmt_close($reviewsStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($product['name']) ?> – ShopEasy Secure</title>
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

<div class="product-detail-container">
    <div class="product-detail-img">🖥️</div>
    <div class="product-detail-info">
        <h1><?= e($product['name']) ?></h1>
        <p><?= e($product['description']) ?></p>
        <p class="price">RM <?= number_format((float) $product['price'], 2) ?></p>
        <p>Stock: <?= (int) $product['stock'] ?> units</p>

        <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST" action="checkout.php">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" required>
                </div>
                <button type="submit" class="btn">Buy Now</button>
            </form>
        <?php else: ?>
            <p><a href="login.php" class="btn">Login to Purchase</a></p>
        <?php endif; ?>
    </div>
</div>

<div class="reviews-container">
    <h2>Customer Reviews</h2>

    <?php if ($review_success): ?>
        <div class="alert success"><?= e($review_success) ?></div>
    <?php endif; ?>

    <?php if ($review_error): ?>
        <div class="alert error"><?= e($review_error) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
            <div class="form-group">
                <label>Leave a Review</label>
                <textarea name="review" rows="3" maxlength="500" placeholder="Write your review here..." required></textarea>
            </div>
            <button type="submit" class="btn">Submit Review</button>
        </form>
    <?php else: ?>
        <p><a href="login.php">Login</a> to leave a review.</p>
    <?php endif; ?>

    <div class="reviews-list">
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $row): ?>
                <div class="review-card">
                    <strong><?= e($row['username']) ?></strong>
                    <p><?= nl2br(e($row['review'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No reviews yet. Be the first!</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
