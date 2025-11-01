<?php
// views/dashboard/admin/assign_plan.php - Enhanced with Select2 for User Search (Cleaned Links)
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$message = '';

// Get all users (students)
$users_stmt = $db->prepare("SELECT * FROM user WHERE role = 'user' ORDER BY id ASC");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all plans
$plans_stmt = $db->prepare("SELECT * FROM plans ORDER BY price ASC");
$plans_stmt->execute();
$plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$transactions_stmt = $db->prepare("SELECT t.*, u.username FROM transactions t JOIN user u ON t.user_id = u.id ORDER BY t.t_time DESC LIMIT 10");
$transactions_stmt->execute();
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle plan assignment with proper UTR handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_plan'])) {
    $user_id = (int)$_POST['user_id'];
    $plan_id = (int)$_POST['plan_id'];
    $payment_type = $_POST['payment_type'];
    $utr_number = trim($_POST['utr_number'] ?? '');

    // Validate UTR for UPI payments
    if ($payment_type === 'UPI' && empty($utr_number)) {
        $message = 'UTR number is required for UPI payments.';
    } else {
        // Check if user already has an active plan
        $check_stmt = $db->prepare("SELECT * FROM student_plans WHERE user_id = ? AND status = 'active'");
        $check_stmt->execute([$user_id]);
        $existing_plan = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_plan) {
            $message = 'This user already has an active plan!';
        } else {
            // Get plan details
            $plan_details_stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
            $plan_details_stmt->execute([$plan_id]);
            $plan = $plan_details_stmt->fetch(PDO::FETCH_ASSOC);

            if ($plan) {
                try {
                    // Begin transaction
                    $db->beginTransaction();

                    // Insert student plan
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+3 months'));

                    // Simplified query without missing columns
                    $insert_stmt = $db->prepare("INSERT INTO student_plans (user_id, plan_id, breakfast_remaining, lunch_remaining, dinner_remaining, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                    $result = $insert_stmt->execute([
                        $user_id, $plan_id,
                        $plan['breakfast_credits'],
                        $plan['lunch_credits'],
                        $plan['dinner_credits'],
                        $start_date, $end_date
                    ]);

                    if ($result) {
                        // Create transaction record with proper ENUM values only
                        $valid_payment_types = ['Card', 'Cash', 'UPI'];
                        $transaction_type = in_array($payment_type, $valid_payment_types) ? $payment_type : 'Cash';

                        $trans_stmt = $db->prepare("INSERT INTO transactions (user_id, t_time, t_type, amount, status) VALUES (?, NOW(), ?, ?, 'completed')");
                        $trans_stmt->execute([$user_id, $transaction_type, $plan['price']]);
                        $transaction_id = $db->lastInsertId();

                        // Commit transaction
                        $db->commit();

                        $message = 'Plan assigned successfully to user ID: ' . $user_id . ' with ' . $transaction_type . ' payment. Transaction ID: ' . $transaction_id;

                        // If UTR was provided, you could store it in a separate table or log it
                        if (!empty($utr_number)) {
                            error_log("UPI Transaction for user $user_id, Transaction ID: $transaction_id, UTR: $utr_number");
                        }
                    } else {
                        throw new Exception('Failed to assign plan.');
                    }
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    $message = 'Error: ' . $e->getMessage();
                }
            } else {
                $message = 'Invalid plan selected.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Plan - Admin</title>
    <!-- Removed trailing spaces in href attributes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Select2 Bootstrap 5 Theme -->
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Mess Management - Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="../../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4>Assign Meal Plan to Student</h4>

                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div> <!-- Sanitize output -->
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Assign Plan to Student</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="assignPlanForm">
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">Select ID</label> <!-- Added 'for' attribute -->
                                        <select class="form-select" id="user_id" name="user_id" required data-placeholder="Search for a student by ID or Name..."> <!-- Added ID, placeholder -->
                                            <option value="">Select a student...</option> <!-- Default option -->
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo (int)$user['id']; ?>"> <!-- Cast to int for security -->
                                                    <?php echo (int)$user['id']; ?> - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="plan_id" class="form-label">Select Plan</label> <!-- Added 'for' attribute -->
                                        <select class="form-select" id="plan_id" name="plan_id" required>
                                            <option value="">Choose a plan...</option>
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?php echo (int)$plan['id']; ?>"> <!-- Cast to int -->
                                                    <?php echo htmlspecialchars($plan['plan_name']); ?> - ₹<?php echo number_format($plan['price'], 2, '.', ''); ?> <!-- Ensure correct decimal separator for JS -->
                                                     (<?php echo (int)$plan['breakfast_credits']; ?>/<?php echo (int)$plan['lunch_credits']; ?>/<?php echo (int)$plan['dinner_credits']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="payment_type" class="form-label">Payment Type</label> <!-- Added 'for' attribute -->
                                        <select class="form-select" id="paymentType" name="payment_type" required>
                                            <option value="">Select payment method...</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Card">Card</option>
                                            <option value="UPI">UPI</option>
                                        </select>
                                    </div>

                                    <div class="mb-3" id="utrField" style="display: none;">
                                        <label for="utr_number" class="form-label">UTR Number (for UPI payments)</label> <!-- Added 'for' attribute -->
                                        <input type="text" class="form-control" id="utr_number" name="utr_number" placeholder="Enter UTR number">
                                        <div class="form-text">Unique Transaction Reference number for UPI payments</div>
                                    </div>

                                    <button type="submit" name="assign_plan" class="btn btn-primary w-100">Assign Plan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Transactions</h5>
                                <span class="badge bg-primary"><?php echo count($transactions); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($transactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Amount</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>User</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $trans): ?>
                                                    <tr>
                                                        <td>₹<?php echo number_format($trans['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($trans['t_type']); ?></td>
                                                        <td>
                                                            <?php if ($trans['status'] === 'completed'): ?>
                                                                <span class="badge bg-success">Completed</span>
                                                            <?php elseif ($trans['status'] === 'pending'): ?>
                                                                <span class="badge bg-warning">Pending</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Failed</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M d, Y h:i A', strtotime($trans['t_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($trans['username']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-receipt" style="font-size: 2rem; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">No transactions found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Available Plans</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($plans as $plan): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                                                    <p>
                                                        Breakfast: <?php echo (int)$plan['breakfast_credits']; ?><br>
                                                        Lunch: <?php echo (int)$plan['lunch_credits']; ?><br>
                                                        Dinner: <?php echo (int)$plan['dinner_credits']; ?><br>
                                                        Price: ₹<?php echo number_format($plan['price'], 2); ?>
                                                    </p>
                                                    <span class="badge <?php echo $plan['plan_type'] === 'Non-veg' ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo htmlspecialchars($plan['plan_type']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for user selection with search and placeholder
            $('#user_id').select2({
                theme: 'bootstrap-5', // Apply Bootstrap 5 theme
                placeholder: $(this).data('placeholder'), // Use the data-placeholder attribute
                allowClear: true, // Allow clearing the selection
                width: '100%' // Ensure full width
            });

            // Show/hide UTR field based on payment type
            $('#paymentType').on('change', function() {
                const utrField = $('#utrField');
                if (this.value === 'UPI') {
                    utrField.show();
                } else {
                    utrField.hide();
                    $('#utr_number').val(''); // Clear UTR field when switching away from UPI
                }
            });

            // Form submission handling
            $('#assignPlanForm').on('submit', function(e) {
                const paymentType = $('#paymentType').val();
                const utrField = $('#utrField');
                const utrInput = $('#utr_number');

                if (paymentType === 'UPI' && utrField.is(':visible') && utrInput.val().trim() === '') {
                    alert('Please enter UTR number for UPI payments');
                    utrInput.focus();
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>