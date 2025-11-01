<?php
// views/dashboard/waiter/completed_orders.php - Waiter Completed Orders
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

// Get waiter's completed orders
$completed_orders = [];
try {
    $stmt = $db->prepare("SELECT wo.*, o.*, t.t_name, u.username FROM waiter_orders wo JOIN orders o ON wo.order_id = o.id JOIN tables t ON o.table_id = t.id JOIN user u ON o.user_id = u.id WHERE wo.waiter_id = ? AND wo.status = 'served' ORDER BY wo.served_at DESC");
    $stmt->execute([$user_id]);
    $completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $completed_orders = [];
}

// Get today's completed orders
$todays_completed = [];
try {
    $stmt = $db->prepare("SELECT wo.*, o.*, t.t_name, u.username FROM waiter_orders wo JOIN orders o ON wo.order_id = o.id JOIN tables t ON o.table_id = t.id JOIN user u ON o.user_id = u.id WHERE wo.waiter_id = ? AND wo.status = 'served' AND DATE(wo.served_at) = CURDATE() ORDER BY wo.served_at DESC");
    $stmt->execute([$user_id]);
    $todays_completed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todays_completed = [];
}

$content = "
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-12'>
                <div class='page-title-box'>
                    <div class='page-title-right'>
                        <ol class='breadcrumb m-0'>
                            <li class='breadcrumb-item'><a href='index.php'>Dashboard</a></li>
                            <li class='breadcrumb-item active'>Completed Orders</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>Completed Orders</h4>
                </div>
            </div>
        </div>

        <div class='row mb-4'>
            <div class='col-md-6 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #28a745, #20c997); color: white;'>
                    <div class='content'>
                        <i class='bi bi-check-circle d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>" . count($completed_orders) . "</h5>
                        <p class='mb-0'>Total Completed</p>
                    </div>
                </div>
            </div>
            <div class='col-md-6 mb-3'>
                <div class='card stat-card' style='background: linear-gradient(45deg, #ffc107, #fd7e14); color: white;'>
                    <div class='content'>
                        <i class='bi bi-clock-history d-block' style='font-size: 2.5rem;'></i>
                        <h5 class='mt-2'>" . count($todays_completed) . "</h5>
                        <p class='mb-0'>Today's Completed</p>
                    </div>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-lg-12'>
                <div class='card'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h5 class='card-title mb-0'>
                            <i class='bi bi-check-circle me-2'></i>
                            All Completed Orders
                        </h5>
                        <span class='badge bg-primary'>" . count($completed_orders) . " Orders</span>
                    </div>
                    <div class='card-body'>
                        " . (empty($completed_orders) ? "
                            <div class='text-center py-5'>
                                <i class='bi bi-check-circle' style='font-size: 3rem; color: #ccc;'></i>
                                <h5 class='mt-3'>No completed orders</h5>
                                <p class='text-muted'>Completed orders will appear here after serving</p>
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
                                            <th>Served Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ") . "
";
if (!empty($completed_orders)) {
    foreach ($completed_orders as $order) {
        $content .= "
                                        <tr>
                                            <td>Table " . htmlspecialchars($order['t_name']) . "</td>
                                            <td>" . htmlspecialchars($order['username']) . "</td>
                                            <td>₹" . number_format($order['total_amount'], 2) . "</td>
                                            <td><span class='badge bg-success'>" . htmlspecialchars(ucfirst($order['status'])) . "</span></td>
                                            <td>" . date('M d, Y h:i A', strtotime($order['served_at'])) . "</td>
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
                            Today's Completed Orders (" . count($todays_completed) . ")
                        </h5>
                    </div>
                    <div class='card-body'>
                        " . (empty($todays_completed) ? "
                            <p class='text-center text-muted'>No completed orders today</p>
                        " : "
                            <div class='table-responsive'>
                                <table class='table table-hover'>
                                    <thead>
                                        <tr>
                                            <th>Table</th>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Served Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ") . "
";
if (!empty($todays_completed)) {
    foreach ($todays_completed as $order) {
        $content .= "
                                        <tr>
                                            <td>Table " . htmlspecialchars($order['t_name']) . "</td>
                                            <td>" . htmlspecialchars($order['username']) . "</td>
                                            <td>₹" . number_format($order['total_amount'], 2) . "</td>
                                            <td>" . date('h:i A', strtotime($order['served_at'])) . "</td>
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
    </div>
";

include '../../layouts/app.php';
?>