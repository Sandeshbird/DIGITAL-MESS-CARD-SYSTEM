<?php
// views/dashboard/waiter/index.php - waiter dashboard with live camera QR scanner (Paytm-style)

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

// Handle QR scan
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

// Fetch stats
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

  <div class="page-title-box">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1">Welcome, <?= htmlspecialchars($user_name); ?> 👋</h3>
        <p class="text-muted mb-0">Manage your orders and tables efficiently.</p>
      </div>
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
          <div class="stat-icon stat-1"><i class="bi bi-list-check"></i></div>
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
          <div class="stat-icon stat-2"><i class="bi bi-check-circle"></i></div>
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
          <div class="stat-icon stat-3"><i class="bi bi-clock-history"></i></div>
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
        <div class="card-body d-flex flex-column justify-content-between">
          <form method="post" class="needs-validation" novalidate>
            <div class="mb-3 position-relative">
              <label class="form-label">Enter or Scan QR Code</label>
              <div class="input-group">
                <input type="text" name="qr_code" id="qr_input" class="form-control" placeholder="TEMP_XXXXXX" required>
                <button type="button" class="btn btn-brand" id="openCameraBtn" title="Open Camera">
                  <i class="bi bi-camera"></i>
                </button>
              </div>
              <div class="invalid-feedback">Please enter QR code</div>
            </div>
            <button class="btn btn-brand w-100 mt-auto" name="scan_qr" type="submit">
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

  <!-- CAMERA MODAL -->
  <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content camera-modal text-center">
        <div class="modal-header border-0">
          <h5 class="modal-title w-100">Scan QR Code</h5>
        </div>
        <div class="modal-body d-flex flex-column align-items-center justify-content-center">
          <div class="camera-circle">
            <video id="cameraPreview" autoplay playsinline muted></video>
            <div class="scanner-border"></div>
          </div>
          <p class="text-light small mt-3 mb-0">Align the QR inside the frame</p>
        </div>
        <div class="modal-footer border-0 justify-content-center">
          <button type="button" class="btn btn-outline-light px-4" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i> Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <canvas id="captureCanvas" hidden></canvas>
</div>

<!-- JS QR Library -->
<script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>

<!-- Camera QR Script -->
<script>
(() => {
  const openCameraBtn = document.getElementById('openCameraBtn');
  const qrInput = document.getElementById('qr_input');
  const modalEl = document.getElementById('cameraModal');
  const modal = new bootstrap.Modal(modalEl);
  const video = document.getElementById('cameraPreview');
  const canvas = document.getElementById('captureCanvas');
  const ctx = canvas.getContext('2d');
  let stream = null;
  let scanning = false;
  const beep = new Audio("https://cdn.pixabay.com/download/audio/2022/03/15/audio_2c4a0f4b92.mp3?filename=beep-6-96243.mp3");

  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
      video.srcObject = stream;
      await video.play();
      scanning = true;
      requestAnimationFrame(scanLoop);
    } catch (err) {
      alert("Camera access denied or unavailable.");
      console.error(err);
      stopCamera();
      modal.hide();
    }
  }

  function stopCamera() {
    scanning = false;
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
  }

  function scanLoop() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });
      if (code) {
        qrInput.value = code.data;
        beep.play();
        document.querySelector('.scanner-border').style.borderColor = "#4caf50";
        document.querySelector('.scanner-border').style.boxShadow = "0 0 20px #4caf50";
        setTimeout(() => {
          stopCamera();
          modal.hide();
        }, 600);
      }
    }
    requestAnimationFrame(scanLoop);
  }

  openCameraBtn?.addEventListener('click', () => {
    modal.show();
    startCamera();
  });

  modalEl.addEventListener('hidden.bs.modal', stopCamera);
})();
</script>

<style>
:root {
  --brand: #ffb26b;
  --brand-dark: #f2a75a;
}
.qr-card, .quick-card { height: 100%; }
.btn-brand {
  background: var(--brand);
  color: #fff;
  border: none;
  border-radius: 8px;
}
.btn-brand:hover { background: var(--brand-dark); }

/* Camera modal style */
.camera-modal {
  background: rgba(0, 0, 0, 0.88);
  color: #fff;
  border-radius: 18px;
  backdrop-filter: blur(8px);
}
.camera-circle {
  position: relative;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  overflow: hidden;
}
.camera-circle video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}
.scanner-border {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 3px solid rgba(255,178,107,0.8);
  animation: pulseGlow 1.8s infinite ease-in-out;
  box-shadow: 0 0 12px rgba(255,178,107,0.8);
}
@keyframes pulseGlow {
  0%,100% { box-shadow:0 0 8px rgba(255,178,107,0.9); }
  50% { box-shadow:0 0 18px rgba(255,178,107,1); }
}
</style>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>
