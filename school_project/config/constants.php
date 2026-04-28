<?php
define('SCHOOL_NAME', 'โรงเรียนละลมวิทยา');
define('SCHOOL_DISTRICT', 'อำเภอภูสิงห์');
define('SCHOOL_PROVINCE', 'จังหวัดศรีสะเกษ');
define('SCHOOL_FULL', SCHOOL_NAME . ' ' . SCHOOL_DISTRICT . ' ' . SCHOOL_PROVINCE);
define('FISCAL_YEAR', 2569);
define('BASE_URL', '/school_project');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('EXPORT_PATH', __DIR__ . '/../uploads/exports/');
define('SESSION_TIMEOUT', 7200);

function getSetting($key) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $db = getDB();
    $s = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $s->execute([$key]);
    $r = $s->fetch();
    $cache[$key] = $r ? $r['setting_value'] : null;
    return $cache[$key];
}

function auditLog($action, $targetType = null, $targetId = null, $oldVal = null, $newVal = null) {
    try {
        $db = getDB();
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $s = $db->prepare("INSERT INTO audit_logs (user_id,action,target_type,target_id,old_value,new_value,ip_address) VALUES (?,?,?,?,?,?,?)");
        $s->execute([$userId, $action, $targetType, $targetId,
            $oldVal ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : null,
            $newVal ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : null, $ip]);
    } catch (Exception $e) {}
}

function addNotification($userId, $type, $title, $message = '', $relatedId = null, $relatedType = null) {
    try {
        $db = getDB();
        $s = $db->prepare("INSERT INTO notifications (user_id,type,title,message,related_id,related_type) VALUES (?,?,?,?,?,?)");
        $s->execute([$userId, $type, $title, $message, $relatedId, $relatedType]);
    } catch (Exception $e) {}
}

function getUnreadNotifications($userId) {
    $db = getDB();
    $s = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 20");
    $s->execute([$userId]);
    return $s->fetchAll();
}

function numberToThai($num) {
    $num = (float)$num;
    $ones = ['','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $tens = ['','สิบ','ยี่สิบ','สามสิบ','สี่สิบ','ห้าสิบ','หกสิบ','เจ็ดสิบ','แปดสิบ','เก้าสิบ'];
    $levels = ['','ร้อย','พัน','หมื่น','แสน','ล้าน'];
    if ($num == 0) return 'ศูนย์บาทถ้วน';
    $baht = (int)$num;
    $satang = round(($num - $baht) * 100);
    $convertPart = function($n) use ($ones, $tens, $levels, &$convertPart) {
        if ($n == 0) return '';
        if ($n < 10) return $ones[$n];
        if ($n < 100) return $tens[(int)($n/10)] . ($n%10 ? $ones[$n%10] : '');
        $level = (int)log10($n);
        $d = (int)($n / pow(10,$level));
        return $levels[$level] ? ($d > 1 || $level > 1 ? $ones[$d] : '') . $levels[$level] . $convertPart($n % (int)pow(10,$level)) : '';
    };
    $result = '';
    if ($baht >= 1000000) {
        $result .= $convertPart((int)($baht/1000000)) . 'ล้าน';
        $baht %= 1000000;
    }
    $result .= $convertPart($baht) . 'บาท';
    $result .= $satang > 0 ? $convertPart($satang) . 'สตางค์' : 'ถ้วน';
    return $result;
}
