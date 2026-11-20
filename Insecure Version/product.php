<?php
include 'db.php';
session_start();

$product_id = $_GET['id'] ?? 1;

// INSECURE: Raw SQL with user input
$sql = "SELECT * FROM products WHERE id = '$product_id'";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    die("Product not found.");
}

// Handle review submission
$review_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["review"])) {
    if (!isset($_SESSION["user_id"])) {
        $review_error = "You must be logged in to leave a review.";
    } else {
        $user_id = $_SESSION["user_id"];
        $review = $_POST["review"];

        // INSECURE: Raw SQL + no sanitization = Stored XSS
        $sql_review = "INSERT INTO reviews (product_id, user_id, review) 
                       VALUES ('$product_id', '$user_id', '$review')";
        mysqli_query($conn, $sql_review);
        $review_success = "Review submitted!";
    }
}

// Fetch all reviews for this product
$sql_reviews = "SELECT reviews.review, users.username 
                FROM reviews 
                JOIN users ON reviews.user_id = users.id 
                WHERE reviews.product_id = '$product_id'";
$reviews_result = mysqli_query($conn, $sql_reviews);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $product["name"] ?> – ShopEasy</title>
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

<!-- Product Details -->
<div class="product-detail-container">
    <div class="product-detail-img">🖥️</div>
    <div class="product-detail-info">
        <h1><?= $product["name"] ?></h1>
        <p><?= $product["description"] ?></p>
        <p class="price">RM <?= number_format($product["price"], 2) ?></p>
        <p>Stock: <?= $product["stock"] ?> units</p>

        <?php if (isset($_SESSION["user_id"])): ?>
            <form method="POST" action="checkout.php">
                <input type="hidden" name="product_id" value="<?= $product["id"] ?>">
                <input type="hidden" name="price" value="<?= $product["price"] ?>">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product["stock"] ?>">
                </div>
                <button type="submit" class="btn">Buy Now</button>
            </form>
        <?php else: ?>
            <p><a href="login.php" class="btn">Login to Purchase</a></p>
        <?php endif; ?>
    </div>
</div>

<!-- Reviews Section -->
<div class="reviews-container">
    <h2>Customer Reviews</h2>

    <?php if ($review_success): ?>
        <div class="alert success"><?= $review_success ?></div>
    <?php endif; ?>
    <?php if (isset($review_error)): ?>
        <div class="alert error"><?= $review_error ?></div>
    <?php endif; ?>

    <!-- Review Form -->
    <?php if (isset($_SESSION["user_id"])): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Leave a Review</label>
                <!-- INSECURE: No input restrictions -->
                <textarea name="review" rows="3" placeholder="Write your review here..."></textarea>
            </div>
            <button type="submit" class="btn">Submit Review</button>
        </form>
    <?php else: ?>
        <p><a href="login.php">Login</a> to leave a review.</p>
    <?php endif; ?>

    <!-- Display Reviews — INSECURE: prints raw HTML from DB (Stored XSS) -->
    <div class="reviews-list">
        <?php if (mysqli_num_rows($reviews_result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($reviews_result)): ?>
                <div class="review-card">
                    <strong><?= $row["username"] ?></strong>
                    <p><?= $row["review"] ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No reviews yet. Be the first!</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>