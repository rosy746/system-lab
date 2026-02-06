<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

$pdo = getDB();

// ============================================================
// API ENDPOINTS
// ============================================================

// 1) GET ?ajax=list&filter=pending|approved|rejected|all
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: application/json');
    try {
        $filter = $_GET['filter'] ?? 'pending';
        $allowed = ['pending', 'approved', 'rejected', 'all'];
        if (!in_array($filter, $allowed)) $filter = 'pending';

        $whereStatus = '';
        if ($filter !== 'all') {
            $whereStatus = "AND b.status = '$filter'";
        }

        $sql = "SELECT b.*,
                       ts.name as time_slot_name, ts.start_time, ts.end_time,
                       r.name as lab_name, r.capacity as lab_capacity,
                       o.name as institution_name, o.type as institution_type
                FROM bookings b
                JOIN time_slots ts ON b.time_slot_id = ts.id
                JOIN resources r   ON b.resource_id  = r.id
                JOIN organizations o ON b.organization_id = o.id
                WHERE r.type = 'lab'
                  AND r.status = 'active'
                  $whereStatus
                ORDER BY
                  FIELD(b.status, 'pending', 'approved', 'rejected'),
                  b.created_at ASC";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung summary
        $summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        $sqlSum = "SELECT bookings.status as status, COUNT(*) as cnt FROM bookings
                   JOIN resources r ON bookings.resource_id = r.id
                   WHERE r.type='lab' AND r.status='active'
                     AND bookings.status IN ('pending','approved','rejected')
                   GROUP BY bookings.status";
        foreach ($pdo->query($sqlSum)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[$row['status']] = (int)$row['cnt'];
        }

        echo json_encode(['success' => true, 'data' => $rows, 'summary' => $summary]);
    } catch (PDOException $e) {
        error_log("Approval list error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2) POST aksi=approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'approve') {
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['booking_id'] ?? 0);
        if ($id === 0) throw new Exception("ID booking tidak valid.");

        $pdo->beginTransaction();

        // Pastikan masih pending
        $check = $pdo->prepare("SELECT id, status, title FROM bookings WHERE id = :id AND status = 'pending'");
        $check->execute([':id' => $id]);
        $booking = $check->fetch(PDO::FETCH_ASSOC);
        if (!$booking) throw new Exception("Booking tidak ditemukan atau sudah diproses.");

        // Update status + approved_by + approved_at
        $upd = $pdo->prepare("UPDATE bookings SET status = 'approved', approved_by = 1, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $upd->execute([':id' => $id]);

        // Activity log
        $log = $pdo->prepare("INSERT INTO activity_logs (user_name, action, entity_type, entity_id, description, created_at)
                              VALUES ('Admin', 'APPROVE', 'booking', :eid, :desc, CURRENT_TIMESTAMP)");
        $log->execute([':eid' => $id, ':desc' => 'Booking disetujui: ' . $booking['title']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Booking berhasil disetujui.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3) POST aksi=reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'reject') {
    header('Content-Type: application/json');
    try {
        $id     = intval($_POST['booking_id'] ?? 0);
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($id === 0) throw new Exception("ID booking tidak valid.");
        if ($reason === '') throw new Exception("Alasan penolakan wajib diisi.");

        $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT id, status, title FROM bookings WHERE id = :id AND status = 'pending'");
        $check->execute([':id' => $id]);
        $booking = $check->fetch(PDO::FETCH_ASSOC);
        if (!$booking) throw new Exception("Booking tidak ditemukan atau sudah diproses.");

        // Update status + simpan alasan di kolom notes
        $upd = $pdo->prepare("UPDATE bookings SET status = 'rejected',
                              notes = :reason,
                              updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $upd->execute([':id' => $id, ':reason' => $reason]);

        // Activity log
        $log = $pdo->prepare("INSERT INTO activity_logs (user_name, action, entity_type, entity_id, description, created_at)
                              VALUES ('Admin', 'REJECT', 'booking', :eid, :desc, CURRENT_TIMESTAMP)");
        $log->execute([':eid' => $id, ':desc' => 'Booking ditolak: ' . $booking['title'] . ' — Alasan: ' . $reason]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Booking berhasil ditolak.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// RENDER
// ============================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Persetujuan Booking – Lab Management</title>

<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ═══════════════ TOKENS ═══════════════ */
:root {
  --cream:       #faf8f5;
  --white:       #ffffff;
  --text-dark:   #1e1e2e;
  --text-mid:    #5a5a6e;
  --text-light:  #9a9aad;
  --border:      #eae8e3;

  --cyan:        #00c9a7;
  --cyan-light:  #e6faf5;
  --pink:        #e8609a;
  --pink-light:  #fde9f1;
  --amber:       #f5a623;
  --amber-light: #fef3e0;
  --blue:        #4a8cff;
  --blue-light:  #eaf1ff;
  --violet:      #8b6bea;
  --violet-light:#f0ecfd;
  --coral:       #ff6f61;
  --coral-light: #fff0ee;
  --red:         #e53e3e;
  --red-light:   #fef2f2;
}

/* ═══════════════ BASE ═══════════════ */
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family:'Inter',sans-serif;
  background:var(--cream);
  color:var(--text-dark);
  min-height:100vh;
}

/* ═══════════════ TOPBAR ═══════════════ */
.topbar {
  background:var(--white);
  border-bottom:1px solid var(--border);
  padding:1rem 2rem;
  display:flex; align-items:center; justify-content:space-between;
  position:sticky; top:0; z-index:200;
  box-shadow:0 2px 12px rgba(0,0,0,.04);
}
.topbar-left { display:flex; align-items:center; gap:.85rem; }
.topbar-logo {
  width:42px; height:42px;
  background:linear-gradient(135deg,var(--cyan),var(--blue));
  border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem;
}
.topbar-text h1 { font-family:'Sora',sans-serif; font-size:1.2rem; font-weight:700; line-height:1.2; }
.topbar-text span { font-size:.78rem; color:var(--text-light); }
.topbar-right { display:flex; align-items:center; gap:1rem; }
.back-link {
  display:inline-flex; align-items:center; gap:.4rem;
  font-size:.82rem; color:var(--text-light);
  text-decoration:none; font-weight:500;
  transition:color .2s;
}
.back-link:hover { color:var(--blue); }

/* ═══════════════ PAGE ═══════════════ */
.page { max-width:1100px; margin:0 auto; padding:1.8rem 1.5rem 3rem; }

/* ─── hero ─── */
.hero {
  background:linear-gradient(135deg,#1e3a5f 0%,#1a2e4a 60%,#16243b 100%);
  border-radius:20px;
  padding:1.6rem 2rem;
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:1.6rem;
  color:#fff; position:relative; overflow:hidden;
}
.hero::before {
  content:''; position:absolute;
  top:-50px; right:-50px;
  width:180px; height:180px; border-radius:50%;
  background:rgba(0,201,167,.13);
}
.hero::after {
  content:''; position:absolute;
  bottom:-35px; right:130px;
  width:110px; height:110px; border-radius:50%;
  background:rgba(74,140,255,.1);
}
.hero-left { position:relative; z-index:1; }
.hero-left h2 { font-family:'Sora',sans-serif; font-size:1.45rem; font-weight:700; margin-bottom:.25rem; }
.hero-left p { font-size:.85rem; opacity:.7; }
.hero-icon { position:relative; z-index:1; font-size:2.4rem; animation:bob 3s ease-in-out infinite; }
@keyframes bob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }

/* ─── summary stats ─── */
.stats-row {
  display:grid; grid-template-columns:repeat(3,1fr);
  gap:1rem; margin-bottom:1.6rem;
}
.stat-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:14px;
  padding:1.1rem 1.2rem;
  display:flex; align-items:center; gap:.9rem;
  opacity:0; animation:cardIn .4s ease forwards;
}
.stat-card:nth-child(1){animation-delay:.06s}
.stat-card:nth-child(2){animation-delay:.12s}
.stat-card:nth-child(3){animation-delay:.18s}
@keyframes cardIn { to{opacity:1;transform:translateY(0)} }

.stat-icon {
  width:40px; height:40px; border-radius:11px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.2rem; flex-shrink:0;
}
.si-amber  { background:var(--amber-light); }
.si-cyan   { background:var(--cyan-light); }
.si-red    { background:var(--red-light); }

.stat-number { font-family:'Sora',sans-serif; font-size:1.5rem; font-weight:800; line-height:1.1; }
.stat-label  { font-size:.74rem; color:var(--text-light); margin-top:.1rem; font-weight:500; }

/* ─── filter tabs ─── */
.filter-bar {
  display:flex; align-items:center; gap:.5rem;
  margin-bottom:1.2rem; flex-wrap:wrap;
}
.filter-tab {
  padding:.42rem .9rem; border-radius:10px;
  border:1.5px solid var(--border);
  background:var(--white); color:var(--text-mid);
  font-size:.8rem; font-weight:600; cursor:pointer;
  transition:all .2s; text-decoration:none;
  display:inline-flex; align-items:center; gap:.4rem;
}
.filter-tab:hover { border-color:var(--blue); color:var(--blue); }
.filter-tab.active {
  background:var(--blue); color:#fff;
  border-color:var(--blue);
}
.filter-tab .tab-count {
  background:rgba(255,255,255,.25); border-radius:10px;
  padding:.05rem .42rem; font-size:.72rem; font-weight:700;
}
.filter-tab:not(.active) .tab-count {
  background:var(--border); color:var(--text-mid);
}

/* ─── section label ─── */
.section-label {
  font-size:.76rem; font-weight:600;
  text-transform:uppercase; letter-spacing:1.5px;
  color:var(--text-light); margin-bottom:.75rem;
  display:flex; align-items:center; gap:.6rem;
}
.section-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* ═══════════════ BOOKING CARDS ═══════════════ */
.booking-list { display:flex; flex-direction:column; gap:.85rem; }

.booking-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  overflow:hidden;
  opacity:0; animation:cardIn .4s ease forwards;
  transition:box-shadow .2s;
}
.booking-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.07); }

/* coloured left stripe per status */
.booking-card[data-status="pending"]  { border-left:4px solid var(--amber); }
.booking-card[data-status="approved"] { border-left:4px solid var(--cyan); }
.booking-card[data-status="rejected"] { border-left:4px solid var(--red); }

/* card head — title row */
.card-head {
  padding:1rem 1.2rem .65rem;
  display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem;
}
.card-head-left { flex:1; min-width:0; }
.card-head-left h3 {
  font-family:'Sora',sans-serif;
  font-size:.95rem; font-weight:700;
  color:var(--text-dark); margin-bottom:.25rem;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.card-head-left .card-meta {
  display:flex; gap:.85rem; flex-wrap:wrap;
  font-size:.76rem; color:var(--text-light);
}
.card-meta span { display:flex; align-items:center; gap:.25rem; }

/* status badge */
.status-badge {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.28rem .65rem; border-radius:8px;
  font-size:.73rem; font-weight:600; white-space:nowrap;
  flex-shrink:0;
}
.sb-pending  { background:var(--amber-light); color:#92400e; }
.sb-approved { background:var(--cyan-light);   color:#065f46; }
.sb-rejected { background:var(--red-light);    color:var(--red); }

/* card body — detail grid */
.card-body {
  padding:0 1.2rem .9rem;
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
  gap:.55rem .9rem;
}
.detail-item { display:flex; flex-direction:column; gap:.08rem; }
.detail-label { font-size:.7rem; font-weight:600; color:var(--text-light); text-transform:uppercase; letter-spacing:.6px; }
.detail-val   { font-size:.8rem; color:var(--text-mid); font-weight:500; }

/* reject reason box (shown only when rejected) */
.reject-reason-box {
  margin:0 1.2rem .75rem;
  background:var(--red-light);
  border-left:3px solid var(--red);
  border-radius:8px;
  padding:.5rem .7rem;
  font-size:.78rem; color:var(--red);
}
.reject-reason-box strong { font-weight:600; }

/* card footer — action buttons */
.card-footer {
  padding:.7rem 1.2rem;
  border-top:1px solid var(--border);
  background:var(--cream);
  display:flex; align-items:center; justify-content:space-between;
  gap:.6rem;
}
.card-footer-actions { display:flex; gap:.5rem; }

/* action buttons */
.btn-action {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.42rem .85rem; border-radius:9px;
  font-size:.78rem; font-weight:600; border:none;
  cursor:pointer; transition:all .2s;
}
.btn-approve {
  background:linear-gradient(135deg,var(--cyan),#059669);
  color:#fff;
}
.btn-approve:hover { box-shadow:0 3px 12px rgba(0,201,167,.35); transform:translateY(-1px); }

.btn-reject {
  background:var(--white); color:var(--red);
  border:1.5px solid var(--red);
}
.btn-reject:hover { background:var(--red-light); }

.btn-detail {
  background:var(--white); color:var(--text-mid);
  border:1.5px solid var(--border);
}
.btn-detail:hover { border-color:var(--blue); color:var(--blue); }

/* booking code badge */
.code-badge {
  font-size:.72rem; font-weight:600;
  background:var(--blue-light); color:var(--blue);
  padding:.18rem .55rem; border-radius:6px;
  font-family:'Sora',sans-serif;
}

/* ═══════════════ EMPTY STATE ═══════════════ */
.empty-state {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:3.2rem 2rem;
  text-align:center;
}
.empty-state .empty-icon { font-size:2.8rem; margin-bottom:.8rem; }
.empty-state h3 { font-family:'Sora',sans-serif; font-size:1.05rem; font-weight:700; margin-bottom:.3rem; }
.empty-state p { font-size:.82rem; color:var(--text-light); }

/* ═══════════════ MODALS ═══════════════ */
.modal-content {
  border-radius:18px; border:none; overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.15);
}
.modal-header {
  background:linear-gradient(135deg,#1e3a5f,#16243b);
  color:#fff; padding:1.2rem 1.6rem; border:none;
}
.modal-title { font-family:'Sora',sans-serif; font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
.modal-body { padding:1.5rem 1.6rem; }
.modal-footer { padding:1rem 1.6rem; border:none; background:var(--cream); display:flex; justify-content:flex-end; gap:.6rem; }

/* detail modal grid */
.detail-grid {
  display:grid; grid-template-columns:1fr 1fr;
  gap:.7rem 1.4rem;
}
.detail-grid .dg-item { display:flex; flex-direction:column; gap:.08rem; }
.detail-grid .dg-label { font-size:.72rem; font-weight:600; color:var(--text-light); text-transform:uppercase; letter-spacing:.6px; }
.detail-grid .dg-val   { font-size:.84rem; color:var(--text-dark); font-weight:500; }
.detail-grid .dg-full  { grid-column:1/-1; }

/* reject modal textarea */
.reject-textarea {
  width:100%; border-radius:10px;
  border:1.5px solid var(--border);
  padding:.6rem .7rem;
  font-size:.83rem; font-family:'Inter',sans-serif;
  resize:vertical; min-height:80px;
  transition:border-color .2s;
}
.reject-textarea:focus { border-color:var(--red); outline:none; box-shadow:0 0 0 3px rgba(229,62,62,.15); }

/* modal btn overrides */
.btn-modal-save {
  background:linear-gradient(135deg,var(--cyan),#059669);
  color:#fff; border:none; border-radius:10px;
  padding:.5rem 1.2rem; font-weight:600; font-size:.82rem;
  cursor:pointer; transition:box-shadow .2s;
}
.btn-modal-save:hover { box-shadow:0 3px 12px rgba(0,201,167,.3); }

.btn-modal-danger {
  background:var(--red); color:#fff; border:none; border-radius:10px;
  padding:.5rem 1.2rem; font-weight:600; font-size:.82rem;
  cursor:pointer; transition:box-shadow .2s;
}
.btn-modal-danger:hover { box-shadow:0 3px 12px rgba(229,62,62,.3); }

.btn-modal-cancel {
  background:var(--white); color:var(--text-mid);
  border:1.5px solid var(--border); border-radius:10px;
  padding:.5rem 1rem; font-weight:600; font-size:.82rem;
  cursor:pointer; transition:border-color .2s;
}
.btn-modal-cancel:hover { border-color:var(--text-mid); }

/* ═══════════════ OVERLAY ═══════════════ */
.overlay {
  position:fixed; inset:0;
  background:rgba(30,30,46,.5);
  display:none; align-items:center; justify-content:center;
  z-index:9999; backdrop-filter:blur(2px);
}
.overlay.show { display:flex; }
.spinner {
  width:42px; height:42px; border-radius:50%;
  border:4px solid rgba(255,255,255,.2);
  border-top-color:#fff;
  animation:spin .7s linear infinite;
}
@keyframes spin { to{transform:rotate(360deg)} }

/* ═══════════════ SWEETALERT OVERRIDES ═══════════════ */
.swal2-popup { border-radius:18px !important; font-family:'Inter',sans-serif !important; }
.swal2-title { font-family:'Sora',sans-serif !important; color:var(--text-dark) !important; }
.swal2-confirm { border-radius:10px !important; font-weight:600 !important; }

/* ═══════════════ RESPONSIVE ═══════════════ */
@media(max-width:680px){
  .page { padding:1.2rem 1rem 2.5rem; }
  .hero { flex-direction:column; align-items:flex-start; gap:.5rem; padding:1.2rem 1.3rem; }
  .stats-row { grid-template-columns:repeat(3,1fr); gap:.6rem; }
  .stat-card { padding:.8rem .7rem; }
  .stat-number { font-size:1.2rem; }
  .card-body { grid-template-columns:1fr 1fr; }
  .detail-grid { grid-template-columns:1fr; }
  .topbar { padding:.7rem 1rem; }
  .filter-bar { gap:.35rem; }
  .filter-tab { padding:.35rem .65rem; font-size:.74rem; }
}
@media(max-width:400px){
  .stats-row { grid-template-columns:1fr; }
  .card-footer { flex-direction:column; align-items:stretch; }
  .card-footer-actions { width:100%; }
  .btn-action { flex:1; justify-content:center; }
}
</style>
</head>
<body>

<!-- ═══ OVERLAY ═══ -->
<div class="overlay" id="overlay"><div class="spinner"></div></div>

<!-- ═══ TOPBAR ═══ -->
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-logo">🧪</div>
    <div class="topbar-text">
      <h1>Lab Management</h1>
      <span>Persetujuan Booking</span>
    </div>
  </div>
  <div class="topbar-right">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Beranda</a>
  </div>
</div>

<!-- ═══ PAGE ═══ -->
<div class="page">

  <!-- hero -->
  <div class="hero">
    <div class="hero-left">
      <h2>Persetujuan Booking</h2>
      <p>Review dan setuju atau tolak permintaan booking laboratorium</p>
    </div>
    <div class="hero-icon">📋</div>
  </div>

  <!-- stats (JS-populated) -->
  <div class="stats-row" id="statsRow">
    <div class="stat-card">
      <div class="stat-icon si-amber">⏳</div>
      <div><div class="stat-number" id="statPending">0</div><div class="stat-label">Menunggu</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-cyan">✓</div>
      <div><div class="stat-number" id="statApproved">0</div><div class="stat-label">Disetujui</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-red">✕</div>
      <div><div class="stat-number" id="statRejected">0</div><div class="stat-label">Ditolak</div></div>
    </div>
  </div>

  <!-- filter tabs -->
  <div class="filter-bar" id="filterBar">
    <button class="filter-tab active" data-filter="pending" onclick="switchFilter(this)">
      ⏳ Menunggu <span class="tab-count" id="countPending">0</span>
    </button>
    <button class="filter-tab" data-filter="approved" onclick="switchFilter(this)">
      ✓ Disetujui <span class="tab-count" id="countApproved">0</span>
    </button>
    <button class="filter-tab" data-filter="rejected" onclick="switchFilter(this)">
      ✕ Ditolak <span class="tab-count" id="countRejected">0</span>
    </button>
    <button class="filter-tab" data-filter="all" onclick="switchFilter(this)">
      📋 Semua
    </button>
  </div>

  <!-- list -->
  <div class="section-label">Daftar Booking</div>
  <div class="booking-list" id="bookingList">
    <!-- JS rendered -->
  </div>

</div><!-- /page -->

<!-- ═══ DETAIL MODAL ═══ -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-search"></i> Detail Booking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
          <h3 style="font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;" id="detailTitle">–</h3>
          <span id="detailStatusBadge" class="status-badge sb-pending">⏳ Pending</span>
        </div>
        <div class="detail-grid" id="detailGrid"><!-- JS --></div>
        <div id="detailRejectBox" style="display:none;" class="reject-reason-box" style="margin-top:.8rem;">
          <strong>Alasan Penolakan:</strong> <span id="detailRejectReason">–</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ REJECT REASON MODAL ═══ -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#7f1d1d,#991b1b);">
        <h5 class="modal-title"><i class="fas fa-ban"></i> Tolak Booking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="font-size:.82rem;color:var(--text-mid);margin-bottom:.6rem;">
          Anda akan menolak booking <strong id="rejectTitle">–</strong>. Silakan berikan alasan penolakan:
        </p>
        <textarea class="reject-textarea" id="rejectReason" placeholder="Tulis alasan penolakan…"></textarea>
        <input type="hidden" id="rejectBookingId" value="">
      </div>
      <div class="modal-footer">
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Batal</button>
        <button class="btn-modal-danger" onclick="submitReject()"><i class="fas fa-ban"></i> Tolak Booking</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ LIBS ═══ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ═══════════════════════════════════════════════════════════
   STATE
   ═══════════════════════════════════════════════════════════ */
let currentFilter = 'pending';
let currentData   = [];

/* ═══════════════════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════════════════ */
function showLoading(){ document.getElementById('overlay').classList.add('show'); }
function hideLoading(){ document.getElementById('overlay').classList.remove('show'); }

function formatDate(str){
  if(!str) return '–';
  const d = new Date(str + 'T00:00:00');
  return d.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
function formatDateTime(str){
  if(!str) return '–';
  const d = new Date(str);
  return d.toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'}) + ' ' +
         d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
}
function generateBookingCode(id, date){
  return 'BKG-' + (date||'').replace(/-/g,'') + '-' + String(id).padStart(4,'0');
}

/* ═══════════════════════════════════════════════════════════
   FETCH & RENDER
   ═══════════════════════════════════════════════════════════ */
async function fetchAndRender(filter = 'pending', showSpinner = true){
  if(showSpinner) showLoading();
  currentFilter = filter;

  try {
    const res  = await fetch('approval.php?ajax=list&filter=' + filter);
    const json = await res.json();
    if(!json.success) throw new Error(json.message);

    currentData = json.data;
    updateSummary(json.summary);
    renderCards(json.data);
  } catch(err){
    console.error(err);
    Swal.fire({ title:'Error', text:err.message||'Gagal memuat data', icon:'error', confirmButtonColor:'#4a8cff' });
  }
  hideLoading();
}

/* ─── update stat numbers + tab counts ─── */
function updateSummary(s){
  document.getElementById('statPending').textContent  = s.pending;
  document.getElementById('statApproved').textContent = s.approved;
  document.getElementById('statRejected').textContent = s.rejected;
  document.getElementById('countPending').textContent  = s.pending;
  document.getElementById('countApproved').textContent = s.approved;
  document.getElementById('countRejected').textContent = s.rejected;
}

/* ─── render booking cards ─── */
function renderCards(list){
  const container = document.getElementById('bookingList');

  if(!list || list.length === 0){
    const labels = { pending:'Tidak ada booking yang menunggu persetujuan.',
                     approved:'Tidak ada booking yang telah disetujui.',
                     rejected:'Tidak ada booking yang ditolak.',
                     all:'Tidak ada data booking.' };
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">${currentFilter==='pending'?'📭':'📄'}</div>
        <h3>${currentFilter==='pending'?'Semua bersih!':'Kosong'}</h3>
        <p>${labels[currentFilter]||labels.all}</p>
      </div>`;
    return;
  }

  let html = '';
  list.forEach((b, idx) => {
    const delay = (idx * 0.04).toFixed(2);
    const code  = generateBookingCode(b.id, b.booking_date);

    // extract reject reason from notes column
    let rejectReason = (b.notes || '').trim();

    // badge
    const badgeClass = { pending:'sb-pending', approved:'sb-approved', rejected:'sb-rejected' };
    const badgeIcon  = { pending:'⏳', approved:'✓', rejected:'✕' };
    const badgeText  = { pending:'Menunggu', approved:'Disetujui', rejected:'Ditolak' };

    // footer buttons — hanya tampil kalau pending
    let footerBtns = '';
    if(b.status === 'pending'){
      footerBtns = `
        <div class="card-footer-actions">
          <button class="btn-action btn-approve" onclick="confirmApprove(${b.id},'${escHtml(b.title)}')">
            <i class="fas fa-check"></i> Setuju
          </button>
          <button class="btn-action btn-reject" onclick="openRejectModal(${b.id},'${escHtml(b.title)}')">
            <i class="fas fa-times"></i> Tolak
          </button>
        </div>`;
    }

    html += `
    <div class="booking-card" data-status="${b.status}" style="animation-delay:${delay}s;">
      <!-- head -->
      <div class="card-head">
        <div class="card-head-left">
          <h3>${escHtml(b.title)}</h3>
          <div class="card-meta">
            <span>📆 ${formatDate(b.booking_date)}</span>
            <span>🕐 ${b.start_time.substring(0,5)} – ${b.end_time.substring(0,5)}</span>
            <span class="code-badge">${code}</span>
          </div>
        </div>
        <span class="status-badge ${badgeClass[b.status]}">${badgeIcon[b.status]} ${badgeText[b.status]}</span>
      </div>

      <!-- body -->
      <div class="card-body">
        <div class="detail-item">
          <span class="detail-label">Laboratorium</span>
          <span class="detail-val">🖥️ ${escHtml(b.lab_name)}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Institusi</span>
          <span class="detail-val">🏢 ${escHtml(b.institution_name)}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Guru</span>
          <span class="detail-val">👤 ${escHtml(b.teacher_name)}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Kelas</span>
          <span class="detail-val">🎓 ${escHtml(b.class_name||'–')}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Mata Pelajaran</span>
          <span class="detail-val">📚 ${escHtml(b.subject_name||'–')}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Peserta</span>
          <span class="detail-val">👥 ${b.participant_count||'–'} orang</span>
        </div>
      </div>

      ${rejectReason ? `<div class="reject-reason-box"><strong>Alasan Penolakan:</strong> ${escHtml(rejectReason)}</div>` : ''}

      <!-- footer -->
      <div class="card-footer">
        ${footerBtns}
        <button class="btn-action btn-detail" onclick="openDetail(${b.id})" style="margin-left:auto;">
          <i class="fas fa-search"></i> Detail
        </button>
      </div>
    </div>`;
  });

  container.innerHTML = html;
}

/* ─── html-escape ─── */
function escHtml(s){
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ═══════════════════════════════════════════════════════════
   FILTER SWITCH
   ═══════════════════════════════════════════════════════════ */
function switchFilter(el){
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  fetchAndRender(el.dataset.filter);
}

/* ═══════════════════════════════════════════════════════════
   APPROVE
   ═══════════════════════════════════════════════════════════ */
async function confirmApprove(id, title){
  const { isConfirmed } = await Swal.fire({
    title:'Setuju Booking?',
    html:`Anda akan menyetujui booking:<br><strong>${escHtml(title)}</strong>`,
    icon:'question',
    showCancelButton:true,
    confirmButtonText:'Ya, Setuju',
    cancelButtonText:'Batal',
    confirmButtonColor:'#00c9a7',
    cancelButtonColor:'#9a9aad',
    reverseButtons:true
  });

  if(!isConfirmed) return;

  showLoading();
  try {
    const fd = new FormData();
    fd.append('aksi','approve');
    fd.append('booking_id', id);

    const res  = await fetch('approval.php', {method:'POST', body:fd});
    const json = await res.json();
    hideLoading();

    if(json.success){
      Swal.fire({ title:'Berhasil ✓', text:json.message, icon:'success', timer:2000, timerProgressBar:true, confirmButtonColor:'#4a8cff' });
      await fetchAndRender(currentFilter, false);
    } else {
      Swal.fire({ title:'Gagal', text:json.message, icon:'error', confirmButtonColor:'#4a8cff' });
    }
  } catch(err){
    hideLoading();
    Swal.fire({ title:'Error', text:'Kesalahan koneksi', icon:'error', confirmButtonColor:'#4a8cff' });
  }
}

/* ═══════════════════════════════════════════════════════════
   REJECT  —  open modal → submit
   ═══════════════════════════════════════════════════════════ */
function openRejectModal(id, title){
  document.getElementById('rejectTitle').textContent     = title;
  document.getElementById('rejectBookingId').value       = id;
  document.getElementById('rejectReason').value          = '';
  bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.dispose();
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

async function submitReject(){
  const id     = document.getElementById('rejectBookingId').value;
  const reason = document.getElementById('rejectReason').value.trim();

  if(!reason){
    Swal.fire({ title:'Perhatian', text:'Silakan isi alasan penolakan.', icon:'warning', confirmButtonColor:'#4a8cff' });
    return;
  }

  // close reject modal
  bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();

  // confirm
  const { isConfirmed } = await Swal.fire({
    title:'Tolak Booking?',
    html:`Booking ini akan ditolak dengan alasan:<br><em>"${escHtml(reason)}"</em>`,
    icon:'warning',
    showCancelButton:true,
    confirmButtonText:'Ya, Tolak',
    cancelButtonText:'Batal',
    confirmButtonColor:'#e53e3e',
    cancelButtonColor:'#9a9aad',
    reverseButtons:true
  });

  if(!isConfirmed) return;

  showLoading();
  try {
    const fd = new FormData();
    fd.append('aksi','reject');
    fd.append('booking_id', id);
    fd.append('reject_reason', reason);

    const res  = await fetch('approval.php', {method:'POST', body:fd});
    const json = await res.json();
    hideLoading();

    if(json.success){
      Swal.fire({ title:'Ditolak', text:json.message, icon:'info', timer:2000, timerProgressBar:true, confirmButtonColor:'#4a8cff' });
      await fetchAndRender(currentFilter, false);
    } else {
      Swal.fire({ title:'Gagal', text:json.message, icon:'error', confirmButtonColor:'#4a8cff' });
    }
  } catch(err){
    hideLoading();
    Swal.fire({ title:'Error', text:'Kesalahan koneksi', icon:'error', confirmButtonColor:'#4a8cff' });
  }
}

/* ═══════════════════════════════════════════════════════════
   DETAIL MODAL
   ═══════════════════════════════════════════════════════════ */
function openDetail(id){
  const b = currentData.find(x => x.id == id);
  if(!b) return;

  const code = generateBookingCode(b.id, b.booking_date);

  // title & badge
  document.getElementById('detailTitle').textContent = b.title;
  const badgeClass = { pending:'sb-pending', approved:'sb-approved', rejected:'sb-rejected' };
  const badgeIcon  = { pending:'⏳', approved:'✓', rejected:'✕' };
  const badgeText  = { pending:'Menunggu', approved:'Disetujui', rejected:'Ditolak' };
  document.getElementById('detailStatusBadge').className = 'status-badge ' + badgeClass[b.status];
  document.getElementById('detailStatusBadge').innerHTML = badgeIcon[b.status] + ' ' + badgeText[b.status];

  // grid
  document.getElementById('detailGrid').innerHTML = `
    <div class="dg-item"><span class="dg-label">Kode Booking</span><span class="dg-val" style="font-family:'Sora',sans-serif;color:var(--blue);font-weight:700;">${code}</span></div>
    <div class="dg-item"><span class="dg-label">Tanggal</span><span class="dg-val">${formatDate(b.booking_date)}</span></div>
    <div class="dg-item"><span class="dg-label">Waktu</span><span class="dg-val">${b.start_time.substring(0,5)} – ${b.end_time.substring(0,5)} (${escHtml(b.time_slot_name)})</span></div>
    <div class="dg-item"><span class="dg-label">Laboratorium</span><span class="dg-val">🖥️ ${escHtml(b.lab_name)} (Kapasitas ${b.lab_capacity})</span></div>
    <div class="dg-item"><span class="dg-label">Institusi</span><span class="dg-val">🏢 ${escHtml(b.institution_name)} (${escHtml(b.institution_type)})</span></div>
    <div class="dg-item"><span class="dg-label">Guru</span><span class="dg-val">👤 ${escHtml(b.teacher_name)}</span></div>
    <div class="dg-item"><span class="dg-label">Telepon</span><span class="dg-val">📞 ${escHtml(b.teacher_phone)}</span></div>
    <div class="dg-item"><span class="dg-label">Kelas</span><span class="dg-val">🎓 ${escHtml(b.class_name||'–')}</span></div>
    <div class="dg-item"><span class="dg-label">Mata Pelajaran</span><span class="dg-val">📚 ${escHtml(b.subject_name||'–')}</span></div>
    <div class="dg-item"><span class="dg-label">Jumlah Peserta</span><span class="dg-val">👥 ${b.participant_count||'–'} orang</span></div>
    <div class="dg-item dg-full"><span class="dg-label">Deskripsi</span><span class="dg-val">${escHtml(b.description||'Tidak ada deskripsi')}</span></div>
    <div class="dg-item"><span class="dg-label">Dibuat</span><span class="dg-val">${formatDateTime(b.created_at)}</span></div>
    <div class="dg-item"><span class="dg-label">Terakhir Diperbarui</span><span class="dg-val">${formatDateTime(b.updated_at)}</span></div>
    ${b.status==='approved' && b.approved_at ? `<div class="dg-item dg-full"><span class="dg-label">Waktu Persetujuan</span><span class="dg-val" style="color:var(--cyan);font-weight:600;">✓ ${formatDateTime(b.approved_at)}</span></div>` : ''}`;

  // reject reason box
  let rejectReason = (b.notes || '').trim();
  const rBox = document.getElementById('detailRejectBox');
  if(rejectReason){
    rBox.style.display = 'block';
    document.getElementById('detailRejectReason').textContent = rejectReason;
  } else {
    rBox.style.display = 'none';
  }

  new bootstrap.Modal(document.getElementById('detailModal')).show();
}

/* ═══════════════════════════════════════════════════════════
   INIT
   ═══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => fetchAndRender('pending'));
</script>
</body>
</html>