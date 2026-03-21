<?php
// ============================================================
//  api/mara_members.php  —  Maranadara Members CRUD API
//  GET    ?action=list          — සියලු members list
//  GET    ?action=get&id=1      — single member + payments
//  POST   ?action=add           — new member add (president only)
//  POST   ?action=update&id=1   — member update (president only)
//  POST   ?action=remove&id=1   — member remove (president only)
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

function requirePresident(): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'president-mara')
        sendError('President access required.', 403);
}

// ── MONTH constants ───────────────────────────────────────────
const PAY_MONTHS = [
    'Apr25' => 'Apr 2025',
    'Mar25' => 'Mar 2025',
    'Feb25' => 'Feb 2025',
    'Jan25' => 'Jan 2025',
    'Dec24' => 'Dec 2024',
    'Nov24' => 'Nov 2024',
    'Oct24' => 'Oct 2024',
    'Sep24' => 'Sep 2024',
    'Aug24' => 'Aug 2024',
    'Jul24' => 'Jul 2024',
    'Jun24' => 'Jun 2024',
    'May24' => 'May 2024',
];

// ── Helper: attach payments array to member ───────────────────
function attachPayments(PDO $pdo, array $member): array {
    $stmt = $pdo->prepare('SELECT month_key, status FROM mara_payments WHERE member_id = ?');
    $stmt->execute([$member['id']]);
    $rows = $stmt->fetchAll();
    $payments = [];
    foreach ($rows as $r) {
        $payments[$r['month_key']] = $r['status'];
    }
    // Fill missing months as unpaid
    foreach (array_keys(PAY_MONTHS) as $k) {
        if (!isset($payments[$k])) $payments[$k] = 'unpaid';
    }
    $member['payments'] = $payments;

    // Calculate consecutive missed from latest
    $consecutive = 0;
    foreach (array_keys(PAY_MONTHS) as $k) {
        if (($payments[$k] ?? 'unpaid') === 'unpaid') $consecutive++;
        else break;
    }
    $member['consecutive_missed'] = $consecutive;
    return $member;
}

// ── Route ─────────────────────────────────────────────────────
$pdo = getDB();

switch ($action) {

    // ── LIST ALL MEMBERS ────────────────────────────────────────
    case 'list':
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $sql = 'SELECT * FROM mara_members WHERE 1=1';
        $params = [];

        if ($search) {
            $sql .= ' AND (LOWER(name) LIKE LOWER(?) OR LOWER(nic) LIKE LOWER(?))';
            $like = "%$search%";
            $params[] = $like; $params[] = $like;
        }
        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY member_no ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();

        // Attach payments to each
        foreach ($members as &$m) {
            $m = attachPayments($pdo, $m);
        }

        sendJSON(['success' => true, 'members' => $members, 'total' => count($members)]);
        break;

    // ── GET SINGLE MEMBER ───────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        $stmt = $pdo->prepare('SELECT * FROM mara_members WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $member = $stmt->fetch();

        if (!$member) sendError('Member not found.', 404);

        $member = attachPayments($pdo, $member);
        sendJSON(['success' => true, 'member' => $member]);
        break;

    // ── ADD MEMBER (President only) ─────────────────────────────
    case 'add':
        requirePresident();

        $name       = trim($body['name']      ?? '');
        $nic        = trim($body['nic']        ?? '');
        $address    = trim($body['address']    ?? '');
        $phone      = trim($body['phone']      ?? '');
        $father     = trim($body['father_name']?? '');
        $religion   = trim($body['religion']   ?? 'Buddhist');
        $ethnicity  = trim($body['ethnicity']  ?? 'Sinhala');
        $years      = (int)($body['years_in_village'] ?? 0);
        $status     = in_array($body['status'] ?? 'active', ['active','suspended']) ? $body['status'] : 'active';
        $joined     = $body['joined_date']     ?? date('Y-m-d');

        if (!$name)    sendError('Name is required.');
        if (!$nic)     sendError('NIC is required.');
        if (!$address) sendError('Address is required.');

        // Check uniqueness
        $s = $pdo->prepare('SELECT id FROM mara_members WHERE LOWER(nic) = LOWER(?) LIMIT 1');
        $s->execute([$nic]);
        if ($s->fetch()) sendError("NIC $nic is already registered.");

        // Generate member_no
        $s = $pdo->query('SELECT COUNT(*) AS cnt FROM mara_members');
        $cnt = (int)$s->fetch()['cnt'] + 1;
        $memberNo = str_pad($cnt, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO mara_members
            (member_no, name, nic, father_name, address, phone, religion, ethnicity,
             years_in_village, status, joined_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([$memberNo, $name, $nic, $father, $address, $phone,
                        $religion, $ethnicity, $years, $status, $joined]);
        $newId = (int)$pdo->lastInsertId();

        // Initialize all payments as unpaid
        $insP = $pdo->prepare('INSERT INTO mara_payments (member_id, month_key, month_label, status) VALUES (?,?,?,?)');
        foreach (PAY_MONTHS as $key => $label) {
            $insP->execute([$newId, $key, $label, 'unpaid']);
        }

        // Create user account if password provided
        if (!empty($body['password'])) {
            $hash = password_hash($body['password'], PASSWORD_DEFAULT);
            $insU = $pdo->prepare('
                INSERT INTO users (nic, password_hash, role, mara_member_id)
                VALUES (?, ?, "member", ?)
                ON DUPLICATE KEY UPDATE mara_member_id = VALUES(mara_member_id)
            ');
            $insU->execute([$nic, $hash, $newId]);
        }

        sendJSON(['success' => true, 'message' => 'Member added.', 'id' => $newId]);
        break;

    // ── UPDATE MEMBER (President only) ──────────────────────────
    case 'update':
        requirePresident();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        $allowed = ['name','nic','father_name','address','phone','religion',
                    'ethnicity','years_in_village','status','joined_date'];
        $sets = []; $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]   = "$field = ?";
                $params[] = $body[$field];
            }
        }
        if (empty($sets)) sendError('No fields to update.');

        $params[] = $id;
        $sql = 'UPDATE mara_members SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute($params);

        sendJSON(['success' => true, 'message' => 'Member updated.']);
        break;

    // ── REMOVE MEMBER (President only) ──────────────────────────
    case 'remove':
        requirePresident();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        // CASCADE will delete payments automatically (FK defined)
        $pdo->prepare('DELETE FROM mara_members WHERE id = ?')->execute([$id]);

        sendJSON(['success' => true, 'message' => 'Member removed.']);
        break;

    default:
        sendError("Unknown action: $action", 404);
}
?>
