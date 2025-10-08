<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'db.php';

// كلمة مرور المدير
$ADMIN_PASS = '123';

// قراءة مدخل JSON عند POST
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// إرسال JSON وإنهاء
function send_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------- وظائف مساعدة -----------------
function get_or_create_user($pdo, $browser_id) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE browser_id = ?');
    $stmt->execute([$browser_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $stmtu = $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ?');
        $stmtu->execute([$user['id']]);
        return (int)$user['id'];
    }
    $st = $pdo->prepare('INSERT INTO users (browser_id) VALUES (?)');
    $st->execute([$browser_id]);
    return (int)$pdo->lastInsertId();
}

// ----------------- معالجة GET -----------------
$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // جلب رسائل المستخدم الجديدة
    if ($action === 'fetch_messages') {
        $browser_id = $_GET['browser_id'] ?? '';
        $since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
        if (!$browser_id) send_json(['messages'=>[]]);

        $stmt = $pdo->prepare('SELECT m.id, m.sender, m.message, m.created_at
                               FROM messages m
                               JOIN users u ON u.id = m.user_id
                               WHERE u.browser_id=? AND m.id>?
                               ORDER BY m.id ASC');
        $stmt->execute([$browser_id, $since_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json(['messages'=>$msgs]);
    }

    // جلب قائمة المستخدمين للمدير مع Badge
    if ($action === 'list_users') {
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) send_json(['error'=>'not authorized']);
        $stmt = $pdo->query('
            SELECT u.id, u.browser_id, u.last_active,
                (SELECT message FROM messages m WHERE m.user_id=u.id ORDER BY id DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM messages m WHERE m.user_id=u.id ORDER BY id DESC LIMIT 1) AS last_time,
                (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND sender="user" AND is_read_by_admin=0) AS has_new_message
            FROM users u
            ORDER BY last_time DESC, u.last_active DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json(['users'=>$rows]);
    }

    // جلب محادثة المستخدم للمدير
    if ($action === 'get_conversation') {
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) send_json(['error'=>'not authorized']);
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        if (!$user_id) send_json(['error'=>'user_id required']);

        // تحديث الرسائل كمقروءة
        $pdo->prepare('UPDATE messages SET is_read_by_admin=1 WHERE user_id=? AND sender="user"')->execute([$user_id]);

        $stmt = $pdo->prepare('SELECT id, sender, message, created_at FROM messages WHERE user_id=? ORDER BY id ASC');
        $stmt->execute([$user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json(['messages'=>$messages]);
    }

    if ($action === 'admin_logout') {
        session_unset(); session_destroy();
        send_json(['ok'=>true]);
    }

    send_json(['error'=>'invalid action']);
}

// ----------------- معالجة POST -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $input['action'] ?? '';

    // تسجيل دخول المدير
    if ($act === 'admin_login') {
        $pass = $input['password'] ?? '';
        global $ADMIN_PASS;
        if ($pass === $ADMIN_PASS) {
            $_SESSION['is_admin'] = true;
            send_json(['ok'=>true]);
        } else send_json(['ok'=>false, 'error'=>'invalid password']);
    }

    // إرسال رسالة
    if ($act === 'send_message') {
        $sender = $input['sender'] ?? 'user';
        $message = trim($input['message'] ?? '');
        if (!$message) send_json(['error'=>'message required']);

        if ($sender==='user') {
            $browser_id = $input['browser_id'] ?? '';
            if (!$browser_id) send_json(['error'=>'browser_id required']);
            $user_id = get_or_create_user($pdo, $browser_id);
        } else {
            if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) send_json(['error'=>'not authorized']);
            $user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
            if (!$user_id) send_json(['error'=>'user_id required']);
        }

        $st = $pdo->prepare('INSERT INTO messages (user_id, sender, message) VALUES (?, ?, ?)');
        $st->execute([$user_id, $sender, $message]);
        send_json(['ok'=>true, 'message_id'=>$pdo->lastInsertId()]);
    }

    send_json(['error'=>'invalid action']);
}

send_json(['error'=>'unsupported request']);
