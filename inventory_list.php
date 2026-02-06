<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

// Get filter parameters
$lab_filter = $_GET['lab'] ?? 'all';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$condition_filter = $_GET['condition'] ?? '';
$search = $_GET['search'] ?? '';

// Initialize variables
$inventories = [];
$labs = [];
$summary = [
    'total_items' => 0,
    'total_quantity' => 0,
    'quantity_good' => 0,
    'quantity_broken' => 0,
    'quantity_backup' => 0,
    'active_items' => 0,
    'maintenance_items' => 0
];
$error_message = '';

try {
    $db = getDB();
    
    // Build query
    $sql = "SELECT 
                li.*,
                r.name AS lab_name,
                r.id AS lab_id,
                u1.full_name AS created_by_name,
                u2.full_name AS updated_by_name
            FROM lab_inventory li
            JOIN resources r ON li.resource_id = r.id
            LEFT JOIN users u1 ON li.created_by = u1.id
            LEFT JOIN users u2 ON li.updated_by = u2.id
            WHERE li.deleted_at IS NULL";

    $params = [];

    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $sql .= " AND li.resource_id = :lab_id";
        $params[':lab_id'] = $lab_filter;
    }

    if (!empty($category_filter)) {
        $sql .= " AND li.category = :category";
        $params[':category'] = $category_filter;
    }

    if (!empty($status_filter)) {
        $sql .= " AND li.status = :status";
        $params[':status'] = $status_filter;
    }

    if (!empty($condition_filter)) {
        $sql .= " AND li.`condition` = :condition";
        $params[':condition'] = $condition_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (li.item_code LIKE :search OR li.item_name LIKE :search OR li.brand LIKE :search OR li.model LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY r.name, li.category, li.item_code";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get labs for tabs
    $labs_stmt = $db->query("SELECT id, name FROM resources WHERE type = 'lab' AND deleted_at IS NULL ORDER BY name");
    $labs = $labs_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_items,
                        SUM(quantity) as total_quantity,
                        SUM(quantity_good) as quantity_good,
                        SUM(quantity_broken) as quantity_broken,
                        SUM(quantity_backup) as quantity_backup,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
                        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_items
                    FROM lab_inventory li
                    WHERE li.deleted_at IS NULL";
    
    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $summary_sql .= " AND li.resource_id = :lab_id";
    }
    
    $summary_stmt = $db->prepare($summary_sql);
    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $summary_stmt->bindValue(':lab_id', $lab_filter);
    }
    $summary_stmt->execute();
    $summary_result = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($summary_result) {
        $summary = array_merge($summary, $summary_result);
    }
    
} catch (PDOException $e) {
    error_log("Database error in inventory_list.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data inventaris. Silakan coba lagi atau hubungi administrator.";
} catch (Exception $e) {
    error_log("General error in inventory_list.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan yang tidak terduga. Silakan coba lagi atau hubungi administrator.";
}

function buildTabUrl($lab_id) {
    $params = [];
    $params['lab'] = $lab_id;
    
    if (!empty($_GET['category'])) $params['category'] = $_GET['category'];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    if (!empty($_GET['condition'])) $params['condition'] = $_GET['condition'];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventaris Lab – <?= htmlspecialchars(APP_NAME) ?></title>

<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
  --green:       #28a745;
  --green-light: #d4edda;
}

/* ═══════════════ BASE ═══════════════ */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:var(--cream); color:var(--text-dark); min-height:100vh; }

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
.page { max-width:1400px; margin:0 auto; padding:1.8rem 1.5rem 3rem; }

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
.hero-icon { position:relative; z-index:1; font-size:2.4rem; }

/* ─── error banner ─── */
.error-banner {
  background:var(--red-light);
  border-left:4px solid var(--red);
  border-radius:12px;
  padding:.9rem 1.2rem;
  margin-bottom:1.4rem;
  font-size:.82rem; color:var(--red);
}
.error-banner strong { font-weight:700; }

/* ─── lab tabs ─── */
.tabs-wrap {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:.7rem 1rem;
  margin-bottom:1.4rem;
  overflow-x:auto;
  display:flex; gap:.4rem;
}
.tab {
  padding:.5rem 1rem; border-radius:10px;
  font-size:.8rem; font-weight:600;
  text-decoration:none; color:var(--text-mid);
  white-space:nowrap; transition:all .2s;
  border:1.5px solid transparent;
}
.tab:hover { background:var(--cream); color:var(--blue); }
.tab.active { background:var(--blue); color:#fff; border-color:var(--blue); }

/* ─── stats row ─── */
.stats-row {
  display:grid; grid-template-columns:repeat(5,1fr);
  gap:1rem; margin-bottom:1.6rem;
}
.stat-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:14px;
  padding:1.1rem 1rem;
  display:flex; flex-direction:column; gap:.4rem;
  opacity:0; animation:cardIn .4s ease forwards;
}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.10s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-card:nth-child(4){animation-delay:.20s}
.stat-card:nth-child(5){animation-delay:.25s}
@keyframes cardIn { to{opacity:1;transform:translateY(0)} }

.stat-label { font-size:.72rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
.stat-number { font-family:'Sora',sans-serif; font-size:1.8rem; font-weight:800; line-height:1; }

.sc-total   { border-left:4px solid var(--blue); }
.sc-total .stat-number { color:var(--blue); }
.sc-qty     { border-left:4px solid var(--cyan); }
.sc-qty .stat-number { color:var(--cyan); }
.sc-good    { border-left:4px solid var(--green); }
.sc-good .stat-number { color:var(--green); }
.sc-broken  { border-left:4px solid var(--red); }
.sc-broken .stat-number { color:var(--red); }
.sc-backup  { border-left:4px solid var(--amber); }
.sc-backup .stat-number { color:var(--amber); }

/* ─── filters ─── */
.filter-box {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:1.3rem 1.5rem;
  margin-bottom:1.4rem;
}
.filter-grid {
  display:grid; grid-template-columns:repeat(4,1fr);
  gap:.85rem; margin-bottom:1rem;
}
.filter-group { display:flex; flex-direction:column; gap:.3rem; }
.filter-group label { font-size:.76rem; font-weight:600; color:var(--text-mid); }
.filter-group select,
.filter-group input {
  padding:.6rem .7rem; border-radius:10px;
  border:1.5px solid var(--border);
  font-size:.82rem; font-family:'Inter',sans-serif;
  transition:border-color .2s;
}
.filter-group select:focus,
.filter-group input:focus {
  border-color:var(--blue); outline:none;
}

.filter-actions { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
.btn {
  display:inline-flex; align-items:center; gap:.35rem;
  padding:.55rem 1.1rem; border-radius:10px;
  font-size:.82rem; font-weight:600; border:none;
  cursor:pointer; text-decoration:none;
  transition:all .2s;
}
.btn-primary { background:var(--blue); color:#fff; }
.btn-primary:hover { background:#3a7bd5; box-shadow:0 3px 12px rgba(74,140,255,.3); }
.btn-secondary { background:var(--white); color:var(--text-mid); border:1.5px solid var(--border); }
.btn-secondary:hover { border-color:var(--text-mid); }
.btn-success { background:var(--green); color:#fff; }
.btn-success:hover { background:#218838; box-shadow:0 3px 12px rgba(40,167,69,.3); }
.btn-info { background:var(--cyan); color:#fff; }
.btn-info:hover { background:#00a88a; box-shadow:0 3px 12px rgba(0,201,167,.3); }

/* dropdown */
.dropdown { position:relative; display:inline-block; }
.dropdown-toggle::after { content:' ▼'; font-size:.7rem; opacity:.7; }
.dropdown-menu {
  display:none; position:absolute; top:calc(100% + 5px); left:0;
  background:var(--white); min-width:220px;
  border:1.5px solid var(--border); border-radius:12px;
  box-shadow:0 8px 24px rgba(0,0,0,.12);
  overflow:hidden; z-index:300;
}
.dropdown-menu.show { display:block; }
.dropdown-item {
  display:block; padding:.7rem 1rem;
  font-size:.8rem; color:var(--text-mid);
  text-decoration:none; transition:background .2s;
  border-bottom:1px solid var(--border);
}
.dropdown-item:last-child { border-bottom:none; }
.dropdown-item:hover { background:var(--cream); color:var(--blue); }

/* ─── content header ─── */
.content-head {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:1rem;
}
.content-head h3 {
  font-family:'Sora',sans-serif;
  font-size:1.05rem; font-weight:700;
  color:var(--text-dark);
}
.count-badge {
  display:inline-block;
  background:var(--blue-light); color:var(--blue);
  padding:.25rem .65rem; border-radius:8px;
  font-size:.74rem; font-weight:700;
  margin-left:.5rem;
}

/* ─── table ─── */
.table-wrap {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  overflow:hidden;
}
table {
  width:100%; border-collapse:collapse;
  font-size:.8rem;
}
thead {
  background:var(--cream);
}
th {
  padding:.75rem .65rem;
  text-align:left; font-weight:600;
  color:var(--text-mid);
  border-bottom:2px solid var(--border);
  font-size:.74rem; text-transform:uppercase;
  letter-spacing:.5px;
}
td {
  padding:.75rem .65rem;
  border-bottom:1px solid var(--border);
  color:var(--text-mid);
}
tbody tr:hover { background:var(--cream); }

/* badges */
.badge {
  display:inline-block;
  padding:.25rem .6rem; border-radius:10px;
  font-size:.72rem; font-weight:600;
  white-space:nowrap;
}
/* category */
.badge-cat-computer   { background:var(--blue-light);   color:var(--blue); }
.badge-cat-peripheral { background:var(--violet-light); color:var(--violet); }
.badge-cat-furniture  { background:var(--amber-light);  color:#e08a10; }
.badge-cat-network    { background:var(--cyan-light);   color:var(--cyan); }
.badge-cat-software   { background:var(--pink-light);   color:var(--pink); }
.badge-cat-other      { background:var(--border);       color:var(--text-mid); }
/* status */
.badge-st-active      { background:var(--green-light);  color:#155724; }
.badge-st-inactive    { background:var(--red-light);    color:var(--red); }
.badge-st-maintenance { background:var(--amber-light);  color:#856404; }
.badge-st-retired     { background:#e2e3e5;             color:#383d41; }
/* condition */
.badge-cond-excellent { background:#d1ecf1; color:#0c5460; }
.badge-cond-good      { background:var(--green-light); color:#155724; }
.badge-cond-fair      { background:var(--amber-light); color:#856404; }
.badge-cond-poor      { background:var(--red-light);   color:var(--red); }
.badge-cond-broken    { background:#f5c6cb; color:#721c24; }

/* qty info */
.qty-main { font-weight:600; color:var(--text-dark); }
.qty-detail {
  font-size:.72rem; color:var(--text-light);
  margin-top:.25rem; line-height:1.4;
}
.qty-good   { color:var(--green); font-weight:600; }
.qty-broken { color:var(--red);   font-weight:600; }
.qty-backup { color:var(--amber); font-weight:600; }

/* empty state */
.empty-state {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:3rem 2rem;
  text-align:center;
}
.empty-state .empty-icon { font-size:2.5rem; margin-bottom:.7rem; opacity:.5; }
.empty-state h3 { font-family:'Sora',sans-serif; font-size:1rem; font-weight:700; margin-bottom:.3rem; }
.empty-state p { font-size:.82rem; color:var(--text-light); }

/* ═══════════════ RESPONSIVE ═══════════════ */
@media(max-width:1024px){
  .stats-row { grid-template-columns:repeat(3,1fr); }
  .filter-grid { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:680px){
  .page { padding:1.2rem 1rem 2.5rem; }
  .hero { flex-direction:column; align-items:flex-start; gap:.5rem; padding:1.2rem 1.3rem; }
  .stats-row { grid-template-columns:repeat(2,1fr); gap:.7rem; }
  .stat-card { padding:.8rem .7rem; }
  .stat-number { font-size:1.4rem; }
  .filter-grid { grid-template-columns:1fr; }
  .content-head { flex-direction:column; align-items:flex-start; gap:.5rem; }
  table { font-size:.72rem; }
  th, td { padding:.5rem .4rem; }
  .topbar { padding:.7rem 1rem; }
}
</style>
</head>
<body>

<!-- ═══ TOPBAR ═══ -->
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-logo">🧪</div>
    <div class="topbar-text">
      <h1>Lab Management</h1>
      <span>Inventaris Lab</span>
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
      <h2>Daftar Inventaris Lab</h2>
      <p>Manajemen inventaris peralatan dan aset laboratorium</p>
    </div>
    <div class="hero-icon">📦</div>
  </div>

  <?php if ($error_message): ?>
    <div class="error-banner">
      <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>

  <!-- lab tabs -->
  <div class="tabs-wrap">
    <a href="<?= buildTabUrl('all') ?>" class="tab <?= $lab_filter==='all'?'active':'' ?>">
      🏢 Semua Lab
    </a>
    <?php foreach($labs as $lab): ?>
      <a href="<?= buildTabUrl($lab['id']) ?>" class="tab <?= $lab_filter==$lab['id']?'active':'' ?>">
        🔬 <?= htmlspecialchars($lab['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- stats -->
  <div class="stats-row">
    <div class="stat-card sc-total">
      <div class="stat-label">Total Item</div>
      <div class="stat-number"><?= number_format($summary['total_items']) ?></div>
    </div>
    <div class="stat-card sc-qty">
      <div class="stat-label">Total Unit</div>
      <div class="stat-number"><?= number_format($summary['total_quantity']) ?></div>
    </div>
    <div class="stat-card sc-good">
      <div class="stat-label">Unit Baik</div>
      <div class="stat-number"><?= number_format($summary['quantity_good']) ?></div>
    </div>
    <div class="stat-card sc-broken">
      <div class="stat-label">Unit Rusak</div>
      <div class="stat-number"><?= number_format($summary['quantity_broken']) ?></div>
    </div>
    <div class="stat-card sc-backup">
      <div class="stat-label">Unit Cadangan</div>
      <div class="stat-number"><?= number_format($summary['quantity_backup']) ?></div>
    </div>
  </div>

  <!-- filters -->
  <div class="filter-box">
    <form method="GET" action="">
      <input type="hidden" name="lab" value="<?= htmlspecialchars($lab_filter) ?>">
      <div class="filter-grid">
        <div class="filter-group">
          <label>Kategori</label>
          <select name="category">
            <option value="">Semua Kategori</option>
            <option value="computer"   <?= $category_filter=='computer'?'selected':'' ?>>Computer</option>
            <option value="peripheral" <?= $category_filter=='peripheral'?'selected':'' ?>>Peripheral</option>
            <option value="furniture"  <?= $category_filter=='furniture'?'selected':'' ?>>Furniture</option>
            <option value="network"    <?= $category_filter=='network'?'selected':'' ?>>Network</option>
            <option value="software"   <?= $category_filter=='software'?'selected':'' ?>>Software</option>
            <option value="other"      <?= $category_filter=='other'?'selected':'' ?>>Other</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="">Semua Status</option>
            <option value="active"      <?= $status_filter=='active'?'selected':'' ?>>Active</option>
            <option value="inactive"    <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option>
            <option value="maintenance" <?= $status_filter=='maintenance'?'selected':'' ?>>Maintenance</option>
            <option value="retired"     <?= $status_filter=='retired'?'selected':'' ?>>Retired</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Kondisi</label>
          <select name="condition">
            <option value="">Semua Kondisi</option>
            <option value="excellent" <?= $condition_filter=='excellent'?'selected':'' ?>>Excellent</option>
            <option value="good"      <?= $condition_filter=='good'?'selected':'' ?>>Good</option>
            <option value="fair"      <?= $condition_filter=='fair'?'selected':'' ?>>Fair</option>
            <option value="poor"      <?= $condition_filter=='poor'?'selected':'' ?>>Poor</option>
            <option value="broken"    <?= $condition_filter=='broken'?'selected':'' ?>>Broken</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Cari</label>
          <input type="text" name="search" placeholder="Kode, nama, brand, model..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
        <a href="inventory_list.php?lab=<?= htmlspecialchars($lab_filter) ?>" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        <a href="inventory_add.php" class="btn btn-success"><i class="fas fa-plus"></i> Tambah</a>
        <div class="dropdown">
          <button type="button" class="btn btn-info dropdown-toggle" onclick="toggleDropdown(event)">
            <i class="fas fa-download"></i> Export
          </button>
          <div id="exportDropdown" class="dropdown-menu">
            <a href="inventory_export.php?format=docx&<?= http_build_query($_GET) ?>" class="dropdown-item">
              📘 Word (.docx)
            </a>
            <a href="inventory_export.php?format=xlsx&<?= http_build_query($_GET) ?>" class="dropdown-item">
              📗 Excel (.xlsx)
            </a>
            <a href="inventory_export.php?format=csv&<?= http_build_query($_GET) ?>" class="dropdown-item">
              📄 CSV
            </a>
            <a href="inventory_export.php?format=pdf&<?= http_build_query($_GET) ?>" class="dropdown-item" target="_blank">
              📕 PDF / Print
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- content -->
  <div class="content-head">
    <h3>
      Daftar Inventaris
      <?php if($lab_filter !== 'all'): ?>
        <?php 
        $current_lab = array_filter($labs, function($l) use ($lab_filter) { return $l['id'] == $lab_filter; });
        $current_lab = reset($current_lab);
        if($current_lab): ?>
          – <?= htmlspecialchars($current_lab['name']) ?>
        <?php endif; ?>
      <?php endif; ?>
      <span class="count-badge"><?= count($inventories) ?> item</span>
    </h3>
  </div>

  <?php if(empty($inventories)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <h3>Tidak ada data inventaris</h3>
      <p>Silakan tambahkan inventaris baru atau ubah filter pencarian Anda</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Item</th>
            <?php if($lab_filter==='all'): ?><th>Lab</th><?php endif; ?>
            <th>Kategori</th>
            <th>Brand/Model</th>
            <th>Serial Number</th>
            <th>Spesifikasi</th>
            <th>Jumlah</th>
            <th>Kondisi</th>
            <th>Status</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($inventories as $item): ?>
            <tr>
              <td><strong style="color:var(--text-dark);"><?= htmlspecialchars($item['item_code']) ?></strong></td>
              <td><?= htmlspecialchars($item['item_name']) ?></td>
              <?php if($lab_filter==='all'): ?>
                <td><?= htmlspecialchars($item['lab_name']) ?></td>
              <?php endif; ?>
              <td><span class="badge badge-cat-<?= htmlspecialchars($item['category']) ?>"><?= ucfirst(htmlspecialchars($item['category'])) ?></span></td>
              <td>
                <?php if(!empty($item['brand']) || !empty($item['model'])): ?>
                  <?= htmlspecialchars($item['brand'] ?? '-') ?><br>
                  <small style="color:var(--text-light);"><?= htmlspecialchars($item['model'] ?? '-') ?></small>
                <?php else: ?>
                  –
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($item['serial_number'] ?? '–') ?></td>
              <td><?= htmlspecialchars($item['specifications'] ?? '–') ?></td>
              <td>
                <div class="qty-main">Total: <?= number_format($item['quantity']) ?></div>
                <div class="qty-detail">
                  <span class="qty-good">✓ Baik: <?= number_format($item['quantity_good']) ?></span><br>
                  <span class="qty-broken">✗ Rusak: <?= number_format($item['quantity_broken']) ?></span><br>
                  <span class="qty-backup">◆ Cadangan: <?= number_format($item['quantity_backup']) ?></span>
                </div>
              </td>
              <td><span class="badge badge-cond-<?= htmlspecialchars($item['condition']) ?>"><?= ucfirst(htmlspecialchars($item['condition'])) ?></span></td>
              <td><span class="badge badge-st-<?= htmlspecialchars($item['status']) ?>"><?= ucfirst(htmlspecialchars($item['status'])) ?></span></td>
              <td><?= htmlspecialchars($item['notes'] ?? '–') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div><!-- /page -->

<script>
function toggleDropdown(event){
  event.stopPropagation();
  document.getElementById('exportDropdown').classList.toggle('show');
}
window.onclick = function(e){
  if(!e.target.matches('.dropdown-toggle')){
    const drops = document.getElementsByClassName('dropdown-menu');
    for(let i=0; i<drops.length; i++){
      if(drops[i].classList.contains('show')) drops[i].classList.remove('show');
    }
  }
}
</script>
</body>
</html>