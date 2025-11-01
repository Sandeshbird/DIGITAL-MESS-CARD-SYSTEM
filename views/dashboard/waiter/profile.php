<?php
// views/dashboard/waiter/profile.php - Waiter Profile
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

// Get user details
$user_details = null;
try {
    $query = "SELECT * FROM user WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_details = null;
}

// Get assigned tables count
$assigned_tables = 0;
try {
    $query = "SELECT COUNT(*) as count FROM waiter_orders WHERE waiter_id = :waiter_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':waiter_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $assigned_tables = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $assigned_tables = 0;
}

// Get completed orders count
$completed_orders = 0;
try {
    $query = "SELECT COUNT(*) as count FROM waiter_orders WHERE waiter_id = :waiter_id AND status = 'served'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':waiter_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $completed_orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $completed_orders = 0;
}

$content = "
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-12'>
                <div class='page-title-box'>
                    <div class='page-title-right'>
                        <ol class='breadcrumb m-0'>
                            <li class='breadcrumb-item'><a href='index.php'>Dashboard</a></li>
                            <li class='breadcrumb-item active'>Profile</li>
                        </ol>
                    </div>
                    <h4 class='page-title'>My Profile</h4>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-lg-8'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>Profile Information</h5>
                    </div>
                    <div class='card-body'>
";
if ($user_details) {
    $status_badge = $user_details['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-warning">Inactive</span>';
    $content .= "
        <div class='table-responsive'>
            <table class='table table-borderless'>
                <tr><th scope='row' class='w-25'>User ID:</th><td>" . htmlspecialchars($user_details['id']) . "</td></tr>
                <tr><th scope='row'>Username:</th><td>" . htmlspecialchars($user_details['username']) . "</td></tr>
                <tr><th scope='row'>Full Name:</th><td>" . htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']) . "</td></tr>
                <tr><th scope='row'>Email:</th><td>" . htmlspecialchars($user_details['email']) . "</td></tr>
                <tr><th scope='row'>Phone Number:</th><td>" . htmlspecialchars($user_details['ph_no']) . "</td></tr>
                <tr><th scope='row'>Role:</th><td>" . htmlspecialchars(ucfirst($user_details['role'])) . "</td></tr>
                <tr><th scope='row'>Status:</th><td>$status_badge</td></tr>
                <tr><th scope='row'>Gender:</th><td>" . htmlspecialchars($user_details['gender'] ?? 'Not specified') . "</td></tr>
                <tr><th scope='row'>Member Since:</th><td>" . htmlspecialchars($user_details['created_at']) . "</td></tr>
            </table>
        </div>
    ";
} else {
    $content .= "<p class='text-center text-muted'>Unable to load profile information.</p>";
}
$content .= "
                    </div>
                </div>
            </div>

            <div class='col-lg-4'>
                <div class='card'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>Work Statistics</h5>
                    </div>
                    <div class='card-body'>
                        <div class='text-center mb-3'>
                            <i class='bi bi-person-circle' style='font-size: 3rem; color: #6f42c1;'></i>
                            <h5 class='mt-2'>" . htmlspecialchars($user_name) . "</h5>
                            <p class='text-muted'>Waiter</p>
                        </div>
                        
                        <div class='d-grid gap-2'>
                            <div class='d-flex justify-content-between align-items-center p-2 bg-light rounded'>
                                <span>Assigned Tables:</span>
                                <span class='badge bg-primary'>" . $assigned_tables . "</span>
                            </div>
                            <div class='d-flex justify-content-between align-items-center p-2 bg-light rounded'>
                                <span>Completed Orders:</span>
                                <span class='badge bg-success'>" . $completed_orders . "</span>
                            </div>
                            <div class='d-flex justify-content-between align-items-center p-2 bg-light rounded'>
                                <span>Performance Rating:</span>
                                <span class='badge bg-warning'>4.8 ★</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='card mt-4'>
                    <div class='card-header'>
                        <h5 class='card-title mb-0'>Quick Actions</h5>
                    </div>
                    <div class='card-body'>
                        <div class='d-grid gap-2'>
                            <a href='orders.php' class='btn btn-primary'>
                                <i class='bi bi-list-check me-1'></i>
                                Manage Orders
                            </a>
                            <a href='tables.php' class='btn btn-success'>
                                <i class='bi bi-table me-1'></i>
                                View Tables
                            </a>
                            <a href='assigned_orders.php' class='btn btn-info'>
                                <i class='bi bi-person-lines-fill me-1'></i>
                                Assigned Orders
                            </a>
                            <a href='completed_orders.php' class='btn btn-secondary'>
                                <i class='bi bi-check-circle me-1'></i>
                                Completed Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
";

include '../../layouts/app.php';
?>  