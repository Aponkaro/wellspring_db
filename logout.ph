<?php
// Initialize the session
session_start();

// Unset all session variables
$_SESSION = array();

// If a session cookie exists, clear it from the client's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session completely
session_destroy();

// Redirect user back to the login page
header("Location: login.php?msg=logged_out");
exit;
?>
