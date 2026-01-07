<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void {
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

// ======= CONFIG =======
$TO_EMAIL  = 'gokulkrishnadass@websitesupports.com';

$SMTP_HOST = 'smtp.hostinger.com';
$SMTP_USER = 'gokulkrishnadass@websitesupports.com';
$SMTP_PASS = getenv('SMTP_PASS') ?: 'Krishnadass04!'; // ✅ set in hosting env
$SMTP_PORT = 587;
// ======================

// Parse JSON (fallback to POST)
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

// ✅ Honeypot (must be a separate hidden field!)
if (!empty($data['company_hp'])) {
  respond(200, ['ok' => true, 'message' => 'Thanks']);
}

// Validate + sanitize
$name    = trim((string)($data['name'] ?? ''));
$email   = trim((string)($data['email'] ?? ''));
$company = trim((string)($data['company'] ?? ''));
$website = trim((string)($data['website'] ?? ''));
$need    = trim((string)($data['need'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || $email === '' || $company === '' || $website === '' || $need === '') {
  respond(422, ['ok' => false, 'error' => 'Please fill all required fields.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(422, ['ok' => false, 'error' => 'Invalid email address.']);
}
if (!preg_match('~^https?://~i', $website)) {
  respond(422, ['ok' => false, 'error' => 'Website URL must start with http:// or https://']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$subject = "New Lead - {$name} ({$company})";
$body =
"New lead received:\n\n".
"Name: {$name}\n".
"Email: {$email}\n".
"Company Name: {$company}\n".
"Website: {$website}\n".
"Need: {$need}\n\n".
"Message:\n".($message ?: "-")."\n\n".
"IP: {$ip}\n".
"Time: ".date('Y-m-d H:i:s')."\n";

if ($SMTP_PASS === '') {
  respond(500, ['ok' => false, 'error' => 'SMTP password not configured (SMTP_PASS env missing).']);
}

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host       = $SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = $SMTP_USER;
  $mail->Password   = $SMTP_PASS;
  $mail->Port       = $SMTP_PORT;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // ✅ for 587
  $mail->CharSet    = 'UTF-8';
  $mail->isHTML(false);

  $mail->setFrom($SMTP_USER, 'websitesupports.com Leads');
  $mail->addAddress($TO_EMAIL);
  $mail->addReplyTo($email, $name);

  $mail->Subject = $subject;
  $mail->Body    = $body;

  $mail->send();
  respond(200, ['ok' => true, 'message' => 'Thanks! Your request was sent successfully.']);
} catch (Exception $e) {
  respond(500, ['ok' => false, 'error' => 'SMTP send failed: ' . $mail->ErrorInfo]);
}
