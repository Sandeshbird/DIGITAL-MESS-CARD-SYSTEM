<?php
// views/dashboard/waiter/index.php - waiter dashboard with working built-in camera scanner

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
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

// Handle manual QR scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_qr'])) {
    $qr_code = trim($_POST['qr_code'] ?? '');
    if ($qr_code === '') {
        $message = 'Please enter or scan a QR code.';
        $message_type = 'danger';
    } elseif (strpos($qr_code, 'TEMP_') === 0) {
        $_SESSION['temp_qr'] = $qr_code;
        $_SESSION['qr_scanned_at'] = time();
        header("Location: orders.php");
        exit;
    } else {
        $message = 'Invalid QR format.';
        $message_type = 'danger';
    }
}

// Fetch waiter stats
$assigned_orders = $completed_orders = $pending_orders = 0;
try {
    $assigned_orders = (int)$db->query("SELECT COUNT(*) FROM waiter_orders WHERE waiter_id = {$user_id} AND status = 'preparing'")->fetchColumn();
    $completed_orders = (int)$db->query("SELECT COUNT(*) FROM waiter_orders WHERE waiter_id = {$user_id} AND status = 'served'")->fetchColumn();
    $pending_orders = (int)$db->query("SELECT COUNT(*) FROM waiter_orders WHERE waiter_id = {$user_id} AND status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Waiter dashboard stats error: " . $e->getMessage());
}

ob_start();
?>

<div class="container-fluid">

  <!-- Page Header -->
  <div class="page-title-box d-flex justify-content-between align-items-center">
    <div>
      <h3 class="mb-1">Welcome, <?= htmlspecialchars($user_name); ?> 👋</h3>
      <p class="text-muted mb-0">Manage your orders and tables efficiently.</p>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-4 stats-grid">
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="d-flex align-items-center">
          <div class="stat-icon"><i class="bi bi-list-check"></i></div>
          <div>
            <h4 class="mb-0"><?= $assigned_orders; ?></h4>
            <small class="text-muted">Active Orders</small>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="d-flex align-items-center">
          <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
          <div>
            <h4 class="mb-0"><?= $completed_orders; ?></h4>
            <small class="text-muted">Completed</small>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="d-flex align-items-center">
          <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
          <div>
            <h4 class="mb-0"><?= $pending_orders; ?></h4>
            <small class="text-muted">Pending</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- QR Scan + Quick Actions -->
  <div class="row g-3 mt-3 align-items-stretch">
    <!-- Quick QR Scan -->
    <div class="col-lg-6 d-flex">
      <div class="card w-100 qr-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div><i class="bi bi-qr-code-scan me-2 text-muted"></i><strong>Quick QR Scan</strong></div>
        </div>
        <div class="card-body">
          <form method="post" class="needs-validation" novalidate>
            <div class="mb-3">
              <label class="form-label">Enter or Scan QR Code</label>
              <div class="input-group">
                <input type="text" name="qr_code" id="qr_input" class="form-control" placeholder="TEMP_XXXXXX" required>
                <button type="button" id="openCameraBtn" class="btn btn-outline-secondary" title="Open Camera">
                  <i class="bi bi-camera"></i>
                </button>
              </div>
              <div class="invalid-feedback">Please enter QR code</div>
            </div>
            <button class="btn btn-brand w-100" name="scan_qr" type="submit">
              <i class="bi bi-qr-code-scan me-1"></i> Scan
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-6 d-flex">
      <div class="card w-100 quick-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div><i class="bi bi-lightning-charge me-2 text-muted"></i><strong>Quick Actions</strong></div>
        </div>
        <div class="card-body d-flex flex-column justify-content-center">
          <div class="row g-3 text-center">
            <div class="col-6">
              <a href="orders.php" class="text-decoration-none d-block">
                <div>
                  <i class="bi bi-list-check" style="font-size:1.6rem;color:var(--brand);"></i>
                  <div class="mt-2 text-dark fw-medium">Orders</div>
                </div>
              </a>
            </div>
            <div class="col-6">
              <a href="tables.php" class="text-decoration-none d-block">
                <div>
                  <i class="bi bi-table" style="font-size:1.6rem;color:var(--brand);"></i>
                  <div class="mt-2 text-dark fw-medium">Tables</div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Camera Modal -->
  <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Scan QR Code</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <video id="cameraPreview" autoplay playsinline style="width:100%;border-radius:10px;background:#000;"></video>
          <canvas id="captureCanvas" style="display:none;"></canvas>
          <p class="text-muted mt-2 mb-0">Align the QR code within the frame to scan.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="stopCameraBtn">Close</button>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- jsQR Library -->
<script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>

<script>
(() => {
  const openCameraBtn = document.getElementById('openCameraBtn');
  const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
  const video = document.getElementById('cameraPreview');
  const canvas = document.getElementById('captureCanvas');
  const ctx = canvas.getContext('2d');
  const qrInput = document.getElementById('qr_input');
  let stream = null;
  let scanning = false;

  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream;
      video.play();
      scanning = true;
      requestAnimationFrame(tick);
    } catch (err) {
      alert('Unable to access camera. Please allow permissions.');
    }
  }

  function stopCamera() {
    scanning = false;
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      stream = null;
    }
  }

  function tick() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.height = video.videoHeight;
      canvas.width = video.videoWidth;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });
      if (code) {
        qrInput.value = code.data;
        stopCamera();
        modal.hide();
      }
    }
    if (scanning) requestAnimationFrame(tick);
  }

  openCameraBtn.addEventListener('click', () => {
    modal.show();
    startCamera();
  });

  document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopCamera);
})();
</script>

<style>
:root {
  --brand: #ffb26b;
  --brand-dark: #f2a75a;
}
.stat-icon i { font-size: 1.8rem; color: var(--brand); margin-right: .8rem; }
.card { border:none; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.04); }
.btn-brand { background:var(--brand); border:none; color:#fff; border-radius:8px; font-weight:500; }
.btn-brand:hover { background:var(--brand-dark); color:#fff; }
.qr-card, .quick-card { height:100%; }
</style>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>
