<?php
// artisan/update_product.php - Update Product
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/session.php';

requireRole('artisan');

$product_id = intval($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header("Location: products.php?error=Invalid product ID");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get artisan info
$artisan_query = "SELECT artisan_id FROM artisans WHERE user_id = ?";
$artisan_stmt = $db->prepare($artisan_query);
$artisan_stmt->execute([$_SESSION['user_id']]);
$artisan = $artisan_stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    header("Location: ../login.php");
    exit();
}

// Get product - ensure it belongs to this artisan
$product_query = "SELECT * FROM products WHERE product_id = ? AND artisan_id = ?";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute([$product_id, $artisan['artisan_id']]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php?error=Product not found or access denied");
    exit();
}

// Get categories
$cat_query = "SELECT * FROM categories ORDER BY category_name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";
$success = "";

// Simple sanitize function if not available
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = sanitize($_POST['product_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $image_url = sanitize($_POST['image_url'] ?? '');
    
    // Validation
    if (empty($product_name)) {
        $error = "Product name is required";
    } elseif (empty($description)) {
        $error = "Product description is required";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0";
    } elseif ($stock < 0) {
        $error = "Stock cannot be negative";
    } elseif ($category_id <= 0) {
        $error = "Please select a valid category";
    } else {
        try {
            $query = "UPDATE products 
                     SET product_name = ?, description = ?, price = ?, stock = ?, category_id = ?, image_url = ? 
                     WHERE product_id = ? AND artisan_id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $product_name, 
                $description, 
                $price, 
                $stock, 
                $category_id, 
                $image_url,
                $product_id,
                $artisan['artisan_id']
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                $success = "Product updated successfully!";
                
                // Refresh product data
                $product_stmt->execute([$product_id, $artisan['artisan_id']]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "No changes were made or product not found.";
            }
            
        } catch (Exception $e) {
            $error = "Error updating product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Product - ArtMart</title>
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
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
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
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            text-align: center;
            font-weight: 600;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
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
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #f5f5f9ff;
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
            color: #e7eaecff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        nav ul li a:hover {
            background: #e7eaecff;
        }
        header {
            background: linear-gradient(135deg, #667eea, #764ba2);
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
                        <li><a href="products.php">My Products</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div>
                    <div style="margin-bottom: 2rem;">
                        <a href="products.php" style="color: #667eea; text-decoration: none; font-weight: 600;">← Back to Products</a>
                    </div>
                    
                    <h1 style="color: #333; margin-bottom: 2rem;">Update Product</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert error">
                            <strong>Error:</strong> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success">
                            <strong>Success:</strong> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h2 style="margin-top: 0; color: #333;">Edit Product Details</h2>
                        
                        <form method="POST" novalidate>
                            <div class="form-group">
                                <label for="product_name">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" 
                                       value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category_id">Category *</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                                <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="price">Price (LKR) *</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0.01" 
                                           value="<?php echo $product['price']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock">Stock Quantity *</label>
                                    <input type="number" id="stock" name="stock" min="0" 
                                           value="<?php echo $product['stock']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="5" required 
                                          placeholder="Describe your product in detail..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="image_url">Image URL (optional)</label>
                                <input type="url" id="image_url" name="image_url" 
                                       value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>"
                                       placeholder="https://example.com/image.jpg">
                                <small style="color: #666; font-size: 0.9rem; display: block; margin-top: 0.5rem;">
                                    Provide a direct link to your product image. Leave empty if you don't have one.
                                </small>
                            </div>
                            
                            <!-- Current Product Info -->
                            <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0;">
                                <h4 style="margin-top: 0; color: #333; margin-bottom: 1rem;">Current Product Status</h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                                    <div><strong>Status:</strong> <span class="status <?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></div>
                                    <div><strong>Created:</strong> <?php echo date('M j, Y', strtotime($product['created_at'])); ?></div>
                                    <div><strong>Product ID:</strong> #<?php echo $product['product_id']; ?></div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="submit" class="btn btn-success">
                                    💾 Update Product
                                </button>
                                <a href="products.php" class="btn btn-secondary">
                                    ❌ Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Update Product</p>
        </div>
    </footer>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const productName = document.getElementById('product_name').value.trim();
            const description = document.getElementById('description').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock').value);
            const category = document.getElementById('category_id').value;
            
            if (!productName) {
                alert('Product name is required');
                e.preventDefault();
                return;
            }
            
            if (!description) {
                alert('Product description is required');
                e.preventDefault();
                return;
            }
            
            if (!price || price <= 0) {
                alert('Please enter a valid price greater than 0');
                e.preventDefault();
                return;
            }
            
            if (stock < 0) {
                alert('Stock quantity cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (!category) {
                alert('Please select a category');
                e.preventDefault();
                return;
            }
        });
        
        // Auto-save draft functionality (optional)
        let saveTimeout;
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    console.log('Auto-saving draft...');
                    // Could implement auto-save here if needed
                }, 2000);
            });
        });
    </script>
</body>
</html>