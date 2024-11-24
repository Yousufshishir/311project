<?php
session_start();

function checkLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $_SESSION['message'] = "Please login to access this page";
        $_SESSION['message_type'] = 'error';
        header("Location: login.php");
        exit();
    }
}
?>