<?php
// ============================================================
//  api/auth.php  —  Login & Registration API
//  PLACE THIS FILE AT:  padawigama/api/auth.php
//
//  Methods:
//    POST /api/auth.php?action=login
//    POST /api/auth.php?action=register
//    POST /api/auth.php?action=logout
//    GET  /api/auth.php?action=me
//    POST /api/auth.php?action=change_password
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

session_start();

// ── FIX: correct path to db.php ──────────────────────────────
// auth.php is in /api/, db.php is in /config/
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helper functions ──────────────────────────────────────────

function sendJSON($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError(string $msg, int $code = 400): void {
    sendJSON(['success' => false, 'message' => $msg], $code);
}

// ── Route ─────────────────────────────────────────────────────

switch ($action) {

    // ── LOGIN ─────────────────────────────────────────────────
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            sendError('POST method required.', 405);

        $nic      = trim($body['nic'] ?? '');
        $password = $body['password'] ?? '';

        if (!$nic || !$password)
            sendError('NIC number සහ password දෙකම අනිවාර්ය වේ.');

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(nic) = LOWER(?) LIMIT 1');
        $stmt->execute([$nic]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash']))
            sendError('NIC number හෝ password වැරදිය. ඔබ registered member කෙනෙක් නොවේ නම් Register here click කරන්න.');

        // Fetch member data if linked
        $memberData = null;
        $goviData   = null;

        if (!empty($user['mara_member_id'])) {
            $s = $pdo->prepare('SELECT * FROM mara_members WHERE id = ? LIMIT 1');
            $s->execute([$user['mara_member_id']]);
            $memberData = $s->fetch();
        }

        if (!empty($user['govi_member_id'])) {
            $s = $pdo->prepare('SELECT * FROM govi_members WHERE id = ? LIMIT 1');
            $s->execute([$user['govi_member_id']]);
            $goviData = $s->fetch();
        }

        // Store in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nic']     = $user['nic'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $memberData['name'] ?? ($goviData['name'] ?? 'User');

        sendJSON([
            'success' => true,
            'user' => [
                'id'             => $user['id'],
                'nic'            => $user['nic'],
                'role'           => $user['role'],
                'name'           => $_SESSION['name'],
                'mara_member_id' => $user['mara_member_id'],
                'govi_member_id' => $user['govi_member_id'],
                'memberData'     => $memberData,
                'goviData'       => $goviData,
            ]
        ]);
        break;

    // ── REGISTER ──────────────────────────────────────────────
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            sendError('POST method required.', 405);

        $name      = trim($body['name']            ?? '');
        $nic       = trim($body['nic']              ?? '');
        $password  = $body['password']              ?? '';
        $address   = trim($body['address']          ?? '');
        $phone     = trim($body['phone']            ?? '');
        $father    = trim($body['father_name']      ?? '');
        $religion  = trim($body['religion']         ?? 'Buddhist');
        $ethnicity = trim($body['ethnicity']        ?? 'Sinhala');
        $gender    = trim($body['gender']           ?? 'Male');
        $dob       = $body['date_of_birth']         ?? null;
        $years     = (int)($body['years_in_village'] ?? 0);
        $joinMara  = (bool)($body['join_mara']      ?? true);
        $joinGovi  = (bool)($body['join_govi']      ?? false);

        // Govi-only fields
        $owner      = trim($body['original_owner'] ?? '');
        $cultivator = trim($body['cultivator']     ?? '');
        $hectares   = (float)($body['hectares']    ?? 0);
        $canal      = trim($body['canal']          ?? '');

        // ── Validation ─────────────────────────────────────────
        if (!$name)     sendError('Full name is required.');
        if (!$nic)      sendError('NIC number is required.');
        if (!$password) sendError('Password is required.');
        if (!$address)  sendError('Address is required.');
        if (strlen($password) < 6)
            sendError('Password must be at least 6 characters.');
        if (!$joinMara && !$joinGovi)
            sendError('Please select at least one society.');
        if ($joinGovi && (!$owner || !$cultivator || !$hectares || !$canal))
            sendError('Paddy field details are required for Govisamvidana.');

        $pdo = getDB();

        // ── Check NIC not already in users table ───────────────
        $s = $pdo->prepare('SELECT id FROM users WHERE LOWER(nic) = LOWER(?) LIMIT 1');
        $s->execute([$nic]);
        if ($s->fetch()) sendError("NIC $nic is already registered. Please login.");

        // ── Also check member_registrations (pending) ──────────
        $s = $pdo->prepare('SELECT id FROM member_registrations WHERE LOWER(nic) = LOWER(?) LIMIT 1');
        $s->execute([$nic]);
        if ($s->fetch()) sendError("NIC $nic has already submitted a registration (pending approval).");

        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $dobVal = ($dob !== '' && $dob !== null) ? $dob : null;

        // ── Step 1: Insert into member_registrations (for president review) ──
        $stmt = $pdo->prepare('
            INSERT INTO member_registrations
            (join_mara, join_govi, name, nic, father_name, phone, ethnicity, religion,
             address, years_in_village, date_of_birth, gender,
             original_owner, cultivator, hectares, canal, password_hash)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $joinMara ? 1 : 0,
            $joinGovi ? 1 : 0,
            $name, $nic, $father, $phone, $ethnicity, $religion,
            $address, $years, $dobVal, $gender,
            $owner ?: null, $cultivator ?: null,
            $hectares ?: null, $canal ?: null,
            $hash
        ]);

        // ── Step 2: ALSO insert into users table so they can login immediately ──
        // They get role="member" (pending) — president can upgrade later
        $stmt2 = $pdo->prepare('
            INSERT INTO users (nic, password_hash, role, mara_member_id, govi_member_id)
            VALUES (?, ?, "member", NULL, NULL)
        ');
        $stmt2->execute([$nic, $hash]);

        sendJSON([
            'success' => true,
            'message' => 'Registration submitted! You can now login. President approval will link your full member profile.'
        ]);
        break;

    // ── LOGOUT ────────────────────────────────────────────────
    case 'logout':
        session_destroy();
        sendJSON(['success' => true, 'message' => 'Logged out.']);
        break;

    // ── ME (current user info) ────────────────────────────────
    case 'me':
        if (!isset($_SESSION['user_id']))
            sendError('Not logged in.', 401);

        sendJSON([
            'success' => true,
            'user' => [
                'id'   => $_SESSION['user_id'],
                'nic'  => $_SESSION['nic'],
                'role' => $_SESSION['role'],
                'name' => $_SESSION['name'],
            ]
        ]);
        break;

    // ── CHANGE PASSWORD ───────────────────────────────────────
    case 'change_password':
        if (!isset($_SESSION['user_id']))
            sendError('Not logged in.', 401);

        $currentPw = $body['current_password'] ?? '';
        $newPw     = $body['new_password']     ?? '';

        if (!$currentPw || !$newPw)
            sendError('current_password සහ new_password දෙකම required.');
        if (strlen($newPw) < 6)
            sendError('New password අවම වශයෙන් 6 characters විය යුතුය.');

        // ── FIX: $pdo was missing here in original code ────────
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPw, $row['password_hash']))
            sendError('Current password වැරදිය.');

        $newHash = password_hash($newPw, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$newHash, $_SESSION['user_id']]);

        sendJSON(['success' => true, 'message' => 'Password changed successfully.']);
        break;

    default:
        sendError("Unknown action: $action", 404);
}
?>