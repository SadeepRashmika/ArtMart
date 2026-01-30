<?php
// artisan/profile.php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in as artisan
requireRole('artisan');

$database = new Database();
$db = $database->getConnection();

// Get artisan information
$stmt = $db->prepare("
    SELECT a.*, u.username 
    FROM artisans a 
    JOIN users u ON a.user_id = u.user_id 
    WHERE a.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    $_SESSION['error'] = "Artisan profile not found.";
    header("Location: ../index.php");
    exit();
}

// Initialize errors array
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: profile.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            // Update artisan profile
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $status = $_POST['status'];
            
            // Validation
            if (empty($name)) {
                $errors[] = "Name is required.";
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Valid email is required.";
            }
            
            if (!empty($phone) && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
                $errors[] = "Please enter a valid phone number.";
            }
            
            // Check if email is already taken by another artisan
            if ($email !== $artisan['email']) {
                $stmt = $db->prepare("SELECT artisan_id FROM artisans WHERE email = ? AND artisan_id != ?");
                $stmt->execute([$email, $artisan['artisan_id']]);
                if ($stmt->fetch()) {
                    $errors[] = "Email address is already registered to another artisan account.";
                }
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("
                    UPDATE artisans 
                    SET name = ?, email = ?, phone = ?, status = ?
                    WHERE artisan_id = ?
                ");
                $stmt->execute([$name, $email, $phone, $status, $artisan['artisan_id']]);
                
                $_SESSION['success'] = "Profile updated successfully!";
                
                // Update artisan data for display
                $artisan['name'] = $name;
                $artisan['email'] = $email;
                $artisan['phone'] = $phone;
                $artisan['status'] = $status;
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validation
            if (empty($current_password)) {
                $errors[] = "Current password is required.";
            }
            
            if (strlen($new_password) < 6) {
                $errors[] = "New password must be at least 6 characters long.";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New password and confirmation do not match.";
            }
            
            if (empty($errors)) {
                // Verify current password
                $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($current_password, $user_data['password'])) {
                    $errors[] = "Current password is incorrect.";
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $_SESSION['success'] = "Password changed successfully!";
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Database error occurred. Please try again.";
        error_log("Artisan profile update error: " . $e->getMessage());
    }
}

// Get artisan's business statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(SUM(od.subtotal), 0) as total_revenue,
        COUNT(DISTINCT p.product_id) as total_products,
        COUNT(DISTINCT o.customer_id) as unique_customers
    FROM products p
    LEFT JOIN order_details od ON p.product_id = od.product_id
    LEFT JOIN orders o ON od.order_id = o.order_id
    WHERE p.artisan_id = ?
");
$stmt->execute([$artisan['artisan_id']]);
$business_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-header {
             background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .form-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }
        .error-messages {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .btn-update {
            background: #28a745;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }
        .btn-update:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }
        .btn-secondary:hover {
            background: #0056b3;
        }
        .tabs {
            display: flex;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 4px;
        }
        .tab {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            color: #666;
        }
        .tab.active {
            background: white;
            color: #333;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
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
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="profile.php">My Profile</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <h1>🎨 Artisan Profile</h1>
                    <p>Welcome, <?php echo htmlspecialchars($artisan['name']); ?>!</p>
                    <p>Artisan since <?php echo date('F Y', strtotime($artisan['join_date'])); ?></p>
                    <div style="margin-top: 0.5rem;">
                        <span class="status-<?php echo $artisan['status']; ?>" style="
                            background: rgba(255,255,255,0.2); 
                            padding: 4px 12px; 
                            border-radius: 15px; 
                            font-size: 0.9rem;
                        ">
                            Status: <?php echo ucfirst($artisan['status']); ?>
                        </span>
                    </div>
                    
                    <!-- Business Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $business_stats['total_products']; ?></div>
                            <div>Products</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $business_stats['total_orders']; ?></div>
                            <div>Orders</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">$<?php echo number_format($business_stats['total_revenue'], 2); ?></div>
                            <div>Revenue</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $business_stats['unique_customers']; ?></div>
                            <div>Customers</div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <ul style="margin: 0; padding-left: 1rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab('profile')">📝 Profile Information</button>
                    <button class="tab" onclick="showTab('password')">🔒 Change Password</button>
                    <button class="tab" onclick="showTab('business')">📊 Business Overview</button>
                </div>

                <!-- Profile Information Tab -->
                <div id="profile-tab" class="tab-content active">
                    <div class="form-section">
                        <h2>Update Profile Information</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($artisan['name']); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($artisan['email']); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($artisan['phone']); ?>" 
                                       placeholder="e.g., +94 76 943 1050">
                            </div>

                            <div class="form-group">
                                <label for="status">Account Status</label>
                                <select id="status" name="status" required>
                                    <option value="active" <?php echo $artisan['status'] === 'active' ? 'selected' : ''; ?>>
                                        ✅ Active (Products visible to customers)
                                    </option>
                                    <option value="inactive" <?php echo $artisan['status'] === 'inactive' ? 'selected' : ''; ?>>
                                        ❌ Inactive (Products hidden from customers)
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($artisan['username']); ?>" 
                                       readonly 
                                       style="background: #f8f9fa; color: #666;">
                                <small style="color: #666;">Username cannot be changed</small>
                            </div>

                            <div class="form-group">
                                <label>Join Date</label>
                                <input type="text" 
                                       value="<?php echo date('F j, Y', strtotime($artisan['join_date'])); ?>" 
                                       readonly 
                                       style="background: #f8f9fa; color: #666;">
                            </div>

                            <button type="submit" class="btn-update">
                                💾 Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Tab -->
                <div id="password-tab" class="tab-content">
                    <div class="form-section">
                        <h2>Change Password</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" 
                                       id="current_password" 
                                       name="current_password" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       minlength="6"
                                       required>
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       minlength="6"
                                       required>
                            </div>

                            <button type="submit" class="btn-secondary">
                                🔐 Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Business Overview Tab -->
                <div id="business-tab" class="tab-content">
                    <div class="form-section">
                        <h2>Business Overview</h2>
                        
                        <!-- Performance Stats -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                            <div style="background: #e8f5e8; padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #28a745; margin-bottom: 0.5rem;">📦</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #333;">
                                    <?php echo $business_stats['total_orders']; ?>
                                </div>
                                <div style="color: #666;">Total Orders Received</div>
                            </div>
                            
                            <div style="background: #e3f2fd; padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #2196f3; margin-bottom: 0.5rem;">💰</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #333;">
                                    $<?php echo number_format($business_stats['total_revenue'], 2); ?>
                                </div>
                                <div style="color: #666;">Total Revenue</div>
                            </div>
                            
                            <div style="background: #fff3e0; padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #ff9800; margin-bottom: 0.5rem;">🎨</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #333;">
                                    <?php echo $business_stats['total_products']; ?>
                                </div>
                                <div style="color: #666;">Products Listed</div>
                            </div>
                            
                            <div style="background: #fce4ec; padding: 1.5rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #e91e63; margin-bottom: 0.5rem;">👥</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: #333;">
                                    <?php echo $business_stats['unique_customers']; ?>
                                </div>
                                <div style="color: #666;">Unique Customers</div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <?php
                        // Get recent orders for this artisan
                        $stmt = $db->prepare("
                            SELECT DISTINCT o.order_id, o.order_date, o.status, 
                                   c.name as customer_name,
                                   COUNT(od.order_detail_id) as items_count,
                                   SUM(od.subtotal) as artisan_total
                            FROM orders o
                            JOIN order_details od ON o.order_id = od.order_id
                            JOIN products p ON od.product_id = p.product_id
                            JOIN customers c ON o.customer_id = c.customer_id
                            WHERE p.artisan_id = ?
                            GROUP BY o.order_id
                            ORDER BY o.order_date DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$artisan['artisan_id']]);
                        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <h3>📈 Recent Activity</h3>
                        <?php if (empty($recent_orders)): ?>
                            <div style="text-align: center; padding: 2rem; color: #666; background: #f8f9fa; border-radius: 8px;">
                                <p>No orders yet. Keep adding great products!</p>
                                <a href="add_product.php" class="btn-update">Add New Product</a>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Order</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Date</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Customer</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Items</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Your Total</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    #<?php echo $order['order_id']; ?>
                                                </td>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                                </td>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                </td>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    <?php echo $order['items_count']; ?> items
                                                </td>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    $<?php echo number_format($order['artisan_total'], 2); ?>
                                                </td>
                                                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                                    <span style="
                                                        padding: 4px 8px; 
                                                        border-radius: 4px; 
                                                        font-size: 0.8rem; 
                                                        font-weight: bold;
                                                        background: <?php 
                                                            echo $order['status'] === 'Delivered' ? '#d4edda' : 
                                                                ($order['status'] === 'Cancelled' ? '#f8d7da' : 
                                                                ($order['status'] === 'Shipped' ? '#cce7ff' : '#fff3cd')); 
                                                        ?>;
                                                        color: <?php 
                                                            echo $order['status'] === 'Delivered' ? '#155724' : 
                                                                ($order['status'] === 'Cancelled' ? '#721c24' : 
                                                                ($order['status'] === 'Shipped' ? '#004085' : '#856404')); 
                                                        ?>;
                                                    ">
                                                        <?php echo $order['status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="orders.php" class="btn-secondary">View All Orders</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <a href="dashboard.php" class="btn" style="background: #6c757d; flex: 1; text-align: center;">
                        📊 Dashboard
                    </a>
                    <a href="add_product.php" class="btn" style="background: #28a745; flex: 1; text-align: center;">
                        ➕ Add Product
                    </a>
                    <a href="orders.php" class="btn" style="background: #007bff; flex: 1; text-align: center;">
                        📋 Manage Orders
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Handmade Marketplace | Connecting Artists with Art Lovers</p>
        </div>
    </footer>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Password confirmation validation
        const confirmPasswordField = document.getElementById('confirm_password');
        if (confirmPasswordField) {
            confirmPasswordField.addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = this.value;
                
                if (newPassword !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>