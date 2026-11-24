<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');

// Browser protections complement (but never replace) server-side authorization.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if ($https) header('Strict-Transport-Security: max-age=31536000');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('campus_monitor_session');
$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
session_set_cookie_params(['lifetime'=>0,'path'=>$cookiePath,'secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();

function clearSession(): void {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (isset($_SESSION['userId']) && time() - (int)($_SESSION['lastActivity'] ?? 0) > 1800) {
    clearSession();
    $_SESSION['flash'] = ['type'=>'error','message'=>'Your session expired. Please sign in again.'];
}
if (isset($_SESSION['userId'])) $_SESSION['lastActivity'] = time();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// Escape all dynamic values before printing them in HTML.
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function redirect(string $page = 'dashboard', array $params = []): never {
    header('Location: index.php?'.http_build_query(['page'=>$page]+$params), true, 303); exit;
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = compact('message','type'); }
function abortRequest(int $status, string $message): never {
    http_response_code($status);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request error</title><link rel="stylesheet" href="assets/style.css"><main class="error-page panel"><h1>Request error</h1><p>'.e($message).'</p><a class="btn" href="index.php">Return to the system</a></main></html>'; exit;
}
set_exception_handler(function(Throwable $error): void {
    // Never expose SQL statements, stack traces or credentials to the browser.
    error_log('Student monitor: '.get_class($error).' code '.$error->getCode().' at '.$error->getFile().':'.$error->getLine());
    abortRequest(500, 'The system could not complete your request. Check the database connection or contact your administrator.');
});
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = require __DIR__.'/config.php';
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt; }
function one(string $sql, array $params = []): ?array { return query($sql,$params)->fetch() ?: null; }
function all(string $sql, array $params = []): array { return query($sql,$params)->fetchAll(); }
function currentUser(): ?array {
    if (!isset($_SESSION['userId'])) return null;
    $user = one('SELECT id, username, role, display_name, must_change, auth_version FROM users WHERE id = ?', [$_SESSION['userId']]);
    if (!$user || (int)$user['auth_version'] !== (int)($_SESSION['authVersion'] ?? -1)) { clearSession(); redirect('login'); }
    return $user;
}
function requireRole(?array $user, string $role): void {
    if (!$user) abortRequest(401,'Sign in to continue.');
    if ($user['role'] !== $role) abortRequest(403,'You do not have permission to perform this action.');
}
function post(string $key): string { $value = $_POST[$key] ?? ''; return is_string($value) ? $value : ''; }
function param(string $key): string { $value = $_GET[$key] ?? ''; return is_string($value) ? $value : ''; }
function textField(string $key, int $max, string $label): string {
    $v = trim(post($key));
    if ($v === '' || preg_match('//u', $v) !== 1 || strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/', $v)) throw new DomainException("Enter a valid $label (maximum $max bytes).");
    return $v;
}
function validPassword(string $value): bool { return strlen($value)>=12 && strlen($value)<=72 && !str_contains($value, "\0"); }
function validStudentNumber(string $value): bool { return preg_match('/\A[A-Za-z0-9_-]{3,32}\z/', $value) === 1; }
function intId(string $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) throw new DomainException('Select a valid student.'); return $id;
}
function validDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $date !== false && $date->format('Y-m-d') === $value && $value >= '1900-01-01' && $value <= date('Y-m-d');
}
function validGrade(string $value): bool { return preg_match('/\A(?:\d{1,3})(?:\.\d{1,2})?\z/', $value) === 1 && (float)$value <= 100; }
function ownedStudent(int $studentId, int $teacherId): array {
    $student = one('SELECT s.*, u.display_name, u.username FROM students s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.teacher_id=?', [$studentId,$teacherId]);
    if (!$student) abortRequest(404,'Student record not found.'); return $student;
}
function studentProfile(int $userId): array {
    $student = one('SELECT s.*, u.display_name FROM students s JOIN users u ON u.id=s.user_id WHERE s.user_id=?', [$userId]);
    if (!$student) abortRequest(404,'Your student profile is not available. Contact your teacher.'); return $student;
}
function studentList(int $teacherId): array {
    return all('SELECT s.*,u.display_name FROM students s JOIN users u ON u.id=s.user_id WHERE s.teacher_id=? ORDER BY s.section,u.display_name',[$teacherId]);
}
function summary(int $studentId): array {
    $a = one("SELECT COUNT(*) total, COALESCE(SUM(status='Present'),0) present, COALESCE(SUM(status='Absent'),0) absent FROM attendance WHERE student_id=?",[$studentId]);
    $g = one('SELECT COUNT(*) grade_count, AVG(grade) average FROM grades WHERE student_id=?',[$studentId]);
    return $a+$g+['rate'=>(int)$a['total'] ? round(100*(int)$a['present']/(int)$a['total'],1) : null];
}
