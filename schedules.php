<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

$pdo = getDB();

// ============================================================
// API ENDPOINTS  —  seluruh logika ini tidak diubah sama sekali
// ============================================================

// 1) GET ?events=1  →  JSON untuk FullCalendar
if (isset($_GET['events'])) {
    header('Content-Type: application/json');
    try {
        $events = [];
        $sql = "SELECT b.*, 
                       ts.start_time, ts.end_time,
                       r.name as lab_name,
                       o.name as institution_name
                FROM bookings b
                JOIN time_slots ts ON b.time_slot_id = ts.id
                JOIN resources r ON b.resource_id = r.id
                JOIN organizations o ON b.organization_id = o.id
                WHERE b.status IN ('approved', 'pending')
                  AND r.type = 'lab'
                  AND r.status = 'active'
                ORDER BY b.booking_date ASC";
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bookings as $row) {
            $events[] = [
                "id" => "booking-" . $row['id'],
                "title" => $row['lab_name'] . " - " . $row['teacher_name'] .
                          ($row['class_name'] ? " (" . $row['class_name'] . ")" : ""),
                "start" => $row['booking_date'] . "T" . $row['start_time'],
                "end"   => $row['booking_date'] . "T" . $row['end_time'],
                "backgroundColor" => $row['status'] === 'approved' ? "#00c9a7" : "#f5a623",
                "borderColor"     => $row['status'] === 'approved' ? "#059669" : "#e08a10",
                "textColor"       => "#ffffff",
                "extendedProps"   => [
                    "institution"      => $row['institution_name'],
                    "subject"          => $row['subject_name'] ?? '-',
                    "status"           => $row['status'],
                    "type"             => "booking",
                    "class"            => $row['class_name'] ?? '-',
                    "participant_count"=> $row['participant_count'] ?? 0
                ]
            ];
        }
        echo json_encode($events);
    } catch (PDOException $e) {
        error_log("Error fetching events: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// 2) GET ?ajax=list  →  JSON { bookings[], schedules[] }
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: application/json');
    try {
        $data = ['success' => true, 'data' => ['bookings' => [], 'schedules' => []]];

        $sql = "SELECT b.*, 
                       ts.name as time_slot_name, ts.start_time, ts.end_time,
                       r.name as lab_name, r.id as resource_id,
                       o.name as institution_name
                FROM bookings b
                JOIN time_slots ts ON b.time_slot_id = ts.id
                JOIN resources r ON b.resource_id = r.id
                JOIN organizations o ON b.organization_id = o.id
                WHERE b.status IN ('approved', 'pending')
                  AND r.type = 'lab'
                  AND r.status = 'active'
                ORDER BY b.booking_date ASC";
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bookings as &$booking) {
            $booking['lab_id'] = $booking['resource_id'];
        }
        $data['data']['bookings'] = $bookings;

        $sql = "SELECT s.*, 
                       ts.name as time_slot_name, ts.start_time, ts.end_time,
                       r.name as lab_name, r.id as resource_id,
                       c.name as class_name, c.grade_level,
                       o.name as institution_name
                FROM schedules s
                JOIN time_slots ts ON s.time_slot_id = ts.id
                JOIN resources r ON s.resource_id = r.id
                JOIN classes c ON s.class_id = c.id
                JOIN organizations o ON c.organization_id = o.id
                WHERE s.status = 'active'
                  AND r.type = 'lab'
                  AND r.status = 'active'
                ORDER BY 
                  FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                  ts.slot_order";
        $stmt = $pdo->query($sql);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($schedules as &$schedule) {
            $schedule['lab_id'] = $schedule['resource_id'];
            $dayMap = [
                'Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
                'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'
            ];
            if (isset($dayMap[$schedule['day_of_week']])) {
                $schedule['day_of_week'] = $dayMap[$schedule['day_of_week']];
            }
        }
        $data['data']['schedules'] = $schedules;
        echo json_encode($data);
    } catch (PDOException $e) {
        error_log("Error fetching list: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $e->getMessage()]);
    }
    exit;
}

// 3) POST aksi=booking  →  insert + conflict-check + activity log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'booking') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $required_fields = [
            'teacher_name','teacher_phone','class_name','subject_name',
            'booking_date','time_slot_id','lab_id','institution_id','title'
        ];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field $field wajib diisi");
            }
        }

        $bookingDate  = $_POST['booking_date'];
        $timeSlotId   = intval($_POST['time_slot_id']);
        $labId        = intval($_POST['lab_id']);

        if (strtotime($bookingDate) < strtotime(date('Y-m-d'))) {
            throw new Exception("Tanggal booking tidak boleh di masa lalu");
        }

        // Konflik booking
        $sqlCheck = "SELECT b.id, r.name as lab_name, ts.name as time_slot_name
                     FROM bookings b
                     JOIN resources r ON b.resource_id = r.id
                     JOIN time_slots ts ON b.time_slot_id = ts.id
                     WHERE b.resource_id = :lab_id
                       AND b.time_slot_id = :slot_id
                       AND b.booking_date = :booking_date
                       AND b.status IN ('approved', 'pending')
                     LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':lab_id'=>$labId,':slot_id'=>$timeSlotId,':booking_date'=>$bookingDate]);
        $conflict = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($conflict) {
            throw new Exception("Slot waktu sudah terpakai! " .
                $conflict['lab_name'] . " pada " . $conflict['time_slot_name'] . " sudah dibooking.");
        }

        // Konflik jadwal rutin
        $dayName = date('l', strtotime($bookingDate));
        $sqlSchedCheck = "SELECT s.id, c.name as class_name, s.teacher_name
                          FROM schedules s
                          JOIN classes c ON s.class_id = c.id
                          WHERE s.resource_id = :lab_id
                            AND s.time_slot_id = :slot_id
                            AND s.day_of_week = :day_name
                            AND s.status = 'active'
                          LIMIT 1";
        $stmtSchedCheck = $pdo->prepare($sqlSchedCheck);
        $stmtSchedCheck->execute([':lab_id'=>$labId,':slot_id'=>$timeSlotId,':day_name'=>$dayName]);
        $schedConflict = $stmtSchedCheck->fetch(PDO::FETCH_ASSOC);
        if ($schedConflict) {
            throw new Exception("Slot waktu bentrok dengan jadwal rutin! " .
                $schedConflict['class_name'] . " - " . $schedConflict['teacher_name']);
        }

        // Insert
        $sql = "INSERT INTO bookings (
                    booking_date, time_slot_id, resource_id, organization_id,
                    teacher_name, teacher_phone, class_name, subject_name,
                    title, description, participant_count, status
                ) VALUES (
                    :date, :slot, :lab, :institution,
                    :teacher, :phone, :class, :subject,
                    :title, :desc, :count, 'pending'
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':date'=>$bookingDate, ':slot'=>$timeSlotId, ':lab'=>$labId,
            ':institution'=>intval($_POST['institution_id']),
            ':teacher'=>$_POST['teacher_name'], ':phone'=>$_POST['teacher_phone'],
            ':class'=>$_POST['class_name'], ':subject'=>$_POST['subject_name'],
            ':title'=>$_POST['title'], ':desc'=>$_POST['description'] ?? '',
            ':count'=>intval($_POST['participant_count'] ?? 0)
        ]);
        $booking_id   = $pdo->lastInsertId();
        $booking_code = 'BKG-' . date('Ymd') . '-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);

        // Activity log
        $sqlLog = "INSERT INTO activity_logs (
                       user_name, action, entity_type, entity_id, description, created_at
                   ) VALUES (
                       :user_name, 'CREATE', 'booking', :entity_id, :description, CURRENT_TIMESTAMP
                   )";
        $stmtLog = $pdo->prepare($sqlLog);
        $stmtLog->execute([
            ':user_name'=>$_POST['teacher_name'],
            ':entity_id'=>$booking_id,
            ':description'=>'Booking baru dibuat: ' . $booking_code . ' - ' . $_POST['title']
        ]);

        $pdo->commit();
        echo json_encode([
            'success'=>true,
            'message'=>'Booking berhasil dibuat! Menunggu persetujuan admin.',
            'booking_code'=>$booking_code,
            'booking_id'=>$booking_id
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Booking error: " . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ============================================================
// FETCH DATA UNTUK RENDER  —  tidak diubah
// ============================================================
$labs = [];
$time_slots = [];
$institutions = [];

try {
    $stmt = $pdo->query("
        SELECT id, name, capacity, building, floor, room_number, status
        FROM resources WHERE type = 'lab' AND status = 'active' ORDER BY name
    ");
    $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, name, start_time, end_time, slot_order, is_break
        FROM time_slots WHERE is_active = 1 ORDER BY slot_order
    ");
    $time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, name, type FROM organizations WHERE is_active = 1 ORDER BY name
    ");
    $institutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

function getWeekRange($date = null) {
    $date = $date ? new DateTime($date) : new DateTime();
    $day  = $date->format('w');
    $diffToMonday = $day === '0' ? -6 : 1 - $day;
    $monday = clone $date;
    $monday->modify("$diffToMonday days");
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    return [
        'monday'            => $monday->format('Y-m-d'),
        'sunday'            => $sunday->format('Y-m-d'),
        'monday_formatted'  => $monday->format('d F Y'),
        'sunday_formatted'  => $sunday->format('d F Y')
    ];
}

$week_range  = getWeekRange();
$app_name    = 'Sistem Manajemen Laboratorium';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $app_name ?> – Jadwal Lab</title>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Libs CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ═══════════════════════════════════════════════════════════
   DESIGN TOKENS  —  matches index.php palette exactly
   ═══════════════════════════════════════════════════════════ */
:root {
  --cream:       #faf8f5;
  --white:       #ffffff;
  --text-dark:   #1e1e2e;
  --text-mid:    #5a5a6e;
  --text-light:  #9a9aad;
  --border:      #eae8e3;

  --cyan:        #00c9a7;   /* routine  */
  --cyan-light:  #e6faf5;
  --pink:        #e8609a;
  --pink-light:  #fde9f1;
  --amber:       #f5a623;   /* pending  */
  --amber-light: #fef3e0;
  --blue:        #4a8cff;   /* primary  */
  --blue-light:  #eaf1ff;
  --violet:      #8b6bea;
  --violet-light:#f0ecfd;
  --coral:       #ff6f61;
  --coral-light: #fff0ee;

  --approved:    #00c9a7;
  --approved-dk: #059669;
}

/* ═══════════════════════════════════════════════════════════
   RESET  &  BASE
   ═══════════════════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--text-dark);
  min-height: 100vh;
}

/* ═══════════════════════════════════════════════════════════
   TOP BAR  —  sticky nav, identical structure to index
   ═══════════════════════════════════════════════════════════ */
.topbar {
  background: var(--white);
  border-bottom: 1px solid var(--border);
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: 0 2px 12px rgba(0,0,0,.04);
}
.topbar-left { display:flex; align-items:center; gap:.85rem; }
.topbar-logo {
  width:42px; height:42px;
  background: linear-gradient(135deg, var(--cyan), var(--blue));
  border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem;
}
.topbar-text h1 {
  font-family:'Sora',sans-serif;
  font-size:1.2rem; font-weight:700; color:var(--text-dark); line-height:1.2;
}
.topbar-text span { font-size:.78rem; color:var(--text-light); }

.topbar-right { display:flex; align-items:center; gap:1rem; }

/* Sync badge */
.sync-badge {
  display:inline-flex; align-items:center; gap:.45rem;
  padding:.35rem .75rem; border-radius:20px;
  font-size:.76rem; font-weight:600;
  background: var(--cyan-light); color: var(--approved-dk);
  transition: background .3s, color .3s;
}
.sync-badge.syncing {
  background: var(--amber-light); color: #92400e;
}
.sync-dot {
  width:7px; height:7px; border-radius:50%;
  background: var(--approved-dk);
  animation: pulse 2s ease-in-out infinite;
}
.sync-badge.syncing .sync-dot { background: var(--amber); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* Back link */
.back-link {
  display:inline-flex; align-items:center; gap:.4rem;
  font-size:.82rem; color:var(--text-light);
  text-decoration:none; font-weight:500;
  transition: color .2s;
}
.back-link:hover { color:var(--blue); }

/* ═══════════════════════════════════════════════════════════
   PAGE SHELL
   ═══════════════════════════════════════════════════════════ */
.page { max-width:1400px; margin:0 auto; padding:1.8rem 1.5rem 3rem; }

/* ─── hero banner (week info) ─── */
.hero {
  background: linear-gradient(135deg, #1e3a5f 0%, #1a2e4a 60%, #16243b 100%);
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
.hero-left h2 {
  font-family:'Sora',sans-serif;
  font-size:1.45rem; font-weight:700; margin-bottom:.25rem;
}
.hero-left p { font-size:.85rem; opacity:.7; }
.hero-week {
  position:relative; z-index:1;
  background:rgba(255,255,255,.1);
  backdrop-filter:blur(4px);
  padding:.55rem 1.1rem; border-radius:12px;
  font-size:.82rem; font-weight:600;
  display:flex; align-items:center; gap:.5rem;
}
.hero-week i { opacity:.7; }

/* ─── section label ─── */
.section-label {
  font-size:.76rem; font-weight:600;
  text-transform:uppercase; letter-spacing:1.5px;
  color:var(--text-light); margin-bottom:.75rem;
  display:flex; align-items:center; gap:.6rem;
}
.section-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* ─── toolbar row ─── */
.toolbar {
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:.75rem;
  margin-bottom:1.2rem;
}
.toolbar-left { display:flex; gap:.6rem; flex-wrap:wrap; }

.btn-pill {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.55rem 1.1rem; border-radius:10px;
  font-size:.82rem; font-weight:600;
  border:1.5px solid var(--border);
  background:var(--white); color:var(--text-mid);
  cursor:pointer; text-decoration:none;
  transition: all .2s;
}
.btn-pill:hover {
  border-color:var(--blue); color:var(--blue);
  box-shadow:0 3px 12px rgba(74,140,255,.15);
}
.btn-pill.primary {
  background: linear-gradient(135deg, var(--cyan), var(--approved-dk));
  border-color:transparent; color:#fff;
}
.btn-pill.primary:hover {
  box-shadow:0 4px 16px rgba(0,201,167,.35);
  border-color:transparent; color:#fff;
}

/* ─── legend ─── */
.legend {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:14px;
  padding:.7rem 1.2rem;
  display:flex; gap:1.2rem; flex-wrap:wrap; align-items:center;
  margin-bottom:1.4rem;
}
.legend-item { display:flex; align-items:center; gap:.5rem; font-size:.8rem; font-weight:500; color:var(--text-mid); }
.legend-swatch {
  width:22px; height:22px; border-radius:6px;
  box-shadow:0 1px 4px rgba(0,0,0,.12);
}
.ls-routine  { background:var(--cyan); }
.ls-approved { background:var(--blue); }
.ls-pending  { background:var(--amber); }
.ls-empty    { background:#fff; border:2px dashed var(--border); box-shadow:none; }

/* ═══════════════════════════════════════════════════════════
   LAB GRID CARD
   ═══════════════════════════════════════════════════════════ */
.lab-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:18px;
  overflow:hidden;
  margin-bottom:1.4rem;
  box-shadow:0 4px 20px rgba(0,0,0,.05);
}

/* lab card header */
.lab-card-head {
  display:flex; align-items:center; gap:.75rem;
  padding:1rem 1.3rem;
  border-bottom:1px solid var(--border);
  background:var(--cream);
}
.lab-card-head-icon {
  width:38px; height:38px; border-radius:10px;
  background:var(--blue-light);
  display:flex; align-items:center; justify-content:center;
  font-size:1.1rem;
}
.lab-card-head h3 {
  font-family:'Sora',sans-serif;
  font-size:1.1rem; font-weight:700; color:var(--text-dark);
}
.lab-card-head .cap-badge {
  margin-left:auto;
  font-size:.74rem; font-weight:600;
  background:var(--blue-light); color:var(--blue);
  padding:.28rem .7rem; border-radius:8px;
}

/* ─── weekly table ─── */
.week-table-wrap { overflow-x:auto; }

table.week-tbl {
  width:100%; border-collapse:collapse;
  min-width:700px;
}

/* header row */
table.week-tbl thead th {
  background:var(--white);
  border-bottom:2px solid var(--border);
  padding:.65rem .5rem;
  font-size:.75rem; font-weight:600;
  text-transform:uppercase; letter-spacing:1px;
  color:var(--text-light);
  text-align:center;
  position:sticky; top:0;
}
table.week-tbl thead th:first-child {
  text-align:left; padding-left:1rem;
  color:var(--text-mid);
}
/* highlight today column */
table.week-tbl thead th.today-col {
  background:var(--blue-light);
  color:var(--blue);
}

/* time-label cell */
table.week-tbl .time-cell {
  padding:.6rem .5rem .6rem 1rem;
  border-bottom:1px solid var(--border);
  min-width:100px;
  background:var(--cream);
}
table.week-tbl .time-cell .time-name {
  font-size:.78rem; font-weight:600; color:var(--text-dark);
}
table.week-tbl .time-cell .time-range {
  font-size:.7rem; color:var(--text-light); margin-top:.1rem;
}

/* break row */
.break-row td {
  background: linear-gradient(90deg, var(--amber-light), #fff5e0);
  border-bottom:1px solid var(--border);
  padding:.5rem 1rem;
  font-size:.75rem; font-weight:600;
  color:var(--amber); text-transform:uppercase;
  letter-spacing:1.5px;
}

/* schedule cell */
.sched-cell {
  padding:.4rem;
  border-bottom:1px solid var(--border);
  border-left:1px solid var(--border);
  min-height:68px;
  vertical-align:middle;
  text-align:center;
  position:relative;
  transition: background .18s;
}
.sched-cell.empty { cursor:pointer; }
.sched-cell.empty:hover { background:var(--blue-light); }

/* + icon on empty hover */
.sched-cell.empty::after {
  content:'+';
  position:absolute;
  inset:0; display:flex; align-items:center; justify-content:center;
  font-size:1.6rem; color:var(--blue); opacity:0;
  transition:opacity .2s; pointer-events:none;
}
.sched-cell.empty:hover::after { opacity:.55; }

/* pill inside cell */
.cell-pill {
  display:inline-block;
  width:calc(100% - 6px);
  padding:.45rem .5rem;
  border-radius:8px;
  font-size:.74rem;
  line-height:1.4;
  color:#fff;
  text-align:left;
  box-shadow:0 2px 6px rgba(0,0,0,.12);
}
.cell-pill strong { display:block; font-size:.78rem; margin-bottom:.1rem; }
.cell-pill small { opacity:.88; }

.pill-routine  { background: linear-gradient(135deg, var(--cyan), var(--approved-dk)); }
.pill-approved { background: linear-gradient(135deg, var(--blue), #3a7bd5); }
.pill-pending  { background: linear-gradient(135deg, var(--amber), #e08a10); color:var(--text-dark); }
.pill-pending small { opacity:.75; }

/* ═══════════════════════════════════════════════════════════
   FULLCALENDAR CARD
   ═══════════════════════════════════════════════════════════ */
.calendar-card {
  background:var(--white);
  border:1.5px solid var(--border);
  border-radius:18px;
  overflow:hidden;
  box-shadow:0 4px 20px rgba(0,0,0,.05);
  margin-top:2rem;
}
.calendar-card-head {
  display:flex; align-items:center; gap:.75rem;
  padding:1rem 1.3rem;
  border-bottom:1px solid var(--border);
  background:var(--cream);
}
.calendar-card-head-icon {
  width:38px; height:38px; border-radius:10px;
  background:var(--violet-light);
  display:flex; align-items:center; justify-content:center;
  font-size:1.1rem;
}
.calendar-card-head h3 {
  font-family:'Sora',sans-serif;
  font-size:1.1rem; font-weight:700;
}
.calendar-card-head .cal-note {
  margin-left:auto; font-size:.74rem; color:var(--text-light);
  background:var(--border); padding:.28rem .65rem; border-radius:8px;
}

/* FullCalendar overrides */
.fc { font-family:'Inter',sans-serif !important; font-size:.82rem !important; }
.fc-toolbar-title { font-family:'Sora',sans-serif !important; font-size:1.15rem !important; font-weight:700 !important; }
.fc .fc-button {
  background:var(--white) !important; color:var(--text-mid) !important;
  border:1.5px solid var(--border) !important;
  border-radius:8px !important; font-weight:600 !important;
  font-size:.78rem !important; padding:.4rem .75rem !important;
  box-shadow:none !important;
  transition: all .2s !important;
}
.fc .fc-button:hover {
  border-color:var(--blue) !important; color:var(--blue) !important;
  background:var(--blue-light) !important;
}
.fc .fc-button-active, .fc .fc-button-primary.fc-button-active {
  background:var(--blue) !important; color:#fff !important;
  border-color:var(--blue) !important;
}
.fc .fc-col-header-cell {
  background:var(--cream);
  border-bottom:2px solid var(--border) !important;
  color:var(--text-mid) !important; font-weight:600 !important;
}
.fc .fc-day-number { color:var(--text-mid) !important; font-size:.78rem; }
.fc .fc-day.fc-day-today { background:var(--blue-light) !important; }
.fc .fc-event {
  border-radius:6px !important;
  padding:2px 5px !important;
  font-size:.73rem !important; font-weight:600 !important;
  border:none !important;
}
.fc .fc-highlightCells { background:rgba(74,140,255,.06) !important; }
.fc-theme-standard td { border-color:var(--border) !important; }
.fc-theme-standard th { border-color:var(--border) !important; }
.fc .fc-list-day-text { color:var(--text-dark) !important; font-weight:700; }

/* ═══════════════════════════════════════════════════════════
   MODAL
   ═══════════════════════════════════════════════════════════ */
.modal-content {
  border-radius:18px; border:none; overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.15);
}
.modal-header {
  background: linear-gradient(135deg, #1e3a5f, #16243b);
  color:#fff; padding:1.2rem 1.6rem; border:none;
}
.modal-title {
  font-family:'Sora',sans-serif;
  font-size:1.15rem; font-weight:700;
  display:flex; align-items:center; gap:.5rem;
}
.modal-body { padding:1.6rem; }
.modal-footer {
  padding:1rem 1.6rem; border:none;
  background:var(--cream);
  display:flex; justify-content:flex-end; gap:.6rem;
}

/* form inside modal */
.form-label {
  font-size:.8rem; font-weight:600; color:var(--text-mid);
  margin-bottom:.35rem; display:flex; align-items:center; gap:.35rem;
}
.form-control, .form-select {
  border-radius:10px;
  border:1.5px solid var(--border);
  padding:.55rem .7rem;
  font-size:.82rem; font-family:'Inter',sans-serif;
  transition: border-color .2s, box-shadow .2s;
}
.form-control:focus, .form-select:focus {
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(74,140,255,.18);
  outline:none;
}

/* multi-select */
select[multiple] { height:auto !important; padding:.4rem !important; }
select[multiple] option { padding:.45rem .6rem; cursor:pointer; }
select[multiple] option:checked {
  background:var(--blue) !important; color:#fff !important;
}

/* slot badge strip */
.slot-badge {
  display:inline-block;
  background:var(--blue); color:#fff;
  padding:.25rem .6rem; border-radius:14px;
  margin:.2rem; font-size:.75rem; font-weight:600;
}
.info-alert {
  background:var(--blue-light);
  border-left:3px solid var(--blue);
  border-radius:8px;
  padding:.7rem .85rem;
  margin-top:.6rem;
  font-size:.8rem; color:var(--text-mid);
}
.info-alert strong { color:var(--text-dark); }

/* tip box */
.tip-box {
  background:var(--amber-light);
  border-left:3px solid var(--amber);
  border-radius:8px;
  padding:.6rem .85rem;
  font-size:.78rem; color:var(--text-mid);
  margin-bottom:.8rem;
}
.tip-box strong { color:var(--text-dark); }

/* btn overrides inside modal */
.btn-save {
  background: linear-gradient(135deg, var(--cyan), var(--approved-dk));
  color:#fff; border:none; border-radius:10px;
  padding:.55rem 1.3rem; font-weight:600; font-size:.82rem;
  cursor:pointer; transition: box-shadow .2s;
}
.btn-save:hover { box-shadow:0 4px 14px rgba(0,201,167,.35); }
.btn-cancel {
  background:var(--white); color:var(--text-mid);
  border:1.5px solid var(--border); border-radius:10px;
  padding:.55rem 1.1rem; font-weight:600; font-size:.82rem;
  cursor:pointer; transition:border-color .2s;
}
.btn-cancel:hover { border-color:var(--text-mid); }

/* ═══════════════════════════════════════════════════════════
   LOADING OVERLAY
   ═══════════════════════════════════════════════════════════ */
.overlay {
  position:fixed; inset:0;
  background:rgba(30,30,46,.55);
  display:none; align-items:center; justify-content:center;
  z-index:9999;
  backdrop-filter:blur(2px);
}
.overlay.show { display:flex; }
.spinner {
  width:44px; height:44px; border-radius:50%;
  border:4px solid rgba(255,255,255,.2);
  border-top-color:#fff;
  animation:spin .7s linear infinite;
}
@keyframes spin { to{transform:rotate(360deg)} }

/* ═══════════════════════════════════════════════════════════
   SWEETALERT OVERRIDES  (keep consistent)
   ═══════════════════════════════════════════════════════════ */
.swal2-popup { border-radius:18px !important; font-family:'Inter',sans-serif !important; }
.swal2-title { font-family:'Sora',sans-serif !important; color:var(--text-dark) !important; }
.swal2-confirm {
  background:var(--blue) !important; border-radius:10px !important;
  font-weight:600 !important;
}
.swal2-confirm:hover { background:#3a7bd5 !important; }

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════ */
@media(max-width:860px){
  .page { padding:1.2rem 1rem 2.5rem; }
  .hero { flex-direction:column; align-items:flex-start; gap:.6rem; padding:1.2rem 1.3rem; }
  .hero-week { width:100%; justify-content:center; }
  table.week-tbl { min-width:580px; }
}
@media(max-width:540px){
  .topbar { padding:.7rem 1rem; }
  .toolbar { flex-direction:column; align-items:flex-start; }
  .legend { gap:.7rem; }
}
</style>
</head>
<body>

<!-- ═══ LOADING OVERLAY ═══ -->
<div class="overlay" id="overlay"><div class="spinner"></div></div>

<!-- ═══ TOP BAR ═══ -->
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-logo">🧪</div>
    <div class="topbar-text">
      <h1>Lab Management</h1>
      <span>Jadwal &amp; Booking Laboratorium</span>
    </div>
  </div>
  <div class="topbar-right">
    <span class="sync-badge synced" id="syncBadge">
      <span class="sync-dot"></span>
      <span id="syncText">Tersinkronisasi</span>
    </span>
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Beranda</a>
  </div>
</div>

<!-- ═══ PAGE ═══ -->
<div class="page">

  <!-- hero -->
  <div class="hero">
    <div class="hero-left">
      <h2>Jadwal Laboratorium</h2>
      <p>Kelola jadwal pemakaian lab dan buat booking baru</p>
    </div>
    <div class="hero-week">
      <i class="fas fa-calendar-week"></i>
      <?= $week_range['monday_formatted'] ?> &ndash; <?= $week_range['sunday_formatted'] ?>
    </div>
  </div>

  <!-- toolbar -->
  <div class="toolbar">
    <div class="toolbar-left">
      <button class="btn-pill primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
        <i class="fas fa-plus"></i> Booking Baru
      </button>
      <button class="btn-pill" onclick="manualRefresh()">
        <i class="fas fa-sync-alt"></i> Refresh
      </button>
    </div>
  </div>

  <!-- legend -->
  <div class="legend">
    <div class="legend-item"><div class="legend-swatch ls-routine"></div>Jadwal Rutin</div>
    <div class="legend-item"><div class="legend-swatch ls-approved"></div>Booking Disetujui</div>
    <div class="legend-item"><div class="legend-swatch ls-pending"></div>Menunggu Persetujuan</div>
    <div class="legend-item"><div class="legend-swatch ls-empty"></div>Kosong (Klik untuk Booking)</div>
  </div>

  <!-- ─── schedule grid (JS-rendered) ─── -->
  <div class="section-label">Jadwal Mingguan</div>
  <div id="scheduleContainer"></div>

  <!-- ─── calendar ─── -->
  <div class="calendar-card">
    <div class="calendar-card-head">
      <div class="calendar-card-head-icon">📆</div>
      <h3>Kalender Booking</h3>
      <span class="cal-note">Hanya tampilkan booking</span>
    </div>
    <div style="padding:1.2rem;">
      <div id="calendar"></div>
    </div>
  </div>

</div><!-- /page -->

<!-- ═══════════════════════════════════════════════════════════
     BOOKING MODAL  —  semua field & hidden-input identik
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bookingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form id="bookingForm" class="modal-content">
      <input type="hidden" name="aksi"          value="booking">
      <input type="hidden" name="booking_date"  id="hiddenDate">
      <input type="hidden" name="time_slot_id"  id="hiddenSlot">
      <input type="hidden" name="lab_id"        id="hiddenLab">

      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit"></i> Form Booking Lab</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- tip -->
        <div class="tip-box">
          <strong><i class="fas fa-lightbulb"></i> Tips:</strong>
          Anda bisa klik langsung pada slot kosong di tabel jadwal, atau isi form di bawah secara manual.
        </div>

        <div class="row g-3">
          <!-- tanggal / lab / slot -->
          <div class="col-md-4">
            <label class="form-label"><i class="fas fa-calendar"></i> Tanggal Booking *</label>
            <input class="form-control" type="date" id="manualDate" required>
          </div>
          <div class="col-md-4">
            <label class="form-label"><i class="fas fa-desktop"></i> Laboratorium *</label>
            <select class="form-select" id="manualLab" required>
              <option value="">– Pilih Lab –</option>
              <?php foreach($labs as $lab): ?>
                <option value="<?=$lab['id']?>"><?=htmlspecialchars($lab['name'])?> (Kapasitas: <?=$lab['capacity']?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">
              <i class="fas fa-clock"></i> Slot Waktu *
              <small style="color:var(--text-light);font-weight:400;">(bisa pilih &gt;1)</small>
            </label>
            <select class="form-select" id="manualSlot" multiple size="5" required>
              <?php foreach($time_slots as $slot): ?>
                <?php if($slot['is_break'] != 1): ?>
                  <option value="<?=$slot['id']?>"><?=htmlspecialchars($slot['name'])?> (<?=substr($slot['start_time'],0,5)?> – <?=substr($slot['end_time'],0,5)?>)</option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <small style="color:var(--text-light);font-size:.72rem;">Tekan <strong>Ctrl</strong> untuk pilih lebih dari satu</small>
          </div>

          <!-- info alert (slot summary) -->
          <div class="col-12">
            <div class="info-alert" id="scheduleInfoAlert" style="display:none;">
              <strong>Jadwal dipilih:</strong>
              <div id="scheduleInfo" style="margin-top:.3rem;"></div>
            </div>
          </div>

          <div class="col-12"><hr style="border-color:var(--border);"></div>

          <!-- data guru & kelas -->
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-user"></i> Nama Guru *</label>
            <input class="form-control" name="teacher_name" placeholder="Nama guru" required>
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-phone"></i> No. Telepon *</label>
            <input class="form-control" name="teacher_phone" placeholder="081234567890" required>
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-building"></i> Institusi *</label>
            <select class="form-select" name="institution_id" required>
              <option value="">– Pilih Institusi –</option>
              <?php foreach($institutions as $inst): ?>
                <option value="<?=$inst['id']?>"><?=htmlspecialchars($inst['name'])?> (<?=htmlspecialchars($inst['type'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-users"></i> Kelas *</label>
            <input class="form-control" name="class_name" placeholder="Contoh: XII IPA 1" required>
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-book"></i> Mata Pelajaran *</label>
            <input class="form-control" name="subject_name" placeholder="Nama mata pelajaran" required>
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fas fa-user-friends"></i> Jumlah Peserta *</label>
            <input class="form-control" type="number" name="participant_count" placeholder="30" min="1" max="50" required>
          </div>
          <div class="col-12">
            <label class="form-label"><i class="fas fa-heading"></i> Judul Kegiatan *</label>
            <input class="form-control" name="title" placeholder="Contoh: Praktikum Jaringan Komputer" required>
          </div>
          <div class="col-12">
            <label class="form-label"><i class="fas fa-align-left"></i> Deskripsi</label>
            <textarea class="form-control" name="description" rows="3" placeholder="Deskripsi kegiatan (opsional)"></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="fas fa-times"></i> Batal</button>
        <button type="submit"  class="btn-save"><i class="fas fa-save"></i> Simpan Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ LIBS JS ═══ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ─────────────────────────────────────────────────────────
   SELURUH JS LOGIKA IDENTIK DENGAN ORIGINAL
   Hanya class-name / id yang berubah mengikuti HTML baru
   ───────────────────────────────────────────────────────── */

const dayNames = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
const timeSlots = <?= json_encode($time_slots) ?>;
const labs      = <?= json_encode($labs) ?>;

let lastDataHash   = null;
let autoSyncInterval = null;

// ── overlay ──
function showLoading(){ document.getElementById('overlay').classList.add('show'); }
function hideLoading(){ document.getElementById('overlay').classList.remove('show'); }

// ── sync badge ──
function updateSyncStatus(state, text){
  const badge = document.getElementById('syncBadge');
  badge.className = 'sync-badge ' + state;
  document.getElementById('syncText').textContent = text;
}

// ── SweetAlert helper ──
function showAlert(title, text, icon){
  Swal.fire({
    title, text, icon,
    confirmButtonText:'OK',
    confirmButtonColor:'#4a8cff',
    timer: icon==='success' ? 2000 : undefined,
    timerProgressBar: true
  });
}

// ── data-hash ──
function generateDataHash(data){ return JSON.stringify(data); }

// ── check updates (auto / manual) ──
async function checkForUpdates(silent = true){
  try {
    if(!silent) updateSyncStatus('syncing','Memeriksa…');
    const res  = await fetch("schedules.php?ajax=list");
    const json = await res.json();
    if(!json.success) return false;

    const newHash = generateDataHash(json.data);
    if(lastDataHash !== null && newHash !== lastDataHash){
      lastDataHash = newHash;
      await renderSchedule(json.data, silent);
      if(!silent){
        updateSyncStatus('synced','Diperbarui');
        showAlert('Update','Ada perubahan jadwal baru','info');
      }
      return true;
    } else if(lastDataHash === null){
      lastDataHash = newHash;
      await renderSchedule(json.data, silent);
      return true;
    }
    if(!silent) updateSyncStatus('synced','Tersinkronisasi');
    return false;
  } catch(err){
    console.error(err);
    if(!silent){
      updateSyncStatus('synced','Error sinkronisasi');
      showAlert('Error','Gagal memeriksa update','error');
    }
    return false;
  }
}

// ── week helpers ──
function getWeekRange(date = new Date()){
  const day = date.getDay();
  const diff = day===0 ? -6 : 1-day;
  const mon = new Date(date); mon.setDate(date.getDate()+diff);
  const sun = new Date(mon);  sun.setDate(mon.getDate()+6);
  return { monday:mon, sunday:sun };
}
function formatDate(d){
  return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}
function getDayName(dateStr){
  const map={Monday:'Senin',Tuesday:'Selasa',Wednesday:'Rabu',Thursday:'Kamis',Friday:'Jumat',Saturday:'Sabtu',Sunday:'Minggu'};
  return map[new Date(dateStr).toLocaleDateString('en-US',{weekday:'long'})];
}

// ── today column detection ──
function getTodayDayName(){
  const map={0:'Minggu',1:'Senin',2:'Selasa',3:'Rabu',4:'Kamis',5:'Jumat',6:'Sabtu'};
  return map[new Date().getDay()];
}

// ── render weekly grids ──
async function renderSchedule(data, silent=false){
  if(!silent) showLoading();

  let bookings  = data.bookings  || [];
  let schedules = data.schedules || [];

  const { monday } = getWeekRange();
  const mondayStr  = formatDate(monday);
  const sundayStr  = formatDate(new Date(monday)); sundayStr; // keep ref
  const sunday     = new Date(monday); sunday.setDate(monday.getDate()+6);
  const sundayF    = formatDate(sunday);

  bookings = bookings.filter(x=> x.booking_date >= mondayStr && x.booking_date <= sundayF);

  const todayName = getTodayDayName();
  let html = '';

  labs.forEach(lab => {
    html += `
    <div class="lab-card">
      <div class="lab-card-head">
        <div class="lab-card-head-icon">🖥️</div>
        <h3>${lab.name}</h3>
        <span class="cap-badge">Kapasitas ${lab.capacity || '–'}</span>
      </div>
      <div class="week-table-wrap">
        <table class="week-tbl">
          <thead><tr>
            <th>Waktu</th>`;

    dayNames.forEach(d => {
      const isTd = d === todayName;
      html += `<th class="${isTd ? 'today-col' : ''}">${d}${isTd ? ' <small>(Hari ini)</small>' : ''}</th>`;
    });
    html += `</tr></thead><tbody>`;

    timeSlots.forEach(slot => {
      if(slot.is_break == 1){
        html += `<tr class="break-row"><td colspan="${dayNames.length+1}">
                   ☕ ${slot.name.toUpperCase()}
                 </td></tr>`;
      } else {
        html += `<tr>
                   <td class="time-cell">
                     <div class="time-name">${slot.name}</div>
                     <div class="time-range">${slot.start_time.substring(0,5)} – ${slot.end_time.substring(0,5)}</div>
                   </td>`;

        dayNames.forEach((day, idx) => {
          let cellContent = '';
          let isEmpty      = true;

          // jadwal rutin
          schedules.forEach(s => {
            if(s.day_of_week===day && s.time_slot_id==slot.id && s.lab_id==lab.id){
              isEmpty = false;
              cellContent = `<div class="cell-pill pill-routine">
                <strong>${s.class_name||'–'}</strong>
                ${s.teacher_name}<br>
                <small>📚 ${s.subject_name||'–'}</small>
              </div>`;
            }
          });

          // booking
          bookings.forEach(b => {
            if(getDayName(b.booking_date)===day && b.time_slot_id==slot.id && b.lab_id==lab.id){
              isEmpty = false;
              const cls = b.status==='approved' ? 'pill-approved' : 'pill-pending';
              cellContent = `<div class="cell-pill ${cls}">
                <strong>${b.title||b.class_name}</strong>
                ${b.teacher_name}<br>
                <small>🏢 ${b.institution_name}</small><br>
                <small>📚 ${b.subject_name||'–'}</small>
              </div>`;
            }
          });

          const cellDate = new Date(monday);
          cellDate.setDate(monday.getDate()+idx);
          const cellDateStr = formatDate(cellDate);

          html += `<td class="sched-cell ${isEmpty?'empty':''}"
                      data-lab="${lab.id}"
                      data-lab-name="${lab.name}"
                      data-slot="${slot.id}"
                      data-slot-name="${slot.name}"
                      data-date="${cellDateStr}"
                      data-day="${day}"
                      data-time="${slot.start_time.substring(0,5)} – ${slot.end_time.substring(0,5)}">
                      ${cellContent}
                   </td>`;
        });
        html += `</tr>`;
      }
    });
    html += `</tbody></table></div></div>`;
  });

  document.getElementById('scheduleContainer').innerHTML = html;
  if(!silent) hideLoading();
}

// ── manual refresh ──
async function manualRefresh(){
  updateSyncStatus('syncing','Memperbarui…');
  lastDataHash = null; // force re-render
  await checkForUpdates(false);
  if(calendar) calendar.refetchEvents();
}

// ── auto-sync ──
function startSmartSync(){
  autoSyncInterval = setInterval(()=> checkForUpdates(true), 30000);
}

// ═══════════════════════════════════════════════════════════
//  FULLCALENDAR
// ═══════════════════════════════════════════════════════════
let calendar;

document.addEventListener('DOMContentLoaded', function(){
  const calEl = document.getElementById('calendar');

  calendar = new FullCalendar.Calendar(calEl, {
    initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
    locale:'id',
    height: window.innerWidth < 768 ? 'auto' : 520,
    headerToolbar:{
      left:'prev,next today',
      center:'title',
      right: window.innerWidth < 768 ? 'listWeek,dayGridMonth' : 'dayGridMonth,timeGridWeek,listWeek'
    },
    buttonText:{ today:'Hari Ini', month:'Bulan', week:'Minggu', day:'Hari', list:'List' },
    events:{
      url:'schedules.php?events=1',
      method:'GET',
      failure:()=> showAlert('Error','Gagal memuat kalender','error')
    },
    eventClick: function(info){
      const ev = info.event;
      const sTime = ev.start.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
      const eTime = ev.end   ? ev.end.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}) : '–';
      const sDate = ev.start.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

      let statusBadge = '', statusIcon = '';
      if(ev.extendedProps.status==='approved'){
        statusBadge = '<span class="badge bg-success fs-6">✓ Disetujui</span>';
        statusIcon  = 'success';
      } else {
        statusBadge = '<span class="badge bg-warning text-dark fs-6">⏳ Menunggu Persetujuan</span>';
        statusIcon  = 'warning';
      }

      Swal.fire({
        title:'<i class="fas fa-calendar-check"></i> Detail Booking',
        html:`
          <div class="text-start" style="padding:8px 4px;">
            <div class="mb-3">${statusBadge}</div>
            <table class="table table-sm table-borderless" style="font-size:.82rem;">
              <tr><td style="width:38%;"><strong>📅 Tanggal</strong></td><td>${sDate}</td></tr>
              <tr><td><strong>🕐 Waktu</strong></td><td>${sTime} – ${eTime}</td></tr>
              <tr><td><strong>🖥️ Laboratorium</strong></td><td>${ev.title.split(' - ')[0]}</td></tr>
              <tr><td><strong>👤 Guru</strong></td><td>${(ev.title.split(' - ')[1]||'').split(' (')[0]||'–'}</td></tr>
              <tr><td><strong>🎓 Kelas</strong></td><td>${ev.extendedProps.class||'–'}</td></tr>
              <tr><td><strong>🏢 Institusi</strong></td><td>${ev.extendedProps.institution||'–'}</td></tr>
              <tr><td><strong>📚 Mata Pelajaran</strong></td><td>${ev.extendedProps.subject||'–'}</td></tr>
              <tr><td><strong>👥 Peserta</strong></td><td>${ev.extendedProps.participant_count||'–'} orang</td></tr>
            </table>
          </div>`,
        icon: statusIcon,
        confirmButtonText:'Tutup',
        width:'560px'
      });
    },
    eventDidMount: function(info){ info.el.title = info.event.title; }
  });

  calendar.render();

  // initial load
  checkForUpdates(false);
  startSmartSync();

  // modal show — cek apakah hidden values sudah diset
  $('#bookingModal').on('show.bs.modal', function(){
    if(!$("#hiddenDate").val() || !$("#hiddenLab").val() || !$("#hiddenSlot").val()){
      $("#scheduleInfoAlert").hide();
    }
  });

  // modal close — reset
  $('#bookingModal').on('hidden.bs.modal', function(){
    $("#bookingForm")[0].reset();
    $("#manualDate").val('');
    $("#manualLab").val('');
    $("#manualSlot").val('');
    $("#hiddenDate").val('');
    $("#hiddenLab").val('');
    $("#hiddenSlot").val('');
    $("#scheduleInfoAlert").hide();
  });
});

// ── min-date = hari ini ──
document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('manualDate').setAttribute('min', new Date().toISOString().split('T')[0]);
});

// ═══════════════════════════════════════════════════════════
//  SYNC manual fields ↔ hidden fields  +  info alert
// ═══════════════════════════════════════════════════════════
$("#manualDate, #manualLab, #manualSlot").on("change", function(){
  const date  = $("#manualDate").val();
  const lab   = $("#manualLab").val();
  const labName = $("#manualLab option:selected").text();
  const slots = $("#manualSlot").val() || [];

  if(date) $("#hiddenDate").val(date);
  if(lab)  $("#hiddenLab").val(lab);
  $("#hiddenSlot").val(slots.length ? JSON.stringify(slots) : '');

  if(date && lab && slots.length){
    const df = new Date(date).toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
    let sHtml = '';
    slots.forEach(id => {
      const opt = $("#manualSlot option[value='"+id+"']");
      if(opt.length) sHtml += `<span class="slot-badge">${opt.text()}</span>`;
    });
    $("#scheduleInfo").html(`
      <strong>🖥️ ${labName.split(' (')[0]}</strong><br>
      📅 ${df}<br>
      <div style="margin-top:.35rem;">🕐 <strong>Slot (${slots.length}):</strong><br>${sHtml}</div>
    `);
    $("#scheduleInfoAlert").slideDown();
  } else {
    $("#scheduleInfoAlert").slideUp();
  }
});

// ═══════════════════════════════════════════════════════════
//  SUBMIT  —  multi-slot loop, identical logic
// ═══════════════════════════════════════════════════════════
$("#bookingForm").submit(async function(e){
  e.preventDefault();

  // sync manual → hidden
  const mDate  = $("#manualDate").val();
  const mLab   = $("#manualLab").val();
  const mSlots = $("#manualSlot").val() || [];
  if(mDate)  $("#hiddenDate").val(mDate);
  if(mLab)   $("#hiddenLab").val(mLab);
  if(mSlots.length) $("#hiddenSlot").val(JSON.stringify(mSlots));

  // read values
  const teacherName  = $("input[name='teacher_name']").val().trim();
  const teacherPhone = $("input[name='teacher_phone']").val().trim();
  const institutionId= $("select[name='institution_id']").val();
  const className    = $("input[name='class_name']").val().trim();
  const subjectName  = $("input[name='subject_name']").val().trim();
  const partCount    = $("input[name='participant_count']").val();
  const title        = $("input[name='title']").val().trim();
  const bookingDate  = $("#hiddenDate").val();
  const labId        = $("#hiddenLab").val();
  let   timeSlotIds  = $("#hiddenSlot").val();

  // validasi required
  if(!teacherName||!teacherPhone||!institutionId||!className||!subjectName||!partCount||!title){
    showAlert('Perhatian','Lengkapi semua field bertanda *','warning'); return;
  }
  if(!bookingDate||!timeSlotIds||!labId){
    showAlert('Perhatian','Pilih tanggal, laboratorium, dan slot waktu','warning'); return;
  }

  // parse slots
  try { timeSlotIds = JSON.parse(timeSlotIds); if(!Array.isArray(timeSlotIds)||!timeSlotIds.length) throw 0; }
  catch{ showAlert('Perhatian','Format slot tidak valid','warning'); return; }

  // validasi telepon
  if(!/^[0-9]{10,13}$/.test(teacherPhone)){
    showAlert('Perhatian','No. telepon harus 10–13 digit angka','warning'); return;
  }

  showLoading();
  updateSyncStatus('syncing','Menyimpan…');

  try {
    let successCount = 0, failedSlots = [], bookingCodes = [];

    for(const slotId of timeSlotIds){
      const fd = new FormData();
      fd.append('aksi','booking');
      fd.append('booking_date', bookingDate);
      fd.append('time_slot_id', slotId);
      fd.append('lab_id',       labId);
      fd.append('institution_id', institutionId);
      fd.append('teacher_name',  teacherName);
      fd.append('teacher_phone', teacherPhone);
      fd.append('class_name',    className);
      fd.append('subject_name',  subjectName);
      fd.append('title',         title);
      fd.append('description',   $("textarea[name='description']").val());
      fd.append('participant_count', partCount);

      try {
        const res  = await fetch("schedules.php",{method:"POST",body:fd});
        const json = await res.json();
        if(json.success){ successCount++; bookingCodes.push(json.booking_code); }
        else { failedSlots.push(`${$("#manualSlot option[value='"+slotId+"']").text()}: ${json.message}`); }
      } catch(err){
        failedSlots.push(`${$("#manualSlot option[value='"+slotId+"']").text()}: Error koneksi`);
      }
    }

    hideLoading();

    if(successCount > 0){
      this.reset();
      $("#bookingModal").modal("hide");
      lastDataHash = null;
      await checkForUpdates(true);
      if(calendar) calendar.refetchEvents();
      updateSyncStatus('synced','Tersinkronisasi');

      const codesHtml = bookingCodes.map(c=> `<span class="slot-badge">${c}</span>`).join('');
      let msg = successCount===timeSlotIds.length
        ? `Semua <strong>${successCount} booking</strong> berhasil!`
        : `<strong>${successCount}</strong> dari ${timeSlotIds.length} booking berhasil.`;

      let extra = '';
      if(failedSlots.length)
        extra = `<div style="background:var(--amber-light);border-left:3px solid var(--amber);border-radius:8px;padding:.6rem .8rem;margin-top:.8rem;font-size:.8rem;text-align:left;">
                   <strong>Gagal:</strong><br>${failedSlots.map(s=>'• '+s).join('<br>')}</div>`;

      Swal.fire({
        title: successCount===timeSlotIds.length ? 'Berhasil ✓' : 'Sebagian Berhasil',
        html:`<p>${msg}</p>
              <p style="margin-top:.7rem;"><strong>Kode Booking:</strong><br>${codesHtml}</p>
              ${extra}
              <p style="margin-top:.6rem;font-size:.76rem;color:var(--text-light);">Simpan kode booking ini untuk referensi.</p>`,
        icon: successCount===timeSlotIds.length ? 'success' : 'warning',
        confirmButtonText:'OK',
        timer: failedSlots.length ? undefined : 5000,
        timerProgressBar: true,
        width:'540px'
      });
    } else {
      updateSyncStatus('synced','Tersinkronisasi');
      Swal.fire({
        title:'Gagal',
        html:`Semua booking gagal:<br><br>${failedSlots.map(s=>'• '+s).join('<br>')}`,
        icon:'error'
      });
    }
  } catch(err){
    hideLoading();
    updateSyncStatus('synced','Error');
    console.error(err);
    showAlert('Error','Kesalahan saat menyimpan booking','error');
  }
});

// ═══════════════════════════════════════════════════════════
//  CLICK HANDLERS  —  empty cell → modal / filled → detail
// ═══════════════════════════════════════════════════════════
$(document).on("click", ".sched-cell.empty", function(e){
  const labId   = $(this).data("lab");
  const labName = $(this).data("lab-name");
  const slotId  = $(this).data("slot");
  const date    = $(this).data("date");

  // masa lalu?
  const today = new Date(); today.setHours(0,0,0,0);
  if(new Date(date) < today){
    showAlert('Perhatian','Tidak bisa booking tanggal yang sudah lewat','warning');
    return;
  }

  // update hidden + manual
  $("#hiddenDate").val(date);
  $("#hiddenLab").val(labId);
  $("#manualDate").val(date);
  $("#manualLab").val(labId);

  // multi / single select
  let current = $("#manualSlot").val() || [];
  if(e.ctrlKey || e.metaKey){
    const i = current.indexOf(String(slotId));
    i > -1 ? current.splice(i,1) : current.push(String(slotId));
  } else {
    current = [String(slotId)];
  }
  $("#manualSlot").val(current);
  $("#hiddenSlot").val(JSON.stringify(current));

  // info alert
  const df = new Date(date).toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  let sHtml = '';
  current.forEach(id => {
    const opt = $("#manualSlot option[value='"+id+"']");
    if(opt.length) sHtml += `<span class="slot-badge">${opt.text()}</span>`;
  });
  $("#scheduleInfo").html(`
    <strong>🖥️ ${labName}</strong><br>
    📅 ${df}<br>
    <div style="margin-top:.35rem;">🕐 <strong>Slot (${current.length}):</strong><br>${sHtml}</div>
    ${e.ctrlKey||e.metaKey ? '<small style="color:var(--text-light);">Ctrl+Click untuk tambah slot</small>' : ''}
  `);
  $("#scheduleInfoAlert").show();

  // buka modal hanya jika bukan Ctrl+Click
  if(!e.ctrlKey && !e.metaKey){
    $("#bookingModal").modal("show");
  } else {
    Swal.fire({ toast:true, position:'top-end', icon:'info', title:`${current.length} slot dipilih`, showConfirmButton:false, timer:1500, timerProgressBar:true });
  }
});

// click filled cell → detail popup
$(document).on("click", ".sched-cell:not(.empty)", function(){
  const pill = $(this).find('.cell-pill');
  if(!pill.length) return;

  const isRoutine  = pill.hasClass('pill-routine');
  const isApproved = pill.hasClass('pill-approved');
  const isPending  = pill.hasClass('pill-pending');

  let badge='', icon='info';
  if(isRoutine)  { badge='<span class="badge bg-success">📋 Jadwal Rutin</span>'; icon='info'; }
  if(isApproved) { badge='<span class="badge bg-primary">✓ Booking Disetujui</span>'; icon='success'; }
  if(isPending)  { badge='<span class="badge bg-warning text-dark">⏳ Menunggu Persetujuan</span>'; icon='warning'; }

  Swal.fire({
    title:'Detail Jadwal',
    html:`<div class="text-start" style="padding:4px 8px;">
            <div class="mb-2">${badge}</div>
            <div style="font-size:.84rem;line-height:1.7;">${pill.html()}</div>
          </div>`,
    icon
  });
});

// ═══════════════════════════════════════════════════════════
//  LIFECYCLE  —  pause sync when hidden, resume on focus
// ═══════════════════════════════════════════════════════════
window.addEventListener('beforeunload', ()=> { if(autoSyncInterval) clearInterval(autoSyncInterval); });
document.addEventListener('visibilitychange', ()=> {
  if(document.hidden){ if(autoSyncInterval){ clearInterval(autoSyncInterval); autoSyncInterval=null; } }
  else { if(!autoSyncInterval){ startSmartSync(); checkForUpdates(true); } }
});
</script>
</body>
</html>