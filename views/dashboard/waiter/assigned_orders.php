<?php
// views/dashboard/waiter/assigned_orders.php - Waiter Assigned Orders
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Waiter';
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get waiter's assigned orders
$assigned_orders = [];
try {
    $stmt = $db->prepare("SELECT wo.*, o.*, t.t_name, u.username FROM waiter_orders wo JOIN orders o ON wo.order_id = o.id JOIN tables t ON o.table_id = t.id JOIN user u ON o.user_id = u.id WHERE wo.waiter_id = ? AND wo.status = 'preparing' ORDER BY wo.assigned_at DESC");
    $stmt->execute([$user_id]);
    $assigned_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assigned_orders = [];
}

$content = "
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-12'>
                <div class='page-title-box'>
                    <div class='page-title-right'>
                        <ol class='breadcrumb m-0'>
                            <li class='breadcrumb-item'><a href='index.php'>Dashboard</a></li>
                            <li class='breadcrumb-item active'>Assigned Orders</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>Assigned Orders</h4>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-lg-12'>
                <div class='card'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-list-check me-2'></i>
                            Currently Assigned Orders
                        </h5>
                        <span class='badge bg-primary'>" . count($assigned_orders) . " Orders</span>
                    </div>
                    <div class='card-body'>
                        " . (empty($assigned_orders) ? "
                            <div class='text-center py-5'>
                                <i class='bi bi-list-check' style='font-size: 3rem; color: #ccc;'></i>
                                <h5 class='mt-3'>No assigned orders</h5>
                                <p class='text-muted'>Orders will be assigned to you automatically</p>
                                <a href='index.php' class='btn btn-primary'>Back to Dashboard</a>
                            </div>
                        " : "
                            <div class='table-responsive'>
                                <table class='table table-hover'>
                                    <thead>
                                        <tr>
                                            <th>Table</th>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Assigned Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ") . "
";
if (!empty($assigned_orders)) {
    foreach ($assigned_orders as $order) {
        $content .= "
                                        <tr>
                                            <td>Table " . htmlspecialchars($order['t_name']) . "</td>
                                            <td>" . htmlspecialchars($order['username']) . "</td>
                                            <td>₹" . number_format($order['total_amount'], 2) . "</td>
                                            <td><span class='badge bg-warning'>" . htmlspecialchars(ucfirst($order['status'])) . "</span></td>
                                            <td>" . date('h:i A', strtotime($order['assigned_at'])) . "</td>
                                            <td>
                                                <a href='orders.php' class='btn btn-primary btn-sm'>Manage</a>
                                            </td>
                                        </tr>
        ";
    }
    $content .= "
                                    </tbody>
                                </table>
                            </div>
        ";
}
$content .= "
                    </div>
                </div>
            </div>
        </div>

        <div class='row mt-4'>
            <div class='col-lg-12'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-info-circle me-2'></i>
                            Order Management Instructions
                        </h5>
                    </div>
                    <div class='card-body'>
                        <ul>
                            <li><strong>Prepare Orders:</strong> Assigned orders will appear here</li>
                            <li><strong>Manage Orders:</strong> Click 'Manage' to serve or cancel orders</li>
                            <li><strong>Table Status:</strong> Tables update automatically when orders are processed</li>
                            <li><strong>New Assignments:</strong> New orders are automatically assigned to you</li>
                        </ul>
                        <p class='mb-0'><strong>Tip:</strong> Check this page regularly for new order assignments.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
";

include '../../layouts/app.php';
?>