<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();
require_admin();

$success = '';
$error = '';
$tab = $_GET['tab'] ?? 'products';

// ─── PRODUCT ACTIONS ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ADD PRODUCT
        if ($action === 'add_product') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $stock       = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

            if ($name === '' || $price === false || $stock === false || $price < 0 || $stock < 0) {
                $error = 'Please fill in all fields with valid values.';
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssdi', $name, $description, $price, $stock);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = "Product \"$name\" added successfully.";
            }
        }

        // EDIT PRODUCT
        if ($action === 'edit_product') {
            $id          = positive_int($_POST['id'] ?? null, 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $stock       = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

            if ($id < 1 || $name === '' || $price === false || $stock === false || $price < 0 || $stock < 0) {
                $error = 'Invalid product data.';
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE products SET name=?, description=?, price=?, stock=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'ssdii', $name, $description, $price, $stock, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = "Product updated successfully.";
            }
        }

        // DELETE PRODUCT
        if ($action === 'delete_product') {
            $id = positive_int($_POST['id'] ?? null, 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($conn, 'DELETE FROM products WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = 'Product deleted successfully.';
            }
        }

        // UPDATE ORDER STATUS
        if ($action === 'update_order') {
            $id     = positive_int($_POST['id'] ?? null, 0);
            $status = trim($_POST['status'] ?? '');
            $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if ($id > 0 && in_array($status, $allowed, true)) {
                $stmt = mysqli_prepare($conn, 'UPDATE orders SET status=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'si', $status, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = "Order #$id status updated to \"$status\".";
                $tab = 'orders';
            }
        }

        // DELETE ORDER
        if ($action === 'delete_order') {
            $id = positive_int($_POST['id'] ?? null, 0);
            if ($id > 0) {
                mysqli_prepare($conn, 'DELETE FROM order_items WHERE order_id=?') && (function() use ($conn, $id) {
                    $s = mysqli_prepare($conn, 'DELETE FROM order_items WHERE order_id=?');
                    mysqli_stmt_bind_param($s, 'i', $id);
                    mysqli_stmt_execute($s);
                    mysqli_stmt_close($s);
                })();
                $stmt = mysqli_prepare($conn, 'DELETE FROM orders WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = "Order #$id deleted.";
                $tab = 'orders';
            }
        }
    }
}

// ─── FETCH DATA ──────────────────────────────────────────────────────────────
// Products
$products = [];
$pResult = mysqli_query($conn, 'SELECT id, name, description, price, stock FROM products ORDER BY id DESC');
while ($row = mysqli_fetch_assoc($pResult)) { $products[] = $row; }

// Orders with user info
$orders = [];
$oResult = mysqli_query($conn, '
    SELECT orders.id, orders.order_ref, orders.total_price, orders.status, orders.shipping_address, orders.created_at,
           users.username
    FROM orders
    JOIN users ON orders.user_id = users.id
    ORDER BY orders.created_at DESC
');
while ($row = mysqli_fetch_assoc($oResult)) { $orders[] = $row; }

// Editing a product?
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = positive_int($_GET['edit'], 0);
    foreach ($products as $p) {
        if ((int)$p['id'] === $editId) { $editProduct = $p; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel – ShopEasy Secure</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f0f2f5; }

        .admin-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .admin-header h1 {
            font-size: 1.8rem;
            color: #1a1a2e;
        }

        .admin-header h1 span {
            color: #e94560;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            background: #dde0e7;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
            width: fit-content;
        }

        .tab-btn {
            padding: 10px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            background: transparent;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
        }

        .tab-btn.active, .tab-btn:hover {
            background: white;
            color: #e94560;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }

        .card h2 {
            font-size: 1.2rem;
            color: #1a1a2e;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Form grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        /* Table */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .admin-table th {
            background: #1a1a2e;
            color: white;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
        }

        .admin-table th:first-child { border-radius: 8px 0 0 0; }
        .admin-table th:last-child  { border-radius: 0 8px 0 0; }

        .admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f2f2f2;
            vertical-align: middle;
        }

        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: #fafafa; }

        .admin-table .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 0.82rem;
            border-radius: 6px;
        }

        .btn-danger {
            background: #e94560;
        }

        .btn-danger:hover {
            background: #c73652;
        }

        .btn-edit {
            background: #0f3460;
        }

        .btn-edit:hover {
            background: #16213e;
        }

        /* Status badge */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-pending    { background: #fff3cd; color: #856404; }
        .badge-processing { background: #cce5ff; color: #004085; }
        .badge-shipped    { background: #d4edda; color: #155724; }
        .badge-delivered  { background: #d1ecf1; color: #0c5460; }
        .badge-cancelled  { background: #f8d7da; color: #721c24; }

        /* Status select inline */
        .status-select {
            padding: 5px 10px;
            border: 2px solid #eee;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .status-select:focus {
            border-color: #e94560;
            outline: none;
        }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            text-align: center;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #e94560;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 4px;
        }

        .edit-form-card {
            border: 2px solid #e94560;
        }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            .admin-table { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="nav-brand">🛒 ShopEasy Secure</a>
    <div class="nav-links">
        <span>Admin: <strong><?= e($_SESSION['username']) ?></strong></span>
        <a href="index.php">🏠 Store</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="admin-wrapper">
    <div class="admin-header">
        <h1>⚙️ Admin <span>Panel</span></h1>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?= count($products) ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count($orders) ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($orders, fn($o) => $o['status'] === 'pending')) ?></div>
            <div class="stat-label">Pending Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">RM <?= number_format(array_sum(array_column($orders, 'total_price')), 2) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="admin.php?tab=products" class="tab-btn <?= $tab === 'products' ? 'active' : '' ?>">📦 Products</a>
        <a href="admin.php?tab=orders"   class="tab-btn <?= $tab === 'orders'   ? 'active' : '' ?>">🧾 Orders</a>
    </div>

    <!-- ─── PRODUCTS TAB ─── -->
    <?php if ($tab === 'products'): ?>

        <!-- Edit form (shown only when editing) -->
        <?php if ($editProduct): ?>
        <div class="card edit-form-card">
            <h2>✏️ Edit Product — <?= e($editProduct['name']) ?></h2>
            <form method="POST" action="admin.php?tab=products">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" value="<?= e($editProduct['name']) ?>" maxlength="200" required>
                    </div>
                    <div class="form-group">
                        <label>Price (RM)</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?= e($editProduct['price']) ?>" required>
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="2"><?= e($editProduct['description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" value="<?= (int) $editProduct['stock'] ?>" required style="width:100%;">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:10px;">
                        <button type="submit" class="btn">💾 Save Changes</button>
                        <a href="admin.php?tab=products" class="btn btn-outline">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Add product form -->
        <div class="card">
            <h2>➕ Add New Product</h2>
            <form method="POST" action="admin.php?tab=products">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                <input type="hidden" name="action" value="add_product">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" placeholder="e.g. Gaming Mouse" maxlength="200" required>
                    </div>
                    <div class="form-group">
                        <label>Price (RM)</label>
                        <input type="number" name="price" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Short product description..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" placeholder="0" required style="width:100%;">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button type="submit" class="btn">Add Product</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products list -->
        <div class="card">
            <h2>📦 All Products (<?= count($products) ?>)</h2>
            <?php if (empty($products)): ?>
                <p style="color:#888;">No products found.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= (int) $p['id'] ?></td>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td style="color:#666;max-width:220px;"><?= e(mb_strimwidth($p['description'], 0, 60, '…')) ?></td>
                        <td>RM <?= number_format((float)$p['price'], 2) ?></td>
                        <td><?= (int) $p['stock'] ?></td>
                        <td>
                            <div class="actions">
                                <a href="admin.php?tab=products&edit=<?= (int) $p['id'] ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                                <form method="POST" action="admin.php?tab=products" onsubmit="return confirm('Delete this product?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- ─── ORDERS TAB ─── -->
    <?php if ($tab === 'orders'): ?>
    <div class="card">
        <h2>🧾 All Orders (<?= count($orders) ?>)</h2>
        <?php if (empty($orders)): ?>
            <p style="color:#888;">No orders yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Shipping Address</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= e($o['order_ref']) ?></strong></td>
                    <td><?= e($o['username']) ?></td>
                    <td>RM <?= number_format((float)$o['total_price'], 2) ?></td>
                    <td>
                        <span class="badge badge-<?= e($o['status']) ?>"><?= e($o['status']) ?></span>
                    </td>
                    <td style="max-width:180px;color:#555;"><?= e(mb_strimwidth($o['shipping_address'], 0, 50, '…')) ?></td>
                    <td style="white-space:nowrap;"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                    <td>
                        <div class="actions">
                            <!-- Update status inline -->
                            <form method="POST" action="admin.php?tab=orders" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_order">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <select name="status" class="status-select">
                                    <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm">✔</button>
                            </form>
                            <!-- Delete order -->
                            <form method="POST" action="admin.php?tab=orders" onsubmit="return confirm('Delete order <?= e($o['order_ref']) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
