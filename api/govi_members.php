<?php
// ============================================================
//  api/govi_members.php  —  Govisamvidana Members CRUD API
//  GET  ?action=list             — all farmer members
//  GET  ?action=get&id=1         — single member
//  POST ?action=add              — add member (president-govi)
//  POST ?action=update&id=1      — update member (president-govi)
//  POST ?action=remove&id=1      — remove member (president-govi)
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

function requireGoviPresident(): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'president-govi')
        sendError('Govisamvidana President access required.', 403);
}

$pdo = getDB();

switch ($action) {

    // ── LIST ────────────────────────────────────────────────────
    case 'list':
        $search = trim($_GET['search'] ?? '');
        $canal  = trim($_GET['canal']  ?? '');

        $sql = 'SELECT * FROM govi_members WHERE 1=1';
        $params = [];

        if ($search) {
            $sql .= ' AND (LOWER(name) LIKE LOWER(?) OR LOWER(nic) LIKE LOWER(?))';
            $like = "%$search%";
            $params[] = $like; $params[] = $like;
        }
        if ($canal) {
            $sql .= ' AND canal = ?';
            $params[] = $canal;
        }
        $sql .= ' ORDER BY member_no ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();

        sendJSON(['success' => true, 'members' => $members, 'total' => count($members)]);
        break;

    // ── GET SINGLE ──────────────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        $stmt = $pdo->prepare('SELECT * FROM govi_members WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $member = $stmt->fetch();

        if (!$member) sendError('Member not found.', 404);
        sendJSON(['success' => true, 'member' => $member]);
        break;

    // ── ADD (Govi President only) ────────────────────────────────
    case 'add':
        requireGoviPresident();

        $name       = trim($body['name']           ?? '');
        $nic        = trim($body['nic']             ?? '');
        $address    = trim($body['address']         ?? '');
        $phone      = trim($body['phone']           ?? '');
        $owner      = trim($body['original_owner']  ?? '');
        $cultivator = trim($body['cultivator']      ?? '');
        $hectares   = (float)($body['hectares']     ?? 0);
        $canal      = trim($body['canal']           ?? '');

        if (!$name)       sendError('Name is required.');
        if (!$nic)        sendError('NIC is required.');
        if (!$address)    sendError('Address is required.');
        if (!$phone)      sendError('Phone is required.');
        if (!$owner)      sendError('Original landowner is required.');
        if (!$cultivator) sendError('Cultivator is required.');
        if (!$hectares)   sendError('Hectares is required.');
        if (!$canal)      sendError('Canal is required.');

        // Check NIC uniqueness in govi_members
        $s = $pdo->prepare('SELECT id FROM govi_members WHERE LOWER(nic) = LOWER(?) LIMIT 1');
        $s->execute([$nic]);
        if ($s->fetch()) sendError("NIC $nic is already registered in Govisamvidana.");

        // Generate member_no
        $s = $pdo->query('SELECT COUNT(*) AS cnt FROM govi_members');
        $cnt = (int)$s->fetch()['cnt'] + 1;
        $memberNo = str_pad($cnt, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO govi_members
            (member_no, name, nic, address, phone, original_owner, cultivator, hectares, canal)
            VALUES (?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([$memberNo, $name, $nic, $address, $phone,
                        $owner, $cultivator, $hectares, $canal]);
        $newId = (int)$pdo->lastInsertId();

        // Create login if password provided
        if (!empty($body['password'])) {
            $hash = password_hash($body['password'], PASSWORD_DEFAULT);
            $insU = $pdo->prepare('
                INSERT INTO users (nic, password_hash, role, govi_member_id)
                VALUES (?, ?, "member", ?)
                ON DUPLICATE KEY UPDATE govi_member_id = VALUES(govi_member_id)
            ');
            $insU->execute([$nic, $hash, $newId]);
        }

        sendJSON(['success' => true, 'message' => 'Farmer member added.', 'id' => $newId]);
        break;

    // ── UPDATE (Govi President only) ─────────────────────────────
    case 'update':
        requireGoviPresident();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        $allowed = ['name','nic','address','phone','original_owner',
                    'cultivator','hectares','canal'];
        $sets = []; $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]   = "$field = ?";
                $params[] = $body[$field];
            }
        }
        if (empty($sets)) sendError('No fields to update.');

        $params[] = $id;
        $pdo->prepare('UPDATE govi_members SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute($params);

        sendJSON(['success' => true, 'message' => 'Member updated.']);
        break;

    // ── REMOVE (Govi President only) ─────────────────────────────
    case 'remove':
        requireGoviPresident();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) sendError('Member ID required.');

        $pdo->prepare('DELETE FROM govi_members WHERE id = ?')->execute([$id]);
        sendJSON(['success' => true, 'message' => 'Member removed.']);
        break;

    default:
        sendError("Unknown action: $action", 404);
}
?>
