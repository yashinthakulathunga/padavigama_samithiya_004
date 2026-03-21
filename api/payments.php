<?php
// ============================================================
//  api/payments.php  —  Maranadara Payment Records API
//  GET  ?action=list&month=Apr25          — payments list
//  POST ?action=record                    — payment record/update
//  GET  ?action=summary&month=Apr25       — paid/unpaid counts
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

$pdo = getDB();

switch ($action) {

    // ── LIST PAYMENTS FOR A MONTH ────────────────────────────────
    case 'list':
        $month  = trim($_GET['month']  ?? 'Apr25');
        $status = trim($_GET['status'] ?? '');

        $sql = '
            SELECT m.id, m.member_no, m.name, m.nic, m.status AS member_status,
                   p.month_key, p.status AS pay_status, p.recorded_at
            FROM mara_members m
            LEFT JOIN mara_payments p ON p.member_id = m.id AND p.month_key = ?
            WHERE 1=1
        ';
        $params = [$month];

        if ($status) {
            $sql .= ' AND COALESCE(p.status,"unpaid") = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY m.member_no ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Normalise: if no payment row, mark as unpaid
        foreach ($rows as &$r) {
            $r['pay_status'] = $r['pay_status'] ?? 'unpaid';
        }

        sendJSON(['success' => true, 'payments' => $rows, 'total' => count($rows)]);
        break;

    // ── RECORD / UPDATE PAYMENT (President only) ─────────────────
    case 'record':
        requirePresident();

        $memberId = (int)($body['member_id'] ?? 0);
        $monthKey = trim($body['month_key']  ?? '');
        $status   = trim($body['status']     ?? '');

        if (!$memberId) sendError('member_id is required.');
        if (!$monthKey) sendError('month_key is required. (e.g. Apr25)');
        if (!in_array($status, ['paid','unpaid'])) sendError('status must be "paid" or "unpaid".');

        $monthLabels = [
            'Apr25'=>'Apr 2025','Mar25'=>'Mar 2025','Feb25'=>'Feb 2025',
            'Jan25'=>'Jan 2025','Dec24'=>'Dec 2024','Nov24'=>'Nov 2024',
            'Oct24'=>'Oct 2024','Sep24'=>'Sep 2024','Aug24'=>'Aug 2024',
            'Jul24'=>'Jul 2024','Jun24'=>'Jun 2024','May24'=>'May 2024',
        ];
        $monthLabel = $monthLabels[$monthKey] ?? $monthKey;

        // UPSERT — update if exists, insert if not
        $stmt = $pdo->prepare('
            INSERT INTO mara_payments (member_id, month_key, month_label, status, recorded_by)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status      = VALUES(status),
                recorded_by = VALUES(recorded_by),
                recorded_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $memberId, $monthKey, $monthLabel, $status,
            $_SESSION['user_id'] ?? null
        ]);

        // Fetch member name for response
        $s = $pdo->prepare('SELECT name FROM mara_members WHERE id = ? LIMIT 1');
        $s->execute([$memberId]);
        $memberName = $s->fetch()['name'] ?? 'Unknown';

        sendJSON([
            'success' => true,
            'message' => "$memberName — $monthLabel marked as $status."
        ]);
        break;

    // ── SUMMARY FOR A MONTH ─────────────────────────────────────
    case 'summary':
        $month = trim($_GET['month'] ?? 'Apr25');

        $total = (int)$pdo->query('SELECT COUNT(*) FROM mara_members')->fetchColumn();

        $stmt = $pdo->prepare('
            SELECT
                SUM(status = "paid")   AS paid_count,
                SUM(status = "unpaid") AS unpaid_count
            FROM mara_payments WHERE month_key = ?
        ');
        $stmt->execute([$month]);
        $row = $stmt->fetch();

        $paidCount   = (int)($row['paid_count']   ?? 0);
        $unpaidCount = $total - $paidCount;
        $rate        = $total > 0 ? round($paidCount / $total * 100) : 0;

        sendJSON([
            'success'       => true,
            'total'         => $total,
            'paid'          => $paidCount,
            'unpaid'        => $unpaidCount,
            'collection_rate' => $rate,
            'collected_rs'  => $paidCount * 1000,
            'outstanding_rs'=> $unpaidCount * 1000,
        ]);
        break;

    default:
        sendError("Unknown action: $action", 404);
}
?>
