<?php
// views/dashboard/waiter/orders.php — Waiter Orders Management with Create/Delete buttons
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Waiter';

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

/* ---- Fetch Orders ---- */
function fetchOrders($db, $user_id, $status) {
    $sql = "
      SELECT wo.*, o.*, t.t_name, u.username
      FROM waiter_orders wo
      JOIN orders o ON wo.order_id = o.id
      JOIN tables t ON o.table_id = t.id
      JOIN user u ON o.user_id = u.id
      WHERE wo.waiter_id = ? AND wo.status = ?
      ORDER BY wo.assigned_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$assigned_orders = fetchOrders($db, $user_id, 'preparing');
$completed_orders = fetchOrders($db, $user_id, 'served');
$cancelled_orders = fetchOrders($db, $user_id, 'cancelled');

/* ---- Page Layout ---- */
ob_start();
?>

<style>
:root {
  --brand: #ffb26b;
  --brand-dark: #f2a75a;
}
.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.05);
}
.page-title-box h4 { font-weight: 600; color: var(--text-color,#333); }
.btn-brand {
  background: var(--brand); border:none; color:#fff;
  border-radius:8px; transition:all .2s;
}
.btn-brand:hover { background: var(--brand-dark); color:#fff; }
.table th { background:#f9fafb; font-weight:600; border-bottom:2px solid #eee; }
.table-hover tbody tr:hover { background:rgba(255,178,107,0.08); }
[data-theme="dark"] .card,
[data-theme="dark"] .table,
[data-theme="dark"] .page-title-box h4,
[data-theme="dark"] .card h5,
[data-theme="dark"] td,
[data-theme="dark"] th {
  color:#fff !important;
  background:#1f1f1f !important;
}
</style>

<div class="container-fluid">
  <div class="page-title-box d-flex align-items-center justify-content-between">
    <h4 class="page-title mb-0">Manage Orders</h4>
    <div>
      <a href="create.php" class="btn btn-sm btn-success me-2">
        <i class="bi bi-plus-circle me-1"></i> Create New Order
      </a>
      <a href="index.php" class="btn btn-sm btn-brand">
        <i class="bi bi-house me-1"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Nav Tabs -->
  <ul class="nav nav-tabs mt-3" id="orderTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#assigned" type="button">Assigned Orders</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#completed" type="button">Completed Orders</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cancelled" type="button">Cancelled Orders</button></li>
  </ul>

  <div class="tab-content pt-3">
    <!-- Assigned Orders -->
    <div class="tab-pane fade show active" id="assigned">
      <?php if (empty($assigned_orders)): ?>
        <div class="text-center py-5">
          <i class="bi bi-list-check" style="font-size:3rem;color:#ccc;"></i>
          <h5 class="mt-3 mb-1">No Assigned Orders</h5>
          <p class="text-muted">Orders will be assigned automatically when available.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Table</th><th>User</th><th>Amount</th><th>Status</th><th>Assigned Time</th><th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assigned_orders as $order): ?>
              <tr>
                <td><strong>Table <?= htmlspecialchars($order['t_name']); ?></strong></td>
                <td><?= htmlspecialchars($order['username']); ?></td>
                <td>₹<?= number_format($order['total_amount'],2); ?></td>
                <td><span class="badge bg-warning text-dark"><?= ucfirst($order['status']); ?></span></td>
                <td><?= date('h:i A', strtotime($order['assigned_at'])); ?></td>
                <td class="text-end">
                  <a href="update.php?id=<?= $order['order_id']; ?>&action=serve" class="btn btn-success btn-sm">
                    <i class="bi bi-check2-circle"></i>
                  </a>
                  <form method="POST" action="delete.php" class="d-inline">
                    <input type="hidden" name="order_id" value="<?= $order['order_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this order?')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Completed Orders -->
    <div class="tab-pane fade" id="completed">
      <?php if (empty($completed_orders)): ?>
        <p class="text-center py-4 text-muted">No completed orders yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th>Table</th><th>User</th><th>Amount</th><th>Served At</th></tr></thead>
            <tbody>
              <?php foreach ($completed_orders as $order): ?>
              <tr>
                <td>Table <?= htmlspecialchars($order['t_name']); ?></td>
                <td><?= htmlspecialchars($order['username']); ?></td>
                <td>₹<?= number_format($order['total_amount'],2); ?></td>
                <td><?= date('M d, Y h:i A', strtotime($order['served_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Cancelled Orders -->
    <div class="tab-pane fade" id="cancelled">
      <?php if (empty($cancelled_orders)): ?>
        <p class="text-center py-4 text-muted">No cancelled orders.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th>Table</th><th>User</th><th>Amount</th><th>Cancelled At</th></tr></thead>
            <tbody>
              <?php foreach ($cancelled_orders as $order): ?>
              <tr>
                <td>Table <?= htmlspecialchars($order['t_name']); ?></td>
                <td><?= htmlspecialchars($order['username']); ?></td>
                <td>₹<?= number_format($order['total_amount'],2); ?></td>
                <td><?= date('M d, Y h:i A', strtotime($order['served_at'] ?? $order['assigned_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Instructions -->
  <div class="card mt-4">
    <div class="card-header"><i class="bi bi-info-circle me-2 text-muted"></i><strong>Order Instructions</strong></div>
    <div class="card-body">
      <ul>
        <li><strong>Serve Order:</strong> Click the ✅ icon when served.</li>
        <li><strong>Cancel Order:</strong> Trash icon deletes or cancels.</li>
        <li><strong>Create New:</strong> Use the green button above.</li>
        <li><strong>Auto Updates:</strong> Tables auto-refresh after actions.</li>
      </ul>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>
