<?php
session_start();

// Function to display messages
function showMessage() {
    if (isset($_SESSION['message'])) {
        $messageClass = ($_SESSION['message_type'] == 'success') ? 'success' : 'error';
        echo '<div class="message ' . $messageClass . '">' . $_SESSION['message'] . '</div>';
        // Clear the message after displaying
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    // Database connection
    $conn = mysqli_connect('localhost', 'root', '', 'restaurant');
    
    // Check connection
    if (!$conn) {
        $_SESSION['message'] = "Connection failed: " . mysqli_connect_error();
        $_SESSION['message_type'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Get and sanitize input data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email or phone already exists
    $check_duplicate = "SELECT email, phone FROM users WHERE email = ? OR phone = ?";
    $stmt_check = mysqli_prepare($conn, $check_duplicate);
    mysqli_stmt_bind_param($stmt_check, "ss", $email, $phone);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($row['email'] == $email) {
            $_SESSION['message'] = "This email is already registered!";
        } else {
            $_SESSION['message'] = "This phone number is already registered!";
        }
        $_SESSION['message_type'] = 'error';
    } else {
        // First, let's reset the auto-increment if the table is empty
        $check_empty = "SELECT COUNT(*) as count FROM users";
        $result = mysqli_query($conn, $check_empty);
        $row = mysqli_fetch_assoc($result);
        if ($row['count'] == 0) {
            mysqli_query($conn, "ALTER TABLE users AUTO_INCREMENT = 1");
        }

        // Prepare SQL statement
        $sql = "INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)";
        
        // Create a prepared statement
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $password);
            
            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Registration successful! Please login.";
                $_SESSION['message_type'] = 'success';
                $_SESSION['redirect_to_login'] = true;
            } else {
                $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
                $_SESSION['message_type'] = 'error';
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['message'] = "Error preparing statement: " . mysqli_error($conn);
            $_SESSION['message_type'] = 'error';
        }
    }
    
    // Close connection
    mysqli_close($conn);
    
    // Redirect to login page on successful registration
    if (isset($_SESSION['redirect_to_login']) && $_SESSION['redirect_to_login'] === true) {
        unset($_SESSION['redirect_to_login']);
        header("Location: login.php");
        exit();
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form</title>
    <style>
    body,
    html {
        height: 100%;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Arial', sans-serif;
        background-image: url('1.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        position: relative;
    }

    .registration-form {
        width: 350px;
        padding: 25px;
        border-radius: 12px;
        background-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .registration-form input[type="text"],
    .registration-form input[type="email"],
    .registration-form input[type="password"],
    .registration-form input[type="tel"],
    .registration-form input[type="submit"] {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        box-sizing: border-box;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
    }

    .registration-form input[type="submit"] {
        background-color: #FF6347;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 18px;
    }

    .registration-form input[type="submit"]:hover {
        background-color: #FF4500;
    }

    .registration-form h2 {
        color: #333;
        font-size: 24px;
        margin-bottom: 15px;
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

    .login-link {
        margin-top: 15px;
        font-size: 14px;
    }

    .login-link a {
        color: #FF6347;
        text-decoration: none;
    }

    .login-link a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="registration-form">
        <h2>Sign Up and Enjoy</h2>
        <?php showMessage(); ?>
        <form method="post">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="tel" name="phone" placeholder="Phone Number" required>
            <input type="password" name="password" placeholder="Password" required minlength="6">
            <input type="submit" name="submit" value="Register">
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>

</html>