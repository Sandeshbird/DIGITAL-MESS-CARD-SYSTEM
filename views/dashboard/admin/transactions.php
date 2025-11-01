<?php
// views/dashboard/admin/transactions.php - Enhanced with UTR Display
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all transactions
$transactions_stmt = $db->prepare("SELECT t.*, u.username FROM transactions t JOIN user u ON t.user_id = u.id ORDER BY t.t_time DESC");
$transactions_stmt->execute();
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transactions - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                <h4>Transaction Management</h4>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Transactions</h5>
                                <span class="badge bg-primary"><?php echo count($transactions); ?> Transactions</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($transactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Transaction ID</th>
                                                    <th>User</th>
                                                    <th>Amount</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $trans): ?>
                                                    <tr>
                                                        <td>#<?php echo $trans['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($trans['username']); ?></td>
                                                        <td>₹<?php echo number_format($trans['amount'], 2); ?></td>
                                                        <td>
                                                            <?php 
                                                            // Extract UTR if present in t_type
                                                            if (strpos($trans['t_type'], 'UTR:') !== false) {
                                                                echo 'UPI';
                                                                $utr_parts = explode('UTR:', $trans['t_type']);
                                                                if (isset($utr_parts[1])) {
                                                                    echo '<br><small class="text-muted">UTR: ' . htmlspecialchars(trim($utr_parts[1])) . '</small>';
                                                                }
                                                            } else {
                                                                echo htmlspecialchars($trans['t_type']);
                                                            }
                                                            ?>
                                                        </td>
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
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-receipt" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="mt-3">No Transactions Found</h5>
                                        <p class="text-muted">Transactions will appear here after plans are assigned</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>