<?php
session_start();
$conn = mysqli_connect('localhost', 'root', '', 'restaurant');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Check if this is first time setup
$first_time_setup = false;
$super_admin_check = mysqli_query($conn, "SELECT * FROM users WHERE role = 'super_admin' LIMIT 1");
if (mysqli_num_rows($super_admin_check) == 0) {
    $first_time_setup = true;
}

// Handle first time super admin setup
if ($first_time_setup && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup_super_admin'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    
    // Check for duplicate phone number
    $phone_check = mysqli_query($conn, "SELECT * FROM users WHERE phone = '$phone'");
    if (mysqli_num_rows($phone_check) > 0) {
        $_SESSION['message'] = "Phone number already exists!";
        $_SESSION['message_type'] = 'error';
    } elseif (strlen($password) < 6) {
        $_SESSION['message'] = "Password must be at least 6 characters long!";
        $_SESSION['message_type'] = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'super_admin')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $hashed_password);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Super Admin account created successfully!";
            $_SESSION['message_type'] = 'success';
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = 'super_admin';
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_phone'] = $phone;
            $first_time_setup = false;
            
            header("Location: super_admin.php");
            exit();
        } else {
            $_SESSION['message'] = "Error creating Super Admin account!";
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Handle update for super admin information
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_super_admin'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    
    // Check for duplicate phone number (excluding current user)
    $phone_check = mysqli_query($conn, "SELECT * FROM users WHERE phone = '$phone' AND id != '".$_SESSION['user_id']."'");
    if (mysqli_num_rows($phone_check) > 0) {
        $_SESSION['message'] = "Phone number already exists!";
        $_SESSION['message_type'] = 'error';
    } elseif (!empty($password) && strlen($password) < 6) {
        $_SESSION['message'] = "Password must be at least 6 characters long!";
        $_SESSION['message_type'] = 'error';
    } else {
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?";
        $params = [$name, $email, $phone];
        $types = "sss";
        
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ? AND role = 'super_admin'";
        $params[] = $_SESSION['user_id'];
        $types .= "i";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Super Admin info updated successfully!";
            $_SESSION['message_type'] = 'success';
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_phone'] = $phone;
        } else {
            $_SESSION['message'] = "Error updating Super Admin info!";
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Handle adding new admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $admin_name = mysqli_real_escape_string($conn, $_POST['admin_name']);
    $admin_email = mysqli_real_escape_string($conn, $_POST['admin_email']);
    $admin_phone = mysqli_real_escape_string($conn, $_POST['admin_phone']);
    $admin_password = $_POST['admin_password'];

    // Check for duplicate email and phone
    $email_check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$admin_email'");
    $phone_check = mysqli_query($conn, "SELECT id FROM users WHERE phone = '$admin_phone'");
    
    if (mysqli_num_rows($email_check) > 0) {
        $_SESSION['message'] = "Email already exists!";
        $_SESSION['message_type'] = 'error';
    } elseif (mysqli_num_rows($phone_check) > 0) {
        $_SESSION['message'] = "Phone number already exists!";
        $_SESSION['message_type'] = 'error';
    } elseif (strlen($admin_password) < 6) {
        $_SESSION['message'] = "Password must be at least 6 characters long!";
        $_SESSION['message_type'] = 'error';
    } else {
        $hashed_admin_password = password_hash($admin_password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $admin_name, $admin_email, $admin_phone, $hashed_admin_password);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "New admin added successfully!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error adding new admin!";
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Secure delete operation with CSRF protection
if (isset($_GET['delete_user_id']) && isset($_GET['token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die('Invalid token');
    }
    
    $user_id = (int)$_GET['delete_user_id'];
    $delete_sql = "DELETE FROM users WHERE id = ? AND role != 'super_admin'";
    $stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "User deleted successfully!";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Error deleting user!";
        $_SESSION['message_type'] = 'error';
    }
    
    // Prevent redirect to login
    header("Location: super_admin.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all customers
$customers_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'user'");

// Fetch all admins
$admins_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin'");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f4f5f7;
    }

    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-4xl font-bold text-center text-blue-800 mb-8">Super Admin Dashboard</h1>

            <?php if (isset($_SESSION['message'])): ?>
            <div
                class="<?= $_SESSION['message_type'] == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> p-4 rounded-lg mb-6 shadow">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php 
                unset($_SESSION['message']); 
                unset($_SESSION['message_type']); 
                ?>
            <?php endif; ?>

            <?php if ($first_time_setup): ?>
            <!-- First Time Setup Form -->
            <div class="bg-white rounded-lg shadow-lg p-8 max-w-md mx-auto">
                <h2 class="text-2xl font-semibold text-center mb-6">First Time Setup</h2>
                <form method="POST" class="space-y-4">
                    <input type="text" name="name" placeholder="Full Name" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="email" name="email" placeholder="Email" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="phone" placeholder="Phone Number" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="password" name="password" placeholder="Password (min 6 characters)" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" name="setup_super_admin"
                        class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
                        Create Super Admin Account
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Update Super Admin Info -->
                <div class="bg-white rounded-lg shadow-lg p-6 card">
                    <h2 class="text-2xl font-semibold mb-6 text-blue-800">
                        <i class="fas fa-user-edit mr-2"></i>Update Your Info
                    </h2>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="name" placeholder="Full Name"
                            value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="email" name="email" placeholder="Email"
                            value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="text" name="phone" placeholder="Phone Number"
                            value="<?= htmlspecialchars($_SESSION['user_phone'] ?? '') ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="password" name="password" placeholder="New Password (min 6 characters)"
                            class="w-full px-4 py-2 border rounded-lg">
                        <button type="submit" name="update_super_admin"
                            class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
                            Update Info
                        </button>
                    </form>
                </div>

                <!-- Add New Admin -->
                <div class="bg-white rounded-lg shadow-lg p-6 card">
                    <h2 class="text-2xl font-semibold mb-6 text-blue-800">
                        <i class="fas fa-user-plus mr-2"></i>Add New Admin
                    </h2>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="admin_name" placeholder="Admin Name" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="email" name="admin_email" placeholder="Admin Email" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="text" name="admin_phone" placeholder="Admin Phone" required
                            class="w-full px-4 py-2 border rounded-lg">
                        <input type="password" name="admin_password" placeholder="Admin Password (min 6 characters)"
                            required class="w-full px-4 py-2 border rounded-lg">
                        <button type="submit" name="add_admin"
                            class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition duration-300">
                            Add Admin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Manage Admins -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-6 card">
                <h2 class="text-2xl font-semibold mb-6 text-blue-800">
                    <i class="fas fa-users-cog mr-2"></i>Manage Admins
                </h2>
                <?php if (mysqli_num_rows($admins_result) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full bg-white">
                        <thead>
                            <tr class="bg-blue-100 text-blue-800">
                                <th class="py-3 px-4 text-left">Name</th>
                                <th class="py-3 px-4 text-left">Email</th>
                                <th class="py-3 px-4 text-left">Phone</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>



                        <tbody>

                            <?php 
                                        // Reset the pointer
                                        mysqli_data_seek($admins_result, 0);
                                        while ($row = mysqli_fetch_assoc($admins_result)): 
                                        ?>
                            <tr class="border-b hover:bg-gray-100">
                                <td class="py-3 px-4"><?= htmlspecialchars($row['Name']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['email']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="py-3 px-4 text-center">
                                    <a href="?delete_user_id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                        onclick="return confirm('Are you sure you want to delete this admin?')"
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-300">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500">No admins found.</p>
                <?php endif; ?>
            </div>

            <!-- Manage Customers -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-6 card">
                <h2 class="text-2xl font-semibold mb-6 text-blue-800">
                    <i class="fas fa-users mr-2"></i>Manage Customers
                </h2>
                <?php if (mysqli_num_rows($customers_result) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full bg-white">
                        <thead>
                            <tr class="bg-green-100 text-green-800">
                                <th class="py-3 px-4 text-left">Name</th>
                                <th class="py-3 px-4 text-left">Email</th>
                                <th class="py-3 px-4 text-left">Phone</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                        // Reset the pointer
                                        mysqli_data_seek($customers_result, 0);
                                        while ($row = mysqli_fetch_assoc($customers_result)): 
                                        ?>
                            <tr class="border-b hover:bg-gray-100">
                                <td class="py-3 px-4"><?= htmlspecialchars($row['Name']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['email']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="py-3 px-4 text-center">
                                    <a href="?delete_user_id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                        onclick="return confirm('Are you sure you want to delete this customer?')"
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-300">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500">No customers found.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Access Buttons -->
            <div class="flex justify-center space-x-4 mt-6">
                <a href="admin_dashboard.php"
                    class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">
                    <i class="fas fa-chart-line mr-2"></i>Go to Admin Dashboard
                </a>
                <a href="logout.php"
                    class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    </div>

    <script>
    // Optional:....... Add some client-side validation

    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const passwordField = form.querySelector('input[type="password"]');
                if (passwordField && passwordField.value.length > 0 && passwordField.value
                    .length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long');
                }
            });
        });
    });
    </script>
</body>

</html>





<?php
// Close database connection
mysqli_close($conn);
?>