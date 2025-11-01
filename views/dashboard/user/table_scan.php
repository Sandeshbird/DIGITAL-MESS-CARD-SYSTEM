<?php
// views/dashboard/user/table_scan.php - Enhanced Table Scan
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$message = '';
$success = false;

// Check if user has an active plan
$plan_stmt = $db->prepare("SELECT sp.*, p.plan_name FROM student_plans sp JOIN plans p ON sp.plan_id = p.id WHERE sp.user_id = ? AND sp.status = 'active'");
$plan_stmt->execute([$user_id]);
$active_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);

if (!$active_plan) {
    header("Location: my_plan.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_table'])) {
    $table_qr = $_POST['table_qr'];
    $meal_type = $_POST['meal_type'];
    $credits_needed = (int)$_POST['credits_needed'];
    
    // Validate table QR exists
    $table_stmt = $db->prepare("SELECT * FROM tables WHERE t_qr = ?");
    $table_stmt->execute([$table_qr]);
    $table = $table_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$table) {
        $message = 'Invalid table QR code.';
    } else {
        // Check if table is available
        if ($table['status'] !== 'available') {
            $message = 'Table is not available.';
        } else {
            // Check if user has enough credits for the meal type
            $remaining_field = $meal_type . '_remaining';
            $remaining_credits = $active_plan[$remaining_field];
            
            if ($remaining_credits < $credits_needed) {
                $message = 'Not enough credits for ' . ucfirst($meal_type) . '. You have ' . $remaining_credits . ' credits remaining.';
            } else {
                // Create temporary QR code
                $temp_qr = 'TEMP_' . uniqid() . '_' . $user_id . '_' . time();
                
                // Create order
                $order_stmt = $db->prepare("INSERT INTO orders (table_id, user_id, total_amount, status, order_time) VALUES (?, ?, 0, 'pending', NOW())");
                $order_result = $order_stmt->execute([
                    $table['id'], $user_id
                ]);
                
                if ($order_result) {
                    $order_id = $db->lastInsertId();
                    
                    // Update table status
                    $update_table = $db->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
                    $update_table->execute([$table['id']]);
                    
                    // Generate temporary QR for waiter
                    $message = "Temporary QR: <strong>$temp_qr</strong><br>Waiter will be notified. Show this QR to the waiter.";
                    $success = true;
                } else {
                    $message = 'Failed to create order.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Table - Mess Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding-top: 56px; font-family: 'Poppins', sans-serif; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; margin-bottom: 1.5rem; }
        .qr-placeholder { background: white; border: 2px dashed #ccc; border-radius: 1rem; height: 300px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .qr-placeholder i { font-size: 4rem; color: #ccc; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Mess Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">Dashboard</a>
                <a class="nav-link text-white" href="../../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Scan Table QR Code</h4>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Scan Table QR</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Table QR Code</label>
                                            <input type="text" class="form-control" name="table_qr" placeholder="Enter table QR code" required>
                                            <div class="form-text">Scan the QR code on the table or enter it manually</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Meal Type</label>
                                            <select class="form-select" name="meal_type" required>
                                                <option value="breakfast">Breakfast</option>
                                                <option value="lunch">Lunch</option>
                                                <option value="dinner">Dinner</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Credits Needed (1-4)</label>
                                            <input type="number" class="form-control" name="credits_needed" min="1" max="4" value="1" required>
                                            <div class="form-text">How many people will be dining? (Max 4)</div>
                                        </div>
                                        
                                        <button type="submit" name="scan_table" class="btn btn-primary w-100">Scan & Generate QR</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Your Plan Credits</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <h4 class="text-primary"><?php echo $active_plan['breakfast_remaining']; ?></h4>
                                            <p class="text-muted">Breakfast Remaining</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-success"><?php echo $active_plan['lunch_remaining']; ?></h4>
                                            <p class="text-muted">Lunch Remaining</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-warning"><?php echo $active_plan['dinner_remaining']; ?></h4>
                                            <p class="text-muted">Dinner Remaining</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-qr-code" style="font-size: 4rem; color: var(--success-color);"></i>
                                    <h4 class="mt-3">Temporary QR Generated!</h4>
                                    <p class="text-muted mb-4">Show this QR code to the waiter</p>
                                    
                                    <div class="alert alert-success">
                                        <h5>Your Temporary QR Code:</h5>
                                        <h3 class="text-success"><?php echo $_POST['table_qr'] ?? 'TEMP_123456'; ?></h3>
                                        <p class="mb-0">Waiter will be notified automatically</p>
                                    </div>
                                    
                                    <a href="table_scan.php" class="btn btn-primary">Scan Another Table</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>