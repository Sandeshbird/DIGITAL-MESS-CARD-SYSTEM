<?php
// views/auth/reset_password.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
session_start();

$token = $_GET['token'] ?? '';
$success_message = '';
$error_message = '';

if (empty($token)) {
    die("Invalid or missing reset token.");
}

$database = new Database();
$db = $database->getConnection();

// Step 1: Verify token and check expiry
try {
    $query = "SELECT id, reset_expires FROM user WHERE reset_token = :token LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error_message = "Invalid or expired reset link.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error_message = "Reset link has expired. Please request a new one.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm_password)) {
            $error_message = "Both password fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Step 2: Update password and clear token
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update = "UPDATE user 
                       SET password = :password, reset_token = NULL, reset_expires = NULL 
                       WHERE id = :id";
            $update_stmt = $db->prepare($update);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $user['id']);

            if ($update_stmt->execute()) {
                $success_message = "Your password has been reset successfully! <a href='login.php'>Login here</a>.";
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Mess Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); min-height: 100vh;">
    <div class="container" style="margin-top: 100px;">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header text-center bg-primary text-white">
                        <h4>Reset Your Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php elseif ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php else: ?>
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password:</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password:</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Reset Password</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
