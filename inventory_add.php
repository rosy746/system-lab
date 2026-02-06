<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Validate required fields
        $required_fields = ['resource_id', 'item_code', 'item_name', 'category', 'condition', 'status', 'quantity'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Field " . ucfirst(str_replace('_', ' ', $field)) . " wajib diisi";
            }
        }
        
        // Validate numeric fields
        if (!empty($_POST['quantity']) && (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 1)) {
            $errors[] = "Jumlah item harus berupa angka positif minimal 1";
        }
        
        if (!empty($_POST['quantity_good']) && (!is_numeric($_POST['quantity_good']) || $_POST['quantity_good'] < 0)) {
            $errors[] = "Jumlah unit baik harus berupa angka positif atau 0";
        }
        
        if (!empty($_POST['quantity_broken']) && (!is_numeric($_POST['quantity_broken']) || $_POST['quantity_broken'] < 0)) {
            $errors[] = "Jumlah unit rusak harus berupa angka positif atau 0";
        }
        
        if (!empty($_POST['quantity_backup']) && (!is_numeric($_POST['quantity_backup']) || $_POST['quantity_backup'] < 0)) {
            $errors[] = "Jumlah unit cadangan harus berupa angka positif atau 0";
        }
        
        // Validate that quantity breakdown doesn't exceed total
        $total_breakdown = intval($_POST['quantity_good'] ?? 0) + intval($_POST['quantity_broken'] ?? 0) + intval($_POST['quantity_backup'] ?? 0);
        if ($total_breakdown > intval($_POST['quantity'] ?? 0)) {
            $errors[] = "Total breakdown (baik + rusak + cadangan) tidak boleh melebihi jumlah total unit";
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        } else {
            // Check if item_code already exists
            $check_stmt = $db->prepare("SELECT id FROM lab_inventory WHERE item_code = :item_code AND deleted_at IS NULL");
            $check_stmt->execute([':item_code' => $_POST['item_code']]);
            
            if ($check_stmt->fetch()) {
                $error_message = "Kode item sudah ada! Gunakan kode yang berbeda.";
            } else {
                // Check if resource_id exists
                $resource_check = $db->prepare("SELECT id FROM resources WHERE id = :resource_id AND deleted_at IS NULL");
                $resource_check->execute([':resource_id' => $_POST['resource_id']]);
                
                if (!$resource_check->fetch()) {
                    $error_message = "Lab yang dipilih tidak valid.";
                } else {
                    // Insert new inventory item
                    $sql = "INSERT INTO lab_inventory (
                                resource_id, item_code, item_name, category, brand, model, 
                                serial_number, specifications, `condition`, status, quantity,
                                quantity_good, quantity_broken, quantity_backup, notes, created_at
                            ) VALUES (
                                :resource_id, :item_code, :item_name, :category, :brand, :model,
                                :serial_number, :specifications, :condition, :status, :quantity,
                                :quantity_good, :quantity_broken, :quantity_backup, :notes, NOW()
                            )";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        ':resource_id' => $_POST['resource_id'],
                        ':item_code' => trim($_POST['item_code']),
                        ':item_name' => trim($_POST['item_name']),
                        ':category' => $_POST['category'],
                        ':brand' => !empty($_POST['brand']) ? trim($_POST['brand']) : null,
                        ':model' => !empty($_POST['model']) ? trim($_POST['model']) : null,
                        ':serial_number' => !empty($_POST['serial_number']) ? trim($_POST['serial_number']) : null,
                        ':specifications' => !empty($_POST['specifications']) ? trim($_POST['specifications']) : null,
                        ':condition' => $_POST['condition'],
                        ':status' => $_POST['status'],
                        ':quantity' => intval($_POST['quantity']),
                        ':quantity_good' => intval($_POST['quantity_good'] ?? 0),
                        ':quantity_broken' => intval($_POST['quantity_broken'] ?? 0),
                        ':quantity_backup' => intval($_POST['quantity_backup'] ?? 0),
                        ':notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
                    ]);
                    
                    if ($result) {
                        $success_message = "Inventaris berhasil ditambahkan dengan kode: " . htmlspecialchars($_POST['item_code']);
                        
                        // Clear form
                        $_POST = [];
                    } else {
                        $error_message = "Gagal menambahkan inventaris. Silakan coba lagi.";
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error adding inventory: " . $e->getMessage());
        $error_message = "Terjadi kesalahan database: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("General error adding inventory: " . $e->getMessage());
        $error_message = "Terjadi kesalahan yang tidak terduga. Silakan coba lagi.";
    }
}

