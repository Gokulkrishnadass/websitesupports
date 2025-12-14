<?php
/**
 * lead.php
 * Secure lead capture endpoint:
 * - Accepts POST (JSON or form-encoded)
 * - Validates & sanitizes fields
 * - Anti-spam: honeypot + simple rate-limit
 * - Sends email to site owner
 *
 * IMPORTANT: If mail() doesn't work on your host, configure SMTP or use PHPMailer.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

// ✅ Configure these
$TO_EMAIL   = 'gokulkrishnadass@websitesupports.com';   // <-- change to your email
$SITE_NAME  = 'Need Website Support';  // shown in email subject
$FROM_EMAIL = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com'); // uses your domain

// ---------- Parse input (supports JSON and form-encoded) ----------
$raw = file_get_contents('php://input');
$data = [];

// JSON?
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
} else {
    // form-data or x-www-form-urlencoded
    $data = $_POST;
}

// ---------- Honeypot (add a hidden field named "company" in form) ----------
$honeypot = trim((string)($data['company'] ?? ''));
if ($honeypot !== '') {
    // bot filled hidden field
    respond(200, ['ok' => true, 'message' => 'Thanks']); // pretend success
}

// ---------- Basic rate-limit per IP (1 request / 20 seconds) ----------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/lead_rate_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $ip);
$now = time();
if (file_exists($rateFile)) {
    $last = (int)file_get_contents($rateFile);
    if ($now - $last < 20) {
        respond(429, ['ok' => false, 'error' => 'Too many requests. Please try again shortly.']);
    }
}
@file_put_contents($rateFile, (string)$now);

// ---------- Sanitize & validate fields ----------
$name    = trim((string)($data['name'] ?? ''));
$email   = trim((string)($data['email'] ?? ''));
$country = trim((string)($data['country'] ?? ''));
$website = trim((string)($data['website'] ?? ''));
$need    = trim((string)($data['need'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || $email === '' || $country === '' || $website === '' || $need === '') {
    respond(422, ['ok' => false, 'error' => 'Please fill all required fields.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'error' => 'Invalid email address.']);
}

if (!preg_match('~^https?://~i', $website)) {
    respond(422, ['ok' => false, 'error' => 'Website URL must start with http:// or https://']);
}

// Allow only your target countries (optional)
$allowedCountries = ['Australia', 'United States', 'Canada'];
if (!in_array($country, $allowedCountries, true)) {
    respond(422, ['ok' => false, 'error' => 'Invalid country selection.']);
}

// Clean dangerous characters for headers
$safeName  = preg_replace("/[\r\n]+/", " ", $name);
$safeEmail = preg_replace("/[\r\n]+/", " ", $email);

// ---------- Build email ----------
$subject = sprintf('[%s] New Lead: %s (%s)', $SITE_NAME, $safeName, $country);

$body = "You received a new lead:\n\n"
      . "Name: {$name}\n"
      . "Email: {$email}\n"
      . "Country: {$country}\n"
      . "Website: {$website}\n"
      . "Need: {$need}\n\n"
      . "Message:\n" . ($message !== '' ? $message : '-') . "\n\n"
      . "IP: {$ip}\n"
      . "Time: " . date('Y-m-d H:i:s') . "\n";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . $SITE_NAME . ' <' . $FROM_EMAIL . '>';
$headers[] = 'Reply-To: ' . $safeName . ' <' . $safeEmail . '>';

// ---------- Send ----------
$sent = @mail($TO_EMAIL, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    respond(500, ['ok' => false, 'error' => 'Email failed to send. Your host may block PHP mail().']);
}

respond(200, ['ok' => true, 'message' => 'Thanks! Your request was sent successfully.']);
