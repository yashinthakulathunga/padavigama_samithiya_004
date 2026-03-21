<?php
// ============================================================
//  api/schedules.php  —  Water Schedule, Cleaning Schedule,
//                        Funeral Duties, Bearers, Committee
//
//  Water Schedule:
//  GET  ?action=water_list&month=Apr+2025&canal=FC+07
//  POST ?action=water_add      (president-govi)
//  POST ?action=water_update&id=1
//  POST ?action=water_remove&id=1
//
//  Cleaning Schedule:
//  GET  ?action=cleaning_list
//  POST ?action=cleaning_add   (president-govi)
//  POST ?action=cleaning_update&id=1
//  POST ?action=cleaning_remove&id=1
//
//  Funeral Duties:
//  GET  ?action=funeral_list&event_id=1&day=1
//  POST ?action=funeral_add    (president-mara)
//  POST ?action=funeral_remove&id=1
//
//  Bearers:
//  GET  ?action=bearers_list
//  POST ?action=bearers_save   (president-mara)
//
//  Govi Committee:
//  GET  ?action=committee_list
//  POST ?action=committee_save (president-govi)
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

session_start();
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

function sendJSON($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError(string $msg, int $code = 400): void {
    sendJSON(['success' => false, 'message' => $msg], $code);
}

function requireRole(string $role): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role)
        sendError("$role access required.", 403);
}

$pdo = getDB();

