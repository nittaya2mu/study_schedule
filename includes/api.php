<?php
/**
 * API helpers — ทุกไฟล์ใน api/ ใช้รูปแบบเดียวกัน
 *
 *   Request : GET  api/xxx.php?action=list
 *             POST api/xxx.php   body JSON {"action":"create", ...}   header X-CSRF-Token
 *   Response: {"ok":true,"data":...}  หรือ  {"ok":false,"error":"...","fields":{...}}
 */
require_once __DIR__ . '/bootstrap.php';

class ApiError extends Exception
{
    public function __construct(string $message, int $code = 400, public array $fields = [])
    {
        parent::__construct($message, $code);
    }
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

/** อ่าน input จาก JSON body + query string */
function api_input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        $data = array_merge($_GET, $_POST, is_array($json) ? $json : []);
    }
    return $data;
}

/**
 * เริ่มต้น API: ตรวจ login + CSRF (สำหรับคำขอที่เปลี่ยนข้อมูล) แล้ว dispatch ไปยัง handler ตาม action
 * @param array<string,callable> $handlers  action => fn(int $userId, array $in): mixed
 * @param string[] $readOnly  action ที่อนุญาตให้เรียกด้วย GET
 */
function api_run(array $handlers, array $readOnly = ['list', 'get']): never
{
    try {
        $userId = current_user_id();
        if (!$userId) throw new ApiError('กรุณาเข้าสู่ระบบ', 401);

        $in = api_input();
        $action = (string)($in['action'] ?? 'list');
        if (!isset($handlers[$action])) throw new ApiError("Unknown action: $action", 404);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($action, $readOnly, true)) {
            if ($method !== 'POST') throw new ApiError('Method not allowed', 405);
            if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf'] ?? null))) {
                throw new ApiError('CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้า', 419);
            }
        }
        // ปลด session lock ให้ request อื่นทำงานคู่ขนานได้ (เช่นระหว่างรัน GA)
        session_write_close();

        $result = $handlers[$action]($userId, $in);
        json_out(['ok' => true, 'data' => $result]);
    } catch (ApiError $e) {
        json_out(['ok' => false, 'error' => $e->getMessage(), 'fields' => $e->fields], $e->getCode() ?: 400);
    } catch (Throwable $e) {
        error_log('[API] ' . $e);
        json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

// ---------------------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------------------
function v_required(array $in, string $key, string $label): string
{
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '') throw new ApiError("กรุณากรอก $label", 422, [$key => "กรุณากรอก $label"]);
    return $v;
}

function v_int(array $in, string $key, string $label, int $min, int $max, ?int $default = null): int
{
    $raw = $in[$key] ?? $default;
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        throw new ApiError("$label ต้องเป็นตัวเลข", 422, [$key => "$label ต้องเป็นตัวเลข"]);
    }
    $v = (int)$raw;
    if ($v < $min || $v > $max) throw new ApiError("$label ต้องอยู่ระหว่าง {$min}–{$max}", 422, [$key => "ต้องอยู่ระหว่าง {$min}–{$max}"]);
    return $v;
}

function v_float(array $in, string $key, string $label, float $min, float $max, ?float $default = null): float
{
    $raw = $in[$key] ?? $default;
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        throw new ApiError("$label ต้องเป็นตัวเลข", 422, [$key => "$label ต้องเป็นตัวเลข"]);
    }
    $v = (float)$raw;
    if ($v < $min || $v > $max) throw new ApiError("$label ต้องอยู่ระหว่าง {$min}–{$max}", 422, [$key => "ต้องอยู่ระหว่าง {$min}–{$max}"]);
    return $v;
}

function v_date(array $in, string $key, string $label, bool $required = true): ?string
{
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '' && !$required) return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) throw new ApiError("$label ไม่ถูกต้อง", 422, [$key => 'รูปแบบวันที่ไม่ถูกต้อง']);
    return $v;
}

function v_time(array $in, string $key, string $label): string
{
    $v = trim((string)($in[$key] ?? ''));
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:\d\d)?$/', $v)) {
        throw new ApiError("$label ไม่ถูกต้อง", 422, [$key => 'รูปแบบเวลา HH:MM']);
    }
    return substr($v, 0, 5);
}

function v_enum(array $in, string $key, array $allowed, string $default): string
{
    $v = (string)($in[$key] ?? $default);
    return in_array($v, $allowed, true) ? $v : $default;
}

function v_color(array $in, string $key, string $default = '#2a78d6'): string
{
    $v = (string)($in[$key] ?? $default);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $default;
}

/** ตรวจว่าวิชาเป็นของผู้ใช้คนนี้ */
function owned_subject(int $userId, int $subjectId): array
{
    $st = db()->prepare('SELECT * FROM subjects WHERE subject_id = ? AND user_id = ?');
    $st->execute([$subjectId, $userId]);
    $s = $st->fetch();
    if (!$s) throw new ApiError('ไม่พบรายวิชา', 404);
    return $s;
}
