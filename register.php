<?php
// register.php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address'] ?? '');
    
    // Validation
    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        $error = "Please fill in all required fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $db->beginTransaction();
            
            // Check if username or email already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$username]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("Username already exists");
            }
            
            // Check email in respective table
            if ($role == 'artisan') {
                $email_check = "SELECT COUNT(*) FROM artisans WHERE email = ?";
            } else {
                $email_check = "SELECT COUNT(*) FROM customers WHERE email = ?";
            }
            $email_stmt = $db->prepare($email_check);
            $email_stmt->execute([$email]);
            if ($email_stmt->fetchColumn() > 0) {
                throw new Exception("Email already exists");
            }
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_query = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([$username, $hashed_password, $role]);
            $user_id = $db->lastInsertId();
            
            // Create profile based on role
            if ($role == 'artisan') {
                $profile_query = "INSERT INTO artisans (user_id, name, email, phone) VALUES (?, ?, ?, ?)";
                $profile_stmt = $db->prepare($profile_query);
                $profile_stmt->execute([$user_id, $name, $email, $phone]);
            } else {
                $profile_query = "INSERT INTO customers (user_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
                $profile_stmt = $db->prepare($profile_query);
                $profile_stmt->execute([$user_id, $name, $email, $phone, $address]);
            }
            
            $db->commit();
            $success = "Registration successful! You can now login.";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ArtMart</title>
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">ArtMart</a>
                <nav>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="auth-container" style="max-width: 500px;">
                <h2 class="auth-title">Join ArtMart</h2>
                
                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="role">I want to:</label>
                        <select id="role" name="role" required onchange="toggleAddressField()">
                            <option value="">Select role</option>
                            <option value="customer">Buy handmade products</option>
                            <option value="artisan">Sell my handmade products</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Full Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group" id="address-field" style="display: none;">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" rows="3" placeholder="Your shipping address"></textarea>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%;">Register</button>
                </form>
                
                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Handmade Marketplace</p>
        </div>
    </footer>

    <script>
        function toggleAddressField() {
            const role = document.getElementById('role').value;
            const addressField = document.getElementById('address-field');
            
            if (role === 'customer') {
                addressField.style.display = 'block';
            } else {
                addressField.style.display = 'none';
            }
        }
    </script>
</body>
</html>