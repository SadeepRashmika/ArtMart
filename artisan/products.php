<?php
// artisan/products.php - Manage Products
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/session.php';

requireRole('artisan');

$database = new Database();
$db = $database->getConnection();

// Get artisan info
$query = "SELECT artisan_id FROM artisans WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    header("Location: ../login.php");
    exit();
}

$message = "";
$message_type = "";

// Handle URL parameters for messages
if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $message_type = "success";
} elseif (isset($_GET['error'])) {
    $message = $_GET['error'];
    $message_type = "error";
} elseif (isset($_GET['warning'])) {
    $message = $_GET['warning'];
    $message_type = "warning";
}

// Handle ONLY status toggle - NO DELETE HERE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['toggle_status'])) {
        $product_id = intval($_POST['product_id']);
        $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        try {
            $update_query = "UPDATE products SET status = ? WHERE product_id = ? AND artisan_id = ?";
            $update_stmt = $db->prepare($update_query);
            $result = $update_stmt->execute([$new_status, $product_id, $artisan['artisan_id']]);
            
            if ($result && $update_stmt->rowCount() > 0) {
                $message = "Product status updated to " . $new_status;
                $message_type = "success";
            } else {
                $message = "Failed to update product status";
                $message_type = "error";
            }
        } catch (Exception $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get artisan's products
$query = "SELECT p.*, c.category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id 
          WHERE p.artisan_id = ? 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$artisan['artisan_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin-top: 2rem; }
        .sidebar { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; height: fit-content; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar ul li { margin-bottom: 0.5rem; }
        .sidebar ul li a { color: #333; text-decoration: none; padding: 0.5rem; display: block; border-radius: 4px; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #667eea; color: white; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status.active {
            background: #d4edda;
            color: #155724;
        }
        .status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: 667eea;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            text-align: center;
            font-weight: 600;
            margin: 0.1rem;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #eeeff4ff;
            text-decoration: none;
        }
        nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1rem;
        }
        nav ul li a {
            color: #f8f9fa ;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        nav ul li a:hover {
            background: #f8f9fa;
        }
        header {
            background:linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
            margin: 0;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">🎨 ArtMart</a>
                <nav>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="dashboard">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3 style="margin-bottom: 1rem; color: #333;">Artisan Menu</h3>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <!--<li><a href="products.php" class="active">My Products</a></li>-->
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h1 style="color: #333; margin: 0;">My Products</h1>
                        <a href="add_product.php" class="btn btn-success">📦 Add New Product</a>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert <?php echo $message_type; ?>">
                            <strong><?php echo ucfirst($message_type); ?>:</strong> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($products)): ?>
                        <div class="card" style="text-align: center; padding: 3rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                            <h3 style="color: #333;">No products yet</h3>
                            <p style="color: #666; margin-bottom: 2rem;">Start by adding your first handmade product to the marketplace</p>
                            <a href="add_product.php" class="btn btn-success">Add Your First Product</a>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                <h2 style="margin: 0; color: #333;">All Products (<?php echo count($products); ?>)</h2>
                                <div style="font-size: 0.9rem; color: #666;">
                                    Active: <?php echo count(array_filter($products, function($p) { return $p['status'] === 'active'; })); ?> | 
                                    Inactive: <?php echo count(array_filter($products, function($p) { return $p['status'] === 'inactive'; })); ?>
                                </div>
                            </div>
                            
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center;">
                                                        <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                                            <?php if ($product['image_url']): ?>
                                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                                            <?php else: ?>
                                                                🎨
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                                            <small style="color: #666;"><?php echo htmlspecialchars(substr($product['description'], 0, 40)); ?>...</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="background: #e9ecef; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem;">
                                                        <?php echo htmlspecialchars($product['category_name']); ?>
                                                    </span>
                                                </td>
                                                <td><strong>LKR <?php echo number_format($product['price'], 2); ?></strong></td>
                                                <td>
                                                    <?php if ($product['stock'] <= 5): ?>
                                                        <span style="color: #dc3545; font-weight: bold;"><?php echo $product['stock']; ?></span>
                                                        <?php if ($product['stock'] == 0): ?>
                                                            <small style="display: block; color: #dc3545;">Out of Stock</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php echo $product['stock']; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status <?php echo $product['status']; ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                        <!-- Edit Button -->
                                                        <a href="update_product.php?id=<?php echo $product['product_id']; ?>" 
                                                           class="btn btn-secondary" 
                                                           title="Edit this product">
                                                             Edit
                                                        </a>
                                                        
                                                        <!-- Status Toggle Button -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                            <input type="hidden" name="status" value="<?php echo $product['status']; ?>">
                                                            <button type="submit" name="toggle_status" 
                                                                    class="btn <?php echo $product['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                                                    title="<?php echo $product['status'] === 'active' ? 'Hide from marketplace' : 'Show in marketplace'; ?>">
                                                                <?php echo $product['status'] === 'active' ? '👁️ Hide' : '👁️ Show'; ?>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Button - Links to separate page 
                                                        <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>)">Delete</button>-->
                                                             
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-top: 1.5rem;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                                    <div><strong>Total Products:</strong> <?php echo count($products); ?></div>
                                    <div><strong>Active:</strong> <span style="color: #28a745;"><?php echo count(array_filter($products, function($p) { return $p['status'] === 'active'; })); ?></span></div>
                                    <div><strong>Inactive:</strong> <span style="color: #dc3545;"><?php echo count(array_filter($products, function($p) { return $p['status'] === 'inactive'; })); ?></span></div>
                                    <div><strong>Low Stock:</strong> <span style="color: #ffc107;"><?php echo count(array_filter($products, function($p) { return $p['stock'] <= 5; })); ?></span></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Product Management</p>
        </div>
    </footer>

    <script>
        // Confirmation for status toggle
        document.querySelectorAll('button[name="toggle_status"]').forEach(button => {
            button.addEventListener('click', function(e) {
                const status = this.previousElementSibling.value;
                const action = status === 'active' ? 'hide' : 'show';
                const productName = this.closest('tr').querySelector('strong').textContent;
                
                if (!confirm(`Are you sure you want to ${action} "${productName}" in the marketplace?`)) {
                    e.preventDefault();
                }
            });
        });
        
        // Add loading state to buttons
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', function() {
                this.style.opacity = '0.6';
                this.style.pointerEvents = 'none';
                setTimeout(() => {
                    this.style.opacity = '1';
                    this.style.pointerEvents = 'auto';
                }, 2000);
            });
        });
    </script>
</body>
</html>