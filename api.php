<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/koneksi.php';

const MAX_BODY_BYTES = 16384;

function send_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
    if ($raw === false || $raw === '' || strlen($raw) > MAX_BODY_BYTES) {
        send_json(400, ['error' => 'Body request tidak valid atau terlalu besar.']);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        send_json(400, ['error' => 'Format JSON tidak valid.']);
    }

    return $decoded;
}

function parse_user_id(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if (!is_int($id)) {
        send_json(400, ['error' => 'ID data tidak valid.']);
    }

    return $id;
}

function clear_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}

function require_auth(PDO $pdo): array
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!is_int($uid) || $uid <= 0) {
        send_json(401, ['error' => 'Silakan login terlebih dahulu.']);
    }

    $stmt = $pdo->prepare('SELECT id, identifier, created_at FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) {
        clear_session();
        send_json(401, ['error' => 'Sesi tidak valid. Silakan login kembali.']);
    }

    $user['id'] = (int) $user['id'];
    return $user;
}

function handle_login(PDO $pdo): never
{
    $body = read_json_body();
    $identifier = $body['identifier'] ?? null;
    $password = $body['password'] ?? null;

    if (!is_string($identifier) || !is_string($password)) {
        send_json(400, ['error' => 'Input harus berupa teks.']);
    }

    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        send_json(400, ['error' => 'ID/Email dan password wajib diisi.']);
    }

    $stmt = $pdo->prepare('SELECT id, identifier, password_hash FROM users WHERE identifier = ?');
    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        send_json(401, ['error' => 'ID/Email atau password salah.']);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['identifier'] = $user['identifier'];

    send_json(200, [
        'message' => 'Login berhasil.',
        'user' => [
            'id' => (int) $user['id'],
            'identifier' => $user['identifier'],
        ],
    ]);
}

function handle_create_account(PDO $pdo): never
{
    $body = read_json_body();
    $identifier = $body['identifier'] ?? null;
    $password = $body['password'] ?? null;

    if (!is_string($identifier) || !is_string($password)) {
        send_json(400, ['error' => 'Input harus berupa teks.']);
    }

    $identifier = trim($identifier);
    if ($identifier === '' || $password === '' || mb_strlen($identifier, 'UTF-8') > 254) {
        send_json(400, ['error' => 'ID/Email dan password wajib diisi (maks. 254 karakter).']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (identifier, password_hash) VALUES (?, ?)');
        $stmt->execute([$identifier, $hash]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            send_json(409, ['error' => 'ID/Email sudah terdaftar.']);
        }
        send_json(500, ['error' => 'Gagal membuat akun di database.']);
    }

    send_json(201, ['message' => 'Akun berhasil dibuat dan disimpan ke database.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'me') {
        $user = require_auth($pdo);
        send_json(200, ['user' => $user]);
    }

    require_auth($pdo);
    $stmt = $pdo->query('SELECT id, identifier, created_at FROM users ORDER BY id DESC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
    }
    send_json(200, ['users' => $rows]);
}

if ($method === 'POST') {
    if ($action === 'login') {
        handle_login($pdo);
    }
    if ($action === 'logout') {
        clear_session();
        send_json(200, ['message' => 'Logout berhasil.']);
    }
    if ($action === '' || $action === 'create') {
        handle_create_account($pdo);
    }
    send_json(400, ['error' => 'Aksi tidak dikenal.']);
}

if ($method === 'PUT') {
    $currentUser = require_auth($pdo);
    $id = parse_user_id();
    $body = read_json_body();
    $identifier = $body['identifier'] ?? null;
    $oldPassword = $body['old_password'] ?? '';
    $newPassword = $body['password'] ?? '';

    if (!is_string($identifier) || !is_string($oldPassword) || !is_string($newPassword)) {
        send_json(400, ['error' => 'Input harus berupa teks.']);
    }

    $identifier = trim($identifier);
    if ($identifier === '' || mb_strlen($identifier, 'UTF-8') > 254) {
        send_json(400, ['error' => 'ID/Email wajib diisi (maks. 254 karakter).']);
    }

    $check = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $check->execute([$id]);
    $existing = $check->fetch();
    if (!$existing) {
        send_json(404, ['error' => 'Akun tidak ditemukan.']);
    }

    if ($newPassword !== '' && $oldPassword === '') {
        send_json(400, ['error' => 'Password lama wajib diisi untuk mengganti password baru.']);
    }
    if ($oldPassword !== '' && $newPassword === '') {
        send_json(400, ['error' => 'Password baru wajib diisi jika ingin mengganti password.']);
    }
    if ($newPassword !== '' && !password_verify($oldPassword, $existing['password_hash'])) {
        send_json(403, ['error' => 'Password lama tidak sesuai.']);
    }

    try {
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET identifier = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$identifier, $hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET identifier = ? WHERE id = ?');
            $stmt->execute([$identifier, $id]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            send_json(409, ['error' => 'ID/Email sudah digunakan akun lain.']);
        }
        send_json(500, ['error' => 'Gagal memperbarui akun di database.']);
    }

    if ($id === $currentUser['id']) {
        $_SESSION['identifier'] = $identifier;
    }

    send_json(200, ['message' => 'Akun berhasil diperbarui.']);
}

if ($method === 'DELETE') {
    $currentUser = require_auth($pdo);
    $id = parse_user_id();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        send_json(404, ['error' => 'Akun tidak ditemukan.']);
    }

    $loggedOut = false;
    if ($id === $currentUser['id']) {
        clear_session();
        $loggedOut = true;
    }

    send_json(200, [
        'message' => 'Akun berhasil dihapus.',
        'logged_out' => $loggedOut,
    ]);
}

send_json(405, ['error' => 'Method tidak didukung.']);
