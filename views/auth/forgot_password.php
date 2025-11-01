<?php
// views/auth/forgot_password.php - OTP Generation & SMS Sending
session_start();
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$message = '';
$message_type = '';

// Handle phone number submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($phone)) {
        $message = 'Please enter your phone number.';
        $message_type = 'danger';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = 'Please enter a valid 10-digit phone number.';
        $message_type = 'danger';
    } else {
        try {
            // Check if user exists with this phone number
            $query = "SELECT * FROM user WHERE ph_no = :phone LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':phone', $phone);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate 6-digit OTP
                $otp = rand(100000, 999999);
                
                // Store OTP in session (in real app, store in database with expiry)
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_phone'] = $phone;
                $_SESSION['otp_time'] = time();
                
                // Simulate SMS sending (in real app, use SMS API like Twilio)
                // For now, we'll just display the OTP
                $message = "OTP sent to your phone. Please check your messages.";
                $message_type = 'success';
                
                // In a real application, you would send SMS here:
                // sendSMS($phone, "Your OTP for Mess Management is: $otp");
                
                // For testing, show OTP (remove in production)
                $message .= "<br><br><strong>OTP:</strong> $otp<br><small>(In production, this will be sent via SMS)</small>";
            } else {
                // For security, don't reveal if phone exists
                $message = 'If your phone is registered, an OTP has been sent.';
                $message_type = 'info';
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred.';
            $message_type = 'danger';
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp'] ?? '');
    
    if (empty($entered_otp)) {
        $message = 'Please enter the OTP.';
        $message_type = 'danger';
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_phone']) || !isset($_SESSION['otp_time'])) {
        $message = 'Session expired. Please request a new OTP.';
        $message_type = 'danger';
    } elseif (time() - $_SESSION['otp_time'] > 300) { // 5 minutes expiry
        $message = 'OTP has expired. Please request a new OTP.';
        $message_type = 'danger';
        // Clear expired session
        unset($_SESSION['otp']);
        unset($_SESSION['otp_phone']);
        unset($_SESSION['otp_time']);
    } elseif ($entered_otp != $_SESSION['otp']) {
        $message = 'Invalid OTP. Please try again.';
        $message_type = 'danger';
    } else {
        // OTP verified successfully
        $_SESSION['otp_verified'] = true;
        header("Location: reset_password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Mess Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Poppins', sans-serif; }
        .forgot-password-card { background: white; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); padding: 2rem; width: 100%; max-width: 400px; }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        .logo i { font-size: 3rem; color: #6f42c1; }
        .form-control:focus { border-color: #6f42c1; box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25); }
        .btn-primary { background-color: #6f42c1; border-color: #6f42c1; }
        .btn-primary:hover { background-color: #5a32a3; border-color: #5a32a3; }
    </style>
</head>
<body>
    <div class="forgot-password-card">
        <div class="logo">
            <i class="bi bi-people-fill"></i>
            <h3>Mess Management</h3>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_SESSION['otp'])): ?>
            <!-- Phone Number Form -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="Enter your 10-digit phone number" required>
                    <div class="form-text">Enter your registered phone number</div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" name="send_otp" class="btn btn-primary btn-lg">
                        <i class="bi bi-send me-1"></i>
                        Send OTP
                    </button>
                </div>
                <div class="text-center">
                    Remember your password? <a href="login.php" class="text-decoration-none">Login</a>
                </div>
            </form>
        <?php else: ?>
            <!-- OTP Verification Form -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Enter OTP</label>
                    <input type="text" class="form-control" name="otp" placeholder="Enter 6-digit OTP" required>
                    <div class="form-text">OTP sent to <?php echo htmlspecialchars($_SESSION['otp_phone']); ?></div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" name="verify_otp" class="btn btn-primary btn-lg">
                        <i class="bi bi-shield-check me-1"></i>
                        Verify OTP
                    </button>
                </div>
                <div class="text-center">
                    Didn't receive OTP? <a href="forgot_password.php" class="text-decoration-none">Resend</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-hide alerts after 5 seconds -->
    <script>
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>