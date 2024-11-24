<?php
session_start();

function showMessage() {
    if (isset($_SESSION['message'])) {
        $messageClass = ($_SESSION['message_type'] == 'success') ? 'success' : 'error';
        echo '<div class="message ' . $messageClass . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $conn = mysqli_connect('localhost', 'root', '', 'restaurant');
    if (!$conn) {
        $_SESSION['message'] = "Connection failed: " . mysqli_connect_error();
        $_SESSION['message_type'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $sql = "SELECT * FROM users WHERE (email = ? OR phone = ?) AND role = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $identifier, $identifier, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['logged_in'] = true;

                if ($role == 'super_admin') {
                    header("Location: super_admin.php");
                } elseif ($role == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $_SESSION['message'] = "Incorrect password!";
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = "No account found with this email/phone for the selected role!";
            $_SESSION['message_type'] = 'error';
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['message'] = "Error preparing statement: " . mysqli_error($conn);
        $_SESSION['message_type'] = 'error';
    }
    mysqli_close($conn);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
    body,
    html {
        height: 100%;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Arial', sans-serif;
        background-image: url('22.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        position: relative;
    }

    .login-form {
        width: 350px;
        padding: 25px;
        border-radius: 12px;
        background-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .login-form input[type="text"],
    .login-form input[type="password"],
    .login-form input[type="submit"] {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        box-sizing: border-box;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
    }

    .login-form input[type="submit"] {
        background-color: #FF6347;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 18px;
    }

    .login-form input[type="submit"]:hover {
        background-color: #FF4500;
    }

    .login-form h2 {
        color: #333;
        font-size: 24px;
        margin-bottom: 15px;
    }

    .register-link {
        margin-top: 15px;
        font-size: 14px;
    }

    .register-link a {
        color: #FF6347;
        text-decoration: none;
    }

    .register-link a:hover {
        text-decoration: underline;
    }

    .message {
        margin: 10px 0;
        padding: 10px;
        border-radius: 5px;
        font-weight: bold;
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
    </style>
</head>

<body>
    <div class="login-form">
        <h2>Welcome Back</h2>
        <?php showMessage(); ?>
        <form method="post">
            <input type="text" name="identifier" placeholder="Email or Phone Number" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="super_admin">Super Admin</option>
            </select>
            <input type="submit" name="login" value="Login">
        </form>
        <div class="register-link">
            Don't have an account? <a href="reg.php">Register here</a>
        </div>

        <div class="logout-link">
            <a href="?logout=true">Logout</a>
        </div>