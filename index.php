<?php
// index.php
require_once 'config/database.php';
require_once 'config/session.php';

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();

$database = new Database();
$db = $database->getConnection();

// Get search parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build query
$query = "SELECT p.*, a.name as artisan_name, c.category_name 
          FROM products p 
          JOIN artisans a ON p.artisan_id = a.artisan_id 
          JOIN categories c ON p.category_id = c.category_id 
          WHERE p.status = 'active' AND p.stock > 0";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND p.category_id = ?";
    $params[] = $category;
}

if (!empty($min_price)) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if (!empty($max_price)) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$cat_query = "SELECT * FROM categories ORDER BY category_name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArtMart - Handmade Marketplace</title>
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"> ArtMart</a>
                <nav>
                    <ul>
                        <?php if (isLoggedIn()): ?>
                            <?php if (hasRole('admin')): ?>
                                <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                            <?php elseif (hasRole('artisan')): ?>
                                <li><a href="artisan/dashboard.php">My Dashboard</a></li>
                            <?php elseif (hasRole('customer')): ?>
                                <li><a href="customer/dashboard.php">My Account</a></li>
                                <li><a href="customer/cart.php">Cart</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; border: 1px solid #c3e6cb;">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; border: 1px solid #f5c6cb;">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 3rem;">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">Welcome to ArtMart</h1>
                <p style="font-size: 1.2rem; margin-bottom: 2rem;">Discover unique handmade products from talented artisans</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn" style="background: white; color: #667eea; font-weight: bold;">Join Our Community</a>
                <?php endif; ?>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="search-row">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="number" name="min_price" placeholder="Min Price" value="<?php echo htmlspecialchars($min_price); ?>" step="0.01">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="number" name="max_price" placeholder="Max Price" value="<?php echo htmlspecialchars($max_price); ?>" step="0.01">
                    </div>
                    <button type="submit" class="btn">Search</button>
                </form>
            </div>

            <!-- Products Grid -->
            <h2 style="color: #333; margin-bottom: 1rem;">
                <?php if (!empty($search) || !empty($category) || !empty($min_price) || !empty($max_price)): ?>
                    Search Results (<?php echo count($products); ?> products found)
                <?php else: ?>
                    Featured Products
                <?php endif; ?>
            </h2>
            
            <?php if (empty($products)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3>No products found</h3>
                    <p>Try adjusting your search criteria or browse all products.</p>
                    <a href="index.php" class="btn">View All Products</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    🎨
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                <div class="product-price">Rs <?php echo number_format($product['price'], 2); ?></div>
                                <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?></p>
                                <p class="artisan-name">by <?php echo htmlspecialchars($product['artisan_name']); ?></p>
                                <div style="margin-top: 1rem;">
                                    <span style="background: #e9ecef; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; color: #6c757d;">
                                        <?php echo htmlspecialchars($product['category_name']); ?>
                                    </span>
                                    <span style="background: #d4edda; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; color: #155724; margin-left: 0.5rem;">
                                        <?php echo $product['stock']; ?> in stock
                                    </span>
                                </div>
                                <?php if (isLoggedIn() && hasRole('customer')): ?>
                                    <form method="POST" action="customer/add_to_cart.php" style="margin-top: 1rem;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 60px; padding: 0.5rem;">
                                            <button type="submit" class="btn btn-success">Add to Cart</button>
                                        </div>
                                    </form>
                                <?php elseif (!isLoggedIn()): ?>
                                    <div style="margin-top: 1rem;">
                                        <a href="login.php" class="btn">Login to Buy</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Handmade Marketplace | Connecting Artists with Art Lovers</p>
        </div>
    </footer>
</body>
</html>