// Get labs for dropdown
try {
    $db = getDB();
    $labs_stmt = $db->query("SELECT id, name FROM resources WHERE type = 'lab' AND deleted_at IS NULL ORDER BY name");
    $labs = $labs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($labs)) {
        $error_message = "Tidak ada lab yang tersedia. Silakan tambahkan lab terlebih dahulu.";
    }
} catch (PDOException $e) {
    error_log("Error fetching labs: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data lab.";
    $labs = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Inventaris – <?= htmlspecialchars(APP_NAME) ?></title>

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
  --blue:        #4a8cff;
  --blue-light:  #eaf1ff;
  --amber:       #f5a623;
  --amber-light: #fef3e0;
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
.page { max-width:900px; margin:0 auto; padding:1.8rem 1.5rem 3rem; }

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

/* ─── alerts ─── */
.alert {
  border-radius:12px;
  padding:.9rem 1.2rem;
  margin-bottom:1.4rem;
  font-size:.82rem;
  display:flex; align-items:flex-start; gap:.6rem;
}
.alert-success {
  background:var(--green-light);
  border-left:4px solid var(--green);
  color:#155724;
}
.alert-error {
  background:var(--red-light);
  border-left:4px solid var(--red);
  color:var(--red);
}
.alert strong { font-weight:700; }

/* ─── form card ─── */
.form-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:16px;
  padding:1.8rem 2rem;
}

/* section within form */
.form-section {
  margin-bottom:2rem;
  padding-bottom:2rem;
  border-bottom:1px solid var(--border);
}
.form-section:last-child { border-bottom:none; }

.form-section h3 {
  font-family:'Sora',sans-serif;
  font-size:1rem; font-weight:700;
  color:var(--text-dark);
  margin-bottom:1.2rem;
  padding-bottom:.5rem;
  border-bottom:2px solid var(--blue);
}

.form-grid {
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:.95rem;
}
.form-group { display:flex; flex-direction:column; gap:.3rem; }
.form-group.full { grid-column:1/-1; }

.form-group label {
  font-size:.78rem; font-weight:600;
  color:var(--text-mid);
}
.form-group label .req {
  color:var(--red); margin-left:.15rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding:.6rem .7rem; border-radius:10px;
  border:1.5px solid var(--border);
  font-size:.82rem; font-family:'Inter',sans-serif;
  transition:border-color .2s, box-shadow .2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color:var(--blue); outline:none;
  box-shadow:0 0 0 3px rgba(74,140,255,.12);
}
.form-group textarea { resize:vertical; min-height:90px; }
.form-group small {
  font-size:.72rem; color:var(--text-light);
}

/* breakdown box */
.breakdown-box {
  background:var(--blue-light);
  border-radius:12px;
  padding:1rem;
  margin-top:.5rem;
}
.breakdown-box h4 {
  font-size:.8rem; color:var(--text-mid);
  margin-bottom:.7rem; font-weight:600;
}
.breakdown-hint {
  display:block; margin-top:.6rem;
  font-size:.74rem; color:var(--text-mid);
  padding:.4rem .6rem; background:var(--white);
  border-radius:8px; border-left:3px solid var(--amber);
}
.breakdown-hint strong { color:var(--text-dark); }
#remaining-units { color:var(--green); font-weight:700; }
#remaining-units.negative { color:var(--red); }

/* ─── actions ─── */
.form-actions {
  display:flex; gap:.6rem; align-items:center;
  padding-top:1.2rem;
}
.btn {
  display:inline-flex; align-items:center; gap:.35rem;
  padding:.6rem 1.2rem; border-radius:10px;
  font-size:.82rem; font-weight:600; border:none;
  cursor:pointer; text-decoration:none;
  transition:all .2s;
}
.btn-primary { background:var(--blue); color:#fff; }
.btn-primary:hover:not(:disabled) { background:#3a7bd5; box-shadow:0 3px 12px rgba(74,140,255,.3); }
.btn-primary:disabled { background:var(--border); color:var(--text-light); cursor:not-allowed; }

.btn-secondary { background:var(--white); color:var(--text-mid); border:1.5px solid var(--border); }
.btn-secondary:hover { border-color:var(--text-mid); }

.btn-warning { background:var(--amber); color:#fff; }
.btn-warning:hover { background:#e08a10; }

/* ═══════════════ RESPONSIVE ═══════════════ */
@media(max-width:680px){
  .page { padding:1.2rem 1rem 2.5rem; }
  .hero { flex-direction:column; align-items:flex-start; gap:.5rem; padding:1.2rem 1.3rem; }
  .form-card { padding:1.3rem 1.1rem; }
  .form-grid { grid-template-columns:1fr; }
  .form-actions { flex-direction:column; }
  .btn { width:100%; justify-content:center; }
  .topbar { padding:.7rem 1rem; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    // Submit validation
    form.addEventListener('submit', function(e) {
        const quantity = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
        const good     = parseInt(document.querySelector('input[name="quantity_good"]').value) || 0;
        const broken   = parseInt(document.querySelector('input[name="quantity_broken"]').value) || 0;
        const backup   = parseInt(document.querySelector('input[name="quantity_backup"]').value) || 0;
        
        if (good + broken + backup > quantity) {
            alert('Total breakdown (baik + rusak + cadangan) tidak boleh melebihi jumlah total unit!');
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-uppercase item code
    const itemCode = document.querySelector('input[name="item_code"]');
    if (itemCode) {
        itemCode.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Auto-calculate remaining units
    const quantityInputs = ['quantity', 'quantity_good', 'quantity_broken', 'quantity_backup'];
    quantityInputs.forEach(name => {
        const input = document.querySelector(`input[name="${name}"]`);
        if (input) {
            input.addEventListener('input', updateRemainingUnits);
        }
    });
    
    function updateRemainingUnits() {
        const quantity = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
        const good     = parseInt(document.querySelector('input[name="quantity_good"]').value) || 0;
        const broken   = parseInt(document.querySelector('input[name="quantity_broken"]').value) || 0;
        const backup   = parseInt(document.querySelector('input[name="quantity_backup"]').value) || 0;
        
        const total     = good + broken + backup;
        const remaining = quantity - total;
        
        const remainingEl = document.getElementById('remaining-units');
        if (remainingEl) {
            remainingEl.textContent = remaining;
            remainingEl.className = remaining < 0 ? 'negative' : '';
        }
    }
});
</script>
</head>
<body>

<!-- ═══ TOPBAR ═══ -->
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-logo">🧪</div>
    <div class="topbar-text">
      <h1>Lab Management</h1>
      <span>Tambah Inventaris</span>
    </div>
  </div>
  <div class="topbar-right">
    <a href="inventory_list.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali</a>
  </div>
</div>

<!-- ═══ PAGE ═══ -->
<div class="page">

  <!-- hero -->
  <div class="hero">
    <div class="hero-left">
      <h2>Tambah Inventaris Baru</h2>
      <p>Form input inventaris peralatan dan aset laboratorium</p>
    </div>
    <div class="hero-icon">➕</div>
  </div>

  <?php if ($success_message): ?>
    <div class="alert alert-success">
      <span>✅</span>
      <div><strong>Berhasil!</strong> <?= $success_message ?></div>
    </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
    <div class="alert alert-error">
      <span>❌</span>
      <div><strong>Error:</strong> <?= $error_message ?></div>
    </div>
  <?php endif; ?>

  <!-- form -->
  <div class="form-card">
    <form method="POST" action="">

      <!-- 1. Informasi Dasar -->
      <div class="form-section">
        <h3>Informasi Dasar</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Lab <span class="req">*</span></label>
            <select name="resource_id" required <?= empty($labs) ? 'disabled' : '' ?>>
              <option value="">Pilih Lab</option>
              <?php foreach ($labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab['id']) ?>" 
                        <?= ($_POST['resource_id'] ?? '') == $lab['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($lab['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($labs)): ?>
              <small style="color:var(--red);">Tidak ada lab tersedia</small>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Kode Item <span class="req">*</span></label>
            <input type="text" name="item_code" placeholder="PC-LAB7-001" 
                   value="<?= htmlspecialchars($_POST['item_code'] ?? '') ?>" 
                   pattern="[A-Za-z0-9\-]+" 
                   title="Hanya huruf, angka, dan tanda minus"
                   required>
            <small>Kode unik (huruf, angka, minus)</small>
          </div>

          <div class="form-group full">
            <label>Nama Item <span class="req">*</span></label>
            <input type="text" name="item_name" placeholder="PC Unit 1" 
                   value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" 
                   maxlength="255" required>
          </div>

          <div class="form-group">
            <label>Kategori <span class="req">*</span></label>
            <select name="category" required>
              <option value="">Pilih Kategori</option>
              <option value="computer"   <?= ($_POST['category'] ?? '') == 'computer'   ? 'selected' : '' ?>>Computer</option>
              <option value="peripheral" <?= ($_POST['category'] ?? '') == 'peripheral' ? 'selected' : '' ?>>Peripheral</option>
              <option value="furniture"  <?= ($_POST['category'] ?? '') == 'furniture'  ? 'selected' : '' ?>>Furniture</option>
              <option value="network"    <?= ($_POST['category'] ?? '') == 'network'    ? 'selected' : '' ?>>Network</option>
              <option value="software"   <?= ($_POST['category'] ?? '') == 'software'   ? 'selected' : '' ?>>Software</option>
              <option value="other"      <?= ($_POST['category'] ?? '') == 'other'      ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 2. Detail Produk -->
      <div class="form-section">
        <h3>Detail Produk</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Brand/Merk</label>
            <input type="text" name="brand" placeholder="ASUS" 
                   value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" maxlength="100">
          </div>

          <div class="form-group">
            <label>Model/Tipe</label>
            <input type="text" name="model" placeholder="VivoPC" 
                   value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" maxlength="100">
          </div>

          <div class="form-group full">
            <label>Serial Number</label>
            <input type="text" name="serial_number" placeholder="SN-LAB7-PC001" 
                   value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>" maxlength="100">
          </div>

          <div class="form-group full">
            <label>Spesifikasi</label>
            <textarea name="specifications" placeholder="CPU: Intel i5-10400, RAM: 8GB DDR4, Storage: 256GB SSD"><?= htmlspecialchars($_POST['specifications'] ?? '') ?></textarea>
            <small>Bisa dalam format JSON atau text biasa</small>
          </div>
        </div>
      </div>

      <!-- 3. Jumlah & Breakdown -->
      <div class="form-section">
        <h3>Jumlah & Breakdown Unit</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Total Jumlah Unit <span class="req">*</span></label>
            <input type="number" name="quantity" placeholder="1" 
                   value="<?= htmlspecialchars($_POST['quantity'] ?? '1') ?>"
                   min="1" step="1" required>
            <small>Total unit dari item ini</small>
          </div>

          <div class="form-group full">
            <div class="breakdown-box">
              <h4>Breakdown per Kondisi:</h4>
              <div class="form-grid">
                <div class="form-group">
                  <label>Unit Baik (Dipakai)</label>
                  <input type="number" name="quantity_good" placeholder="0" 
                         value="<?= htmlspecialchars($_POST['quantity_good'] ?? '0') ?>"
                         min="0" step="1">
                </div>

                <div class="form-group">
                  <label>Unit Rusak</label>
                  <input type="number" name="quantity_broken" placeholder="0" 
                         value="<?= htmlspecialchars($_POST['quantity_broken'] ?? '0') ?>"
                         min="0" step="1">
                </div>

                <div class="form-group">
                  <label>Unit Cadangan</label>
                  <input type="number" name="quantity_backup" placeholder="0" 
                         value="<?= htmlspecialchars($_POST['quantity_backup'] ?? '0') ?>"
                         min="0" step="1">
                </div>
              </div>
              <span class="breakdown-hint">
                ⚠️ Total breakdown tidak boleh melebihi total unit. 
                Sisa unit yang belum tercatat: <strong id="remaining-units">0</strong>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. Status & Kondisi -->
      <div class="form-section">
        <h3>Status & Kondisi</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Kondisi <span class="req">*</span></label>
            <select name="condition" required>
              <option value="excellent" <?= ($_POST['condition'] ?? 'good') == 'excellent' ? 'selected' : '' ?>>Excellent (Sempurna)</option>
              <option value="good"      <?= ($_POST['condition'] ?? 'good') == 'good'      ? 'selected' : '' ?>>Good (Baik)</option>
              <option value="fair"      <?= ($_POST['condition'] ?? '')     == 'fair'      ? 'selected' : '' ?>>Fair (Cukup)</option>
              <option value="poor"      <?= ($_POST['condition'] ?? '')     == 'poor'      ? 'selected' : '' ?>>Poor (Buruk)</option>
              <option value="broken"    <?= ($_POST['condition'] ?? '')     == 'broken'    ? 'selected' : '' ?>>Broken (Rusak)</option>
            </select>
          </div>

          <div class="form-group">
            <label>Status <span class="req">*</span></label>
            <select name="status" required>
              <option value="active"      <?= ($_POST['status'] ?? 'active') == 'active'      ? 'selected' : '' ?>>Active (Aktif)</option>
              <option value="inactive"    <?= ($_POST['status'] ?? '')       == 'inactive'    ? 'selected' : '' ?>>Inactive (Tidak Aktif)</option>
              <option value="maintenance" <?= ($_POST['status'] ?? '')       == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
              <option value="retired"     <?= ($_POST['status'] ?? '')       == 'retired'     ? 'selected' : '' ?>>Retired (Pensiun)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 5. Catatan -->
      <div class="form-section">
        <h3>Catatan Tambahan</h3>
        <div class="form-group full">
          <label>Catatan</label>
          <textarea name="notes" placeholder="Catatan atau informasi tambahan tentang item ini..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Actions -->
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" <?= empty($labs) ? 'disabled' : '' ?>>
          <i class="fas fa-save"></i> Simpan Inventaris
        </button>
        <button type="reset" class="btn btn-warning">
          <i class="fas fa-redo"></i> Reset Form
        </button>
        <a href="inventory_list.php" class="btn btn-secondary">
          <i class="fas fa-times"></i> Batal
        </a>
      </div>

    </form>
  </div>

</div><!-- /page -->

</body>
</html>