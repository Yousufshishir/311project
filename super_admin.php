<?php
session_start();
$conn = mysqli_connect('localhost', 'root', '', 'restaurant');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if the user is logged in and is a super admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] != 'super_admin') {
    // If not, redirect to login page
    header("Location: login.php");
    exit();
}

// Handle update for super admin information
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_super_admin'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Update super admin's information
    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone, $hashed_password, $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Super Admin info updated successfully!";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Error updating Super Admin info!";
        $_SESSION['message_type'] = 'error';
    }
}

// Handle adding new admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_phone = $_POST['admin_phone'];
    $admin_password = $_POST['admin_password'];

    // Hash the password for the new admin
    $hashed_admin_password = password_hash($admin_password, PASSWORD_BCRYPT);

    // Insert the new admin into the database
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

// Fetch all customers
$customers_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'user'");

// Fetch all admins
$admins_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin'");

// Handle deleting customer/admin
if (isset($_GET['delete_user_id'])) {
    $user_id = $_GET['delete_user_id'];
    $delete_sql = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    header("Location: admin_dashboard.php"); // Redirect after delete
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f9;
        margin: 0;
        padding: 0;
    }

    .container {
        width: 80%;
        margin: 30px auto;
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
        color: #333;
    }

    h2 {
        color: #FF6347;
    }

    form input[type="text"],
    form input[type="email"],
    form input[type="password"],
    form input[type="submit"],
    form select {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        box-sizing: border-box;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
    }

    form input[type="submit"] {
        background-color: #FF6347;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    form input[type="submit"]:hover {
        background-color: #FF4500;
    }

    .message {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        font-weight: bold;
        text-align: center;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    table {
        width: 100%;
        margin-top: 30px;
        border-collapse: collapse;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    table th,
    table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    table th {
        background-color: #FF6347;
        color: white;
    }

    table tr:hover {
        background-color: #f1f1f1;
    }

    a {
        text-decoration: none;
        color: #FF6347;
    }

    a:hover {
        text-decoration: underline;
    }

    .footer {
        text-align: center;
        margin-top: 50px;
    }
    </style>
</head>

<body>

    <div class="container">
        <h1>Welcome, Super Admin</h1>

        <?php
    // Display success or error messages
    if (isset($_SESSION['message'])) {
        $messageClass = ($_SESSION['message_type'] == 'success') ? 'success' : 'error';
        echo '<div class="message ' . $messageClass . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>

        <!-- Super Admin Update Info Form -->
        <h2>Update Your Info</h2>
        <form method="POST">
            <input type="text" name="name" placeholder="Full Name"
                value="<?= isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '' ?>" required>
            <input type="email" name="email" placeholder="Email"
                value="<?= isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '' ?>" required>
            <input type="text" name="phone" placeholder="Phone Number"
                value="<?= isset($_SESSION['user_phone']) ? $_SESSION['user_phone'] : '' ?>" required>
            <input type="password" name="password" placeholder="New Password" required>
            <input type="submit" name="update_super_admin" value="Update Info">
        </form>

        <!-- Add New Admin Form -->
        <h2>Add New Admin</h2>
        <form method="POST">
            <input type="text" name="admin_name" placeholder="Admin Name" required>
            <input type="email" name="admin_email" placeholder="Admin Email" required>
            <input type="text" name="admin_phone" placeholder="Admin Phone" required>
            <input type="password" name="admin_password" placeholder="Admin Password" required>
            <input type="submit" name="add_admin" value="Add Admin">
        </form>

        <!-- Manage Customers Section -->
        <h2>Manage Customers</h2>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = mysqli_fetch_assoc($customers_result)) { ?>
            <tr>
                <td><?= $row['name'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['phone'] ?></td>
                <td><a href="?delete_user_id=<?= $row['id'] ?>">Delete</a></td>
            </tr>
            <?php } ?>
        </table>

        <!-- Manage Admins Section -->
        <h2>Manage Admins</h2>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = mysqli_fetch_assoc($admins_result)) { ?>
            <tr>
                <td><?= $row['name'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['phone'] ?></td>
                <td><a href="?delete_user_id=<?= $row['id'] ?>">Delete</a></td>
            </tr>
            <?php } ?>
        </table>

        <div class="footer">
            <a href="admin_dashboard.php">Go to Admin Dashboard</a><br>
            <a href="logout.php">Logout</a>
        </div>
    </div>

</body>

</html>