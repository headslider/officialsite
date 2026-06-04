<?php
/**
 * REF contact form endpoint with basic anti-spam protection.
 * Place this file in the same directory as index.html.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

const TO_EMAIL = 'contact@r-flash.com';
const FROM_EMAIL = 'noreply@r-flash.com';
const FROM_NAME = 'Real Emotion Factory';
const MAX_BODY_BYTES = 100000;
const MIN_SECONDS_ON_PAGE = 4;
const MAX_SECONDS_ON_PAGE = 7200;
const RATE_LIMIT_COUNT = 3;
const RATE_LIMIT_WINDOW = 3600;
const DUPLICATE_BLOCK_WINDOW = 2592000; // 30日間、同一内容の再送信をブロック

function respond(string $status, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function field(string $key, int $max = 2000): string {
    $value = isset($_POST[$key]) ? (string)$_POST[$key] : '';
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function has_header_injection(string $value): bool {
    return (bool)preg_match('/[\r\n](to:|cc:|bcc:|from:|reply-to:|content-type:)/i', $value);
}

function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $raw = (string)$_SERVER[$key];
            $ip = trim(explode(',', $raw)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return 'unknown';
}

function storage_path(string $prefix): string {
    $dir = __DIR__ . '/contact_guard';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_dir($dir) && is_writable($dir)) return $dir . '/' . $prefix;
    return rtrim(sys_get_temp_dir(), '/\\') . '/' . $prefix;
}

function log_reject(string $reason): void {
    $line = date('c') . "\t" . client_ip() . "\t" . $reason . "\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n";
    @file_put_contents(storage_path('ref_contact_reject.log'), $line, FILE_APPEND | LOCK_EX);
}

function reject(string $reason, string $public = '送信内容を確認してください。'): void {
    log_reject($reason);
    respond('error', $public, 400);
}


function normalize_for_signature(string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/https?:\/\/\S+|www\.\S+/iu', '[url]', $value) ?? $value;
    $value = preg_replace('/[\s　]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/[!！?？。．、,.\-ー＿_\[\]\(\)（）「」『』"\'`]+/u', '', $value) ?? $value;
    return trim($value);
}

function read_guard_lines(string $filename): array {
    $path = storage_path($filename);
    if (!is_file($path)) return [];
    $lines = preg_split('/\R/u', (string)@file_get_contents($path));
    if (!is_array($lines)) return [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $out[] = $line;
    }
    return $out;
}

function manual_block_check(string $email, string $allText): void {
    $emailLower = strtolower(trim($email));
    foreach (read_guard_lines('blocked_emails.txt') as $blockedEmail) {
        if ($emailLower === strtolower(trim($blockedEmail))) {
            reject('manual_block_email');
        }
    }
    $normalizedText = normalize_for_signature($allText);
    foreach (read_guard_lines('blocked_phrases.txt') as $phrase) {
        $normalizedPhrase = normalize_for_signature($phrase);
        if ($normalizedPhrase !== '' && str_contains($normalizedText, $normalizedPhrase)) {
            reject('manual_block_phrase');
        }
    }
}

function duplicate_content_check(string $name, string $company, string $email, string $tel, string $message): void {
    // IPを変えて定期送信されるbot対策として、送信内容そのものの指紋を保存します。
    // email/telを含む完全一致系と、本文だけが同一のパターンを両方見ます。
    $now = time();
    $messageSig = normalize_for_signature($message);
    $identitySig = normalize_for_signature($name . '|' . $company . '|' . $email . '|' . $tel . '|' . $message);
    if ($messageSig === '' || $identitySig === '') return;

    $hashes = [
        'full_' . hash('sha256', $identitySig),
        'message_' . hash('sha256', $messageSig),
    ];

    foreach ($hashes as $hash) {
        $path = storage_path('dup_' . $hash . '.json');
        if (is_file($path)) {
            $json = json_decode((string)@file_get_contents($path), true);
            $firstSeen = is_array($json) && isset($json['first_seen']) && is_numeric($json['first_seen']) ? (int)$json['first_seen'] : 0;
            if ($firstSeen > 0 && $firstSeen > $now - DUPLICATE_BLOCK_WINDOW) {
                log_reject('duplicate_content_' . substr($hash, 0, 16));
                respond('error', '同じ内容のお問い合わせがすでに送信されています。内容を変更して再度お試しください。', 429);
            }
        }
    }

    foreach ($hashes as $hash) {
        $path = storage_path('dup_' . $hash . '.json');
        $data = [
            'first_seen' => $now,
            'last_seen' => $now,
            'ip' => client_ip(),
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'message_preview' => mb_substr($messageSig, 0, 120, 'UTF-8'),
        ];
        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

function rate_limit_check(): void {
    $ip = client_ip();
    $key = hash('sha256', $ip . '|ref-contact');
    $path = storage_path('rate_' . $key . '.json');
    $now = time();
    $times = [];
    if (is_file($path)) {
        $json = json_decode((string)@file_get_contents($path), true);
        if (is_array($json)) $times = array_values(array_filter($json, 'is_numeric'));
    }
    $times = array_values(array_filter($times, fn($t) => $t > $now - RATE_LIMIT_WINDOW));
    if (count($times) >= RATE_LIMIT_COUNT) {
        log_reject('rate_limit');
        respond('error', '短時間に複数回送信されています。時間をおいて再度お試しください。', 429);
    }
    $times[] = $now;
    @file_put_contents($path, json_encode($times), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', '不正なリクエストです。', 405);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > MAX_BODY_BYTES) reject('too_large');

// JavaScript経由の通常送信だけを受け付け、botの直接POSTを減らします。
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    reject('missing_ajax_header');
}

// 同一サイト外からのPOSTを抑止します。Origin/Refererがある場合は現在のホストと一致させます。
$currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
    if (empty($_SERVER[$header])) continue;
    $host = strtolower((string)(parse_url((string)$_SERVER[$header], PHP_URL_HOST) ?: ''));
    if ($host !== '' && $currentHost !== '' && $host !== $currentHost) {
        reject('bad_origin_' . $header);
    }
}

// Honeypot。通常ユーザーには見えないため、値が入っていればbot扱い。
if (field('website', 200) !== '' || field('url', 200) !== '') {
    reject('honeypot_filled');
}

$loadedAt = field('loaded_at', 30);
$jsToken = field('js_token', 80);
if ($loadedAt === '' || $jsToken === '') reject('missing_js_fields');
if (!ctype_digit($loadedAt)) reject('bad_loaded_at');
$elapsed = (int)floor((time() * 1000 - (int)$loadedAt) / 1000);
if ($elapsed < MIN_SECONDS_ON_PAGE) reject('too_fast');
if ($elapsed > MAX_SECONDS_ON_PAGE) reject('too_old');
if (!preg_match('/^[A-Za-z0-9+\/=]{12,80}$/', $jsToken)) reject('bad_js_token');

rate_limit_check();

$name = field('name', 100);
$company = field('company', 120);
$email = field('email', 180);
$tel = field('tel', 60);
$message = field('message', 3000);

if ($name === '' || $email === '' || $message === '') {
    respond('error', '必須項目を入力してください。', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond('error', '正しいメールアドレスをご入力ください。', 400);
}
foreach ([$name, $company, $email, $tel] as $v) {
    if (has_header_injection($v)) reject('header_injection');
}

// URL過多・典型スパム語を抑止。必要に応じて増減してください。
$allText = $name . "\n" . $company . "\n" . $tel . "\n" . $message;
$urlCount = preg_match_all('/https?:\/\/|www\./i', $allText);
if ($urlCount !== false && $urlCount >= 3) reject('too_many_urls', 'URLが多すぎます。内容を確認してください。');

$blockedPatterns = [
    '/\bviagra\b/i', '/\bcialis\b/i', '/\bcasino\s+bonus\b/i', '/\bcrypto\s+investment\b/i',
    '/\bloan\s+offer\b/i', '/\bseo\s+backlinks?\b/i', '/\bguest\s+post\b/i',
    '/\btelegram\b/i', '/\bwhatsapp\b/i', '/\bforex\b/i'
];
foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $allText)) reject('blocked_pattern');
}

// 手動ブロック。contact_guard/blocked_emails.txt または blocked_phrases.txt に1行ずつ追加すると即時ブロックできます。
manual_block_check($email, $allText);

// 同じ本文・同じ入力内容が定期的に送られるbotを30日間ブロックします。
duplicate_content_check($name, $company, $email, $tel, $message);

$subject = '【REF公式サイト】お問い合わせ';
$body = "REF公式サイトからお問い合わせがありました。\n\n"
      . "お名前: {$name}\n"
      . "会社名: " . ($company !== '' ? $company : '未入力') . "\n"
      . "メール: {$email}\n"
      . "電話番号: " . ($tel !== '' ? $tel : '未入力') . "\n"
      . "送信元IP: " . client_ip() . "\n"
      . "送信日時: " . date('Y-m-d H:i:s') . "\n\n"
      . "お問い合わせ内容:\n{$message}\n";

// 日本語メールの文字化け対策。
// CORESERVER等の一部環境では、UTF-8本文＋mb_send_mailの自動変換＋UTF-8ヘッダーが混在すると
// 受信側で「????」になる場合があるため、本文・件名・From名・ヘッダーを ISO-2022-JP に統一します。
if (function_exists('mb_language')) @mb_language('Japanese');
if (function_exists('mb_internal_encoding')) @mb_internal_encoding('UTF-8');

$subjectForMail = $subject;
$bodyForMail = $body;
$fromNameForMail = FROM_NAME;
$charset = 'UTF-8';
$transferEncoding = '8bit';

if (function_exists('mb_convert_encoding') && function_exists('mb_encode_mimeheader')) {
    $charset = 'ISO-2022-JP';
    $transferEncoding = '7bit';
    $subjectForMail = mb_encode_mimeheader(
        mb_convert_encoding($subject, 'ISO-2022-JP', 'UTF-8'),
        'ISO-2022-JP',
        'B',
        "\r\n"
    );
    $fromNameForMail = mb_encode_mimeheader(
        mb_convert_encoding(FROM_NAME, 'ISO-2022-JP', 'UTF-8'),
        'ISO-2022-JP',
        'B',
        "\r\n"
    );
    $bodyForMail = mb_convert_encoding($body, 'ISO-2022-JP', 'UTF-8');
} else {
    $subjectForMail = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromNameForMail = '=?UTF-8?B?' . base64_encode(FROM_NAME) . '?=';
}

$headers = [];
$headers[] = 'From: ' . $fromNameForMail . ' <' . FROM_EMAIL . '>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=' . $charset;
$headers[] = 'Content-Transfer-Encoding: ' . $transferEncoding;
$headers[] = 'X-Mailer: REF Contact Form';

// すでに件名・本文・ヘッダーの文字コードを明示的に揃えているため、mail()で送信します。
$sent = @mail(TO_EMAIL, $subjectForMail, $bodyForMail, implode("\r\n", $headers));

if (!$sent) {
    log_reject('mail_failed');
    respond('error', '送信に失敗しました。時間をおいて再度お試しください。', 500);
}

respond('ok', '送信が完了しました。');
