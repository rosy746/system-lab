<?php
// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Helper Functions
 * File ini berisi fungsi-fungsi helper untuk Lab Management System
 */

// ====== HELPER FUNCTIONS FROM CONFIG ======

/**
 * Sanitize input string
 */
function sanitize($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Log activity
 */
function logActivity($action, $entityType, $entityId, $description, $userId = null, $userName = 'System', $changes = null) {
    try {
        $pdo = getDB();
        $sql = "INSERT INTO activity_logs (
                    user_id, user_name, action, entity_type, entity_id, 
                    description, changes, ip_address, created_at
                ) VALUES (
                    :user_id, :user_name, :action, :entity_type, :entity_id,
                    :description, :changes, :ip, CURRENT_TIMESTAMP
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':user_name' => $userName,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':changes' => $changes ? json_encode($changes) : null,
            ':ip' => getClientIP()
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Format date in Indonesian
 */
function formatDateIndo($date, $format = 'd F Y') {
    if (empty($date)) return '-';
    
    $dateObj = is_string($date) ? new DateTime($date) : $date;
    
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $days = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
    ];
    
    $formatted = $dateObj->format($format);
    
    // Replace English month names with Indonesian
    foreach ($months as $num => $name) {
        $formatted = str_replace($dateObj->format('F'), $name, $formatted);
    }
    
    // Replace English day names with Indonesian
    $dayName = $dateObj->format('l');
    if (isset($days[$dayName])) {
        $formatted = str_replace($dayName, $days[$dayName], $formatted);
    }
    
    return $formatted;
}

/**
 * Get Indonesian day name from English
 */
function getDayNameIndo($englishDay) {
    $dayMap = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    
    return $dayMap[$englishDay] ?? $englishDay;
}

/**
 * Check booking conflict
 */
function checkBookingConflict($labId, $timeSlotId, $bookingDate) {
    try {
        $pdo = getDB();
        $sql = "SELECT b.id, r.name as lab_name, ts.name as time_slot_name
                FROM bookings b
                JOIN resources r ON b.resource_id = r.id
                JOIN time_slots ts ON b.time_slot_id = ts.id
                WHERE b.resource_id = :lab_id
                  AND b.time_slot_id = :slot_id
                  AND b.booking_date = :booking_date
                  AND b.status IN ('approved', 'pending')
                  AND b.deleted_at IS NULL
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lab_id' => $labId,
            ':slot_id' => $timeSlotId,
            ':booking_date' => $bookingDate
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error checking booking conflict: " . $e->getMessage());
        return null;
    }
}

/**
 * Check schedule conflict
 */
function checkScheduleConflict($labId, $timeSlotId, $dayName) {
    try {
        $pdo = getDB();
        
        // Convert Indonesian day name to English for database
        $dayMapReverse = [
            'Senin' => 'Monday',
            'Selasa' => 'Tuesday',
            'Rabu' => 'Wednesday',
            'Kamis' => 'Thursday',
            'Jumat' => 'Friday',
            'Sabtu' => 'Saturday',
            'Minggu' => 'Sunday'
        ];
        
        $englishDay = $dayMapReverse[$dayName] ?? $dayName;
        
        $sql = "SELECT s.id, c.name as class_name, s.teacher_name
                FROM schedules s
                JOIN classes c ON s.class_id = c.id
                WHERE s.resource_id = :lab_id
                  AND s.time_slot_id = :slot_id
                  AND s.day_of_week = :day_name
                  AND s.status = 'active'
                  AND s.deleted_at IS NULL
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lab_id' => $labId,
            ':slot_id' => $timeSlotId,
            ':day_name' => $englishDay
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error checking schedule conflict: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate booking code
 */
function generateBookingCode() {
    $pdo = getDB();
    
    // Get count of bookings today
    $sql = "SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = CURDATE()";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] + 1;
    
    return 'BKG-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Format time to HH:MM
 */
function formatTime($time) {
    if (empty($time)) return '-';
    return date('H:i', strtotime($time));
}

/**
 * Check if date is holiday
 */
function isHoliday($date) {
    try {
        $pdo = getDB();
        $sql = "SELECT COUNT(*) as count FROM holidays 
                WHERE date = :date AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking holiday: " . $e->getMessage());
        return false;
    }
}

/**
 * Get resource by ID
 */
function getResource($resourceId) {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM resources WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $resourceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting resource: " . $e->getMessage());
        return null;
    }
}

/**
 * Get time slot by ID
 */
function getTimeSlot($timeSlotId) {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM time_slots WHERE id = :id AND is_active = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $timeSlotId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting time slot: " . $e->getMessage());
        return null;
    }
}

/**
 * Response JSON helper
 */
function jsonResponse($success, $message = '', $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indonesian format)
 */
function isValidPhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if length is between 10-13 digits
    return strlen($phone) >= 10 && strlen($phone) <= 13;
}

/**
 * Validate date format (Y-m-d)
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// ====== RESOURCE FUNCTIONS ======

/**
 * Get all active labs/resources
 */
function getActiveLabs() {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM resources 
                WHERE type = 'lab' 
                AND status = 'active' 
                AND deleted_at IS NULL 
                ORDER BY name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active labs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all active time slots
 */
function getActiveTimeSlots() {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM time_slots 
                WHERE is_active = 1 
                ORDER BY start_time ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active time slots: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all active organizations
 */
function getActiveOrganizations() {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM organizations 
                WHERE is_active = 1 
                AND deleted_at IS NULL 
                ORDER BY name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active organizations: " . $e->getMessage());
        return [];
    }
}

/**
 * Get classes by organization (optional filter)
 */
function getClassesByOrganization($organizationId = null) {
    try {
        $pdo = getDB();
        
        if ($organizationId) {
            $sql = "SELECT c.*, o.name as organization_name 
                    FROM classes c
                    LEFT JOIN organizations o ON c.organization_id = o.id
                    WHERE c.organization_id = :org_id 
                    AND c.is_active = 1 
                    AND c.deleted_at IS NULL 
                    ORDER BY c.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':org_id' => $organizationId]);
        } else {
            $sql = "SELECT c.*, o.name as organization_name 
                    FROM classes c
                    LEFT JOIN organizations o ON c.organization_id = o.id
                    WHERE c.is_active = 1 
                    AND c.deleted_at IS NULL 
                    ORDER BY o.name ASC, c.name ASC";
            $stmt = $pdo->query($sql);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get class by ID
 */
function getClassById($classId) {
    try {
        $pdo = getDB();
        $sql = "SELECT c.*, o.name as organization_name 
                FROM classes c
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE c.id = :id 
                AND c.deleted_at IS NULL 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $classId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting class: " . $e->getMessage());
        return null;
    }
}

// ====== VALIDATION FUNCTIONS ======

/**
 * Validate date range
 */
function validateDateRange($startDate, $endDate) {
    if (empty($startDate) || empty($endDate)) {
        return true; // Allow empty dates
    }
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    return $end >= $start;
}

/**
 * Validate time range
 */
function validateTimeRange($startTime, $endTime) {
    if (empty($startTime) || empty($endTime)) {
        return false;
    }
    
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    
    return $end > $start;
}

/**
 * Check if date is weekend
 */
function isWeekend($date) {
    $dayOfWeek = date('N', strtotime($date));
    return ($dayOfWeek == 6 || $dayOfWeek == 7); // 6 = Saturday, 7 = Sunday
}

/**
 * Check if date is in the past
 */
function isPastDate($date) {
    $today = date('Y-m-d');
    return $date < $today;
}

// ====== SCHEDULE FUNCTIONS ======

/**
 * Get schedule by ID
 */
function getScheduleById($scheduleId) {
    try {
        $pdo = getDB();
        $sql = "SELECT s.*, 
                       r.name as resource_name,
                       ts.name as time_slot_name,
                       ts.start_time, ts.end_time,
                       c.name as class_name,
                       o.name as organization_name
                FROM schedules s
                LEFT JOIN resources r ON s.resource_id = r.id
                LEFT JOIN time_slots ts ON s.time_slot_id = ts.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE s.id = :id 
                AND s.deleted_at IS NULL 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting schedule: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active schedules
 */
function getActiveSchedules() {
    try {
        $pdo = getDB();
        $sql = "SELECT s.*, 
                       r.name as resource_name,
                       ts.name as time_slot_name,
                       ts.start_time, ts.end_time,
                       c.name as class_name,
                       o.name as organization_name
                FROM schedules s
                LEFT JOIN resources r ON s.resource_id = r.id
                LEFT JOIN time_slots ts ON s.time_slot_id = ts.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE s.status = 'active' 
                AND s.deleted_at IS NULL 
                ORDER BY s.day_of_week, ts.start_time ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active schedules: " . $e->getMessage());
        return [];
    }
}

/**
 * Get schedules by day
 */
function getSchedulesByDay($dayOfWeek) {
    try {
        $pdo = getDB();
        $sql = "SELECT s.*, 
                       r.name as resource_name,
                       ts.name as time_slot_name,
                       ts.start_time, ts.end_time,
                       c.name as class_name,
                       o.name as organization_name
                FROM schedules s
                LEFT JOIN resources r ON s.resource_id = r.id
                LEFT JOIN time_slots ts ON s.time_slot_id = ts.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE s.day_of_week = :day 
                AND s.status = 'active' 
                AND s.deleted_at IS NULL 
                ORDER BY ts.start_time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':day' => $dayOfWeek]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting schedules by day: " . $e->getMessage());
        return [];
    }
}

// ====== BOOKING FUNCTIONS ======

/**
 * Get booking by ID
 */
function getBookingById($bookingId) {
    try {
        $pdo = getDB();
        $sql = "SELECT b.*, 
                       r.name as resource_name,
                       ts.name as time_slot_name,
                       ts.start_time, ts.end_time,
                       c.name as class_name,
                       o.name as organization_name
                FROM bookings b
                LEFT JOIN resources r ON b.resource_id = r.id
                LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
                LEFT JOIN classes c ON b.class_id = c.id
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE b.id = :id 
                AND b.deleted_at IS NULL 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting booking: " . $e->getMessage());
        return null;
    }
}

/**
 * Get bookings by date range
 */
function getBookingsByDateRange($startDate, $endDate, $status = null) {
    try {
        $pdo = getDB();
        
        $sql = "SELECT b.*, 
                       r.name as resource_name,
                       ts.name as time_slot_name,
                       ts.start_time, ts.end_time,
                       c.name as class_name,
                       o.name as organization_name
                FROM bookings b
                LEFT JOIN resources r ON b.resource_id = r.id
                LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
                LEFT JOIN classes c ON b.class_id = c.id
                LEFT JOIN organizations o ON c.organization_id = o.id
                WHERE b.booking_date BETWEEN :start_date AND :end_date 
                AND b.deleted_at IS NULL";
        
        if ($status) {
            $sql .= " AND b.status = :status";
        }
        
        $sql .= " ORDER BY b.booking_date, ts.start_time ASC";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];
        
        if ($status) {
            $params[':status'] = $status;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting bookings by date range: " . $e->getMessage());
        return [];
    }
}

// ====== STATISTICS FUNCTIONS ======

/**
 * Get total bookings count
 */
function getTotalBookings($status = null) {
    try {
        $pdo = getDB();
        
        $sql = "SELECT COUNT(*) as total FROM bookings WHERE deleted_at IS NULL";
        
        if ($status) {
            $sql .= " AND status = :status";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $status]);
        } else {
            $stmt = $pdo->query($sql);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting total bookings: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total schedules count
 */
function getTotalSchedules() {
    try {
        $pdo = getDB();
        $sql = "SELECT COUNT(*) as total FROM schedules 
                WHERE status = 'active' AND deleted_at IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting total schedules: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total resources count
 */
function getTotalResources() {
    try {
        $pdo = getDB();
        $sql = "SELECT COUNT(*) as total FROM resources 
                WHERE status = 'active' AND deleted_at IS NULL";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting total resources: " . $e->getMessage());
        return 0;
    }
}

// ====== UTILITY FUNCTIONS ======

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span style="background:#ffc107;color:#000;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">⏳ Pending</span>',
        'approved' => '<span style="background:#28a745;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">✅ Approved</span>',
        'rejected' => '<span style="background:#dc3545;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">❌ Rejected</span>',
        'cancelled' => '<span style="background:#6c757d;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">🚫 Cancelled</span>',
        'active' => '<span style="background:#28a745;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">✅ Active</span>',
        'inactive' => '<span style="background:#6c757d;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">⏸️ Inactive</span>'
    ];
    
    return $badges[$status] ?? '<span style="background:#6c757d;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">' . ucfirst($status) . '</span>';
}

/**
 * Truncate text
 */
function truncateText($text, $length = 50, $suffix = '...') {
    if (empty($text)) return '-';
    
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Convert English day to Indonesian
 */
function convertDayToIndo($englishDay) {
    $days = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    
    return $days[$englishDay] ?? $englishDay;
}

/**
 * Convert Indonesian day to English
 */
function convertDayToEnglish($indoDay) {
    $days = [
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday',
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
        'Minggu' => 'Sunday'
    ];
    
    return $days[$indoDay] ?? $indoDay;
}

/**
 * Get day order number
 */
function getDayOrder($englishDay) {
    $order = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7
    ];
    
    return $order[$englishDay] ?? 0;
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirect to login if not admin
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: login.php');
        exit;
    }
}