switch ($action) {

    // ══════════════════════════════════════════════════════════════
    //  WATER SCHEDULE
    // ══════════════════════════════════════════════════════════════

    case 'water_list':
        $month = trim($_GET['month'] ?? '');
        $canal = trim($_GET['canal'] ?? '');

        $sql = 'SELECT * FROM water_schedule WHERE 1=1';
        $params = [];
        if ($month) { $sql .= ' AND month_label = ?'; $params[] = $month; }
        if ($canal) { $sql .= ' AND canal = ?'; $params[] = $canal; }
        $sql .= ' ORDER BY schedule_date ASC, canal ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendJSON(['success' => true, 'schedule' => $stmt->fetchAll()]);
        break;

    case 'water_add':
        requireRole('president-govi');

        $required = ['member_name','nic','canal','hectares','schedule_date',
                     'time_window','duration','month_label'];
        foreach ($required as $f) {
            if (empty($body[$f])) sendError("$f is required.");
        }

        $stmt = $pdo->prepare('
            INSERT INTO water_schedule
            (member_name, nic, canal, hectares, schedule_date, time_window, duration, month_label)
            VALUES (?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $body['member_name'], $body['nic'], $body['canal'],
            (float)$body['hectares'], $body['schedule_date'],
            $body['time_window'], $body['duration'], $body['month_label']
        ]);
        sendJSON(['success' => true, 'message' => 'Water schedule entry added.', 'id' => $pdo->lastInsertId()]);
        break;

    case 'water_update':
        requireRole('president-govi');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('ID required.');

        $allowed = ['member_name','nic','canal','hectares','schedule_date',
                    'time_window','duration','month_label'];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (empty($sets)) sendError('No fields to update.');
        $params[] = $id;
        $pdo->prepare('UPDATE water_schedule SET '.implode(',',$sets).' WHERE id = ?')->execute($params);
        sendJSON(['success' => true, 'message' => 'Water schedule updated.']);
        break;

    case 'water_remove':
        requireRole('president-govi');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('ID required.');
        $pdo->prepare('DELETE FROM water_schedule WHERE id = ?')->execute([$id]);
        sendJSON(['success' => true, 'message' => 'Entry removed.']);
        break;

    // ══════════════════════════════════════════════════════════════
    //  CLEANING SCHEDULE
    // ══════════════════════════════════════════════════════════════

    case 'cleaning_list':
        $status = trim($_GET['status'] ?? '');
        $sql = 'SELECT * FROM cleaning_schedule WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY event_date DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendJSON(['success' => true, 'events' => $stmt->fetchAll()]);
        break;

    case 'cleaning_add':
        requireRole('president-govi');

        $date    = trim($body['event_date']    ?? '');
        $section = trim($body['canal_section'] ?? '');
        $start   = trim($body['start_time']    ?? '');
        $end     = trim($body['end_time']      ?? '');
        $members = (int)($body['members_req']  ?? 0);
        $status  = trim($body['status']        ?? 'Upcoming');
        $notes   = trim($body['notes']         ?? '');

        if (!$date)    sendError('Date required.');
        if (!$section) sendError('Canal section required.');
        if (!$start)   sendError('Start time required.');
        if (!$end)     sendError('End time required.');
        if ($start >= $end) sendError('End time must be after start time.');
        if ($members < 1)   sendError('Members required must be >= 1.');

        $pdo->prepare('
            INSERT INTO cleaning_schedule (event_date, canal_section, start_time, end_time, members_req, status, notes)
            VALUES (?,?,?,?,?,?,?)
        ')->execute([$date, $section, $start, $end, $members, $status, $notes]);

        sendJSON(['success' => true, 'message' => 'Cleaning event added.', 'id' => $pdo->lastInsertId()]);
        break;

    case 'cleaning_update':
        requireRole('president-govi');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('ID required.');

        $allowed = ['event_date','canal_section','start_time','end_time',
                    'members_req','status','notes'];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (empty($sets)) sendError('No fields to update.');
        $params[] = $id;
        $pdo->prepare('UPDATE cleaning_schedule SET '.implode(',',$sets).' WHERE id = ?')->execute($params);
        sendJSON(['success' => true, 'message' => 'Cleaning event updated.']);
        break;

    case 'cleaning_remove':
        requireRole('president-govi');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('ID required.');
        $pdo->prepare('DELETE FROM cleaning_schedule WHERE id = ?')->execute([$id]);
        sendJSON(['success' => true, 'message' => 'Event removed.']);
        break;

    // ══════════════════════════════════════════════════════════════
    //  FUNERAL DUTIES
    // ══════════════════════════════════════════════════════════════

    case 'funeral_list':
        $eventId = (int)($_GET['event_id'] ?? 1);
        $day     = (int)($_GET['day']      ?? 0);
        $shift   = trim($_GET['shift']     ?? '');
        $duty    = trim($_GET['duty']      ?? '');

        $sql = 'SELECT * FROM mara_funeral_duties WHERE event_id = ?';
        $params = [$eventId];
        if ($day)   { $sql .= ' AND day_no = ?'; $params[] = $day; }
        if ($shift) { $sql .= ' AND shift = ?';  $params[] = $shift; }
        if ($duty)  { $sql .= ' AND duty_type = ?'; $params[] = $duty; }
        $sql .= ' ORDER BY day_no ASC, id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendJSON(['success' => true, 'duties' => $stmt->fetchAll()]);
        break;

    case 'funeral_add':
        requireRole('president-mara');

        $required = ['event_id','day_no','time_slot','duty_type',
                     'member_name','nic','shift'];
        foreach ($required as $f) {
            if (empty($body[$f])) sendError("$f is required.");
        }

        $validShifts = ['Morning','Afternoon','Evening'];
        if (!in_array($body['shift'], $validShifts))
            sendError('shift must be Morning, Afternoon, or Evening.');

        $pdo->prepare('
            INSERT INTO mara_funeral_duties
            (event_id, day_no, time_slot, duty_type, member_name, nic, shift)
            VALUES (?,?,?,?,?,?,?)
        ')->execute([
            (int)$body['event_id'], (int)$body['day_no'],
            $body['time_slot'], $body['duty_type'],
            $body['member_name'], $body['nic'], $body['shift']
        ]);
        sendJSON(['success' => true, 'message' => 'Duty assignment added.', 'id' => $pdo->lastInsertId()]);
        break;

    case 'funeral_remove':
        requireRole('president-mara');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('ID required.');
        $pdo->prepare('DELETE FROM mara_funeral_duties WHERE id = ?')->execute([$id]);
        sendJSON(['success' => true, 'message' => 'Duty removed.']);
        break;

    // ══════════════════════════════════════════════════════════════
    //  OFFICE BEARERS (Maranadara)
    // ══════════════════════════════════════════════════════════════

    case 'bearers_list':
        $stmt = $pdo->query('SELECT * FROM mara_bearers ORDER BY sort_order ASC');
        sendJSON(['success' => true, 'bearers' => $stmt->fetchAll()]);
        break;

    case 'bearers_save':
        requireRole('president-mara');

        // Expects: array of bearer objects with optional id
        $bearers = $body['bearers'] ?? [];
        if (!is_array($bearers) || empty($bearers))
            sendError('bearers array required.');

        // Truncate and re-insert (simple approach)
        $pdo->exec('DELETE FROM mara_bearers');

        $ins = $pdo->prepare('
            INSERT INTO mara_bearers (position, name, nic, address, ethnicity, religion, sort_order)
            VALUES (?,?,?,?,?,?,?)
        ');
        foreach ($bearers as $i => $b) {
            $ins->execute([
                $b['position'] ?? '',
                $b['name']     ?? '',
                $b['nic']      ?? '—',
                $b['address']  ?? '',
                $b['ethnicity']?? 'Sinhala',
                $b['religion'] ?? 'Buddhist',
                $i + 1
            ]);
        }
        sendJSON(['success' => true, 'message' => 'Bearers saved.']);
        break;

    // ══════════════════════════════════════════════════════════════
    //  GOVI COMMITTEE
    // ══════════════════════════════════════════════════════════════

    case 'committee_list':
        $stmt = $pdo->query('SELECT * FROM govi_committee ORDER BY sort_order ASC');
        sendJSON(['success' => true, 'committee' => $stmt->fetchAll()]);
        break;

    case 'committee_save':
        requireRole('president-govi');

        $members = $body['committee'] ?? [];
        if (!is_array($members) || empty($members))
            sendError('committee array required.');

        $pdo->exec('DELETE FROM govi_committee');

        $ins = $pdo->prepare('
            INSERT INTO govi_committee (position, name, nic, phone, sort_order)
            VALUES (?,?,?,?,?)
        ');
        foreach ($members as $i => $m) {
            $ins->execute([
                $m['position'] ?? '',
                $m['name']     ?? '',
                $m['nic']      ?? '',
                $m['phone']    ?? '',
                $i + 1
            ]);
        }
        sendJSON(['success' => true, 'message' => 'Committee saved.']);
        break;

    default:
        sendError("Unknown action: $action", 404);
}
?>
