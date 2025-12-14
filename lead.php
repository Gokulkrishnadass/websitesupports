<?php
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

// ======= CONFIG =======
$TO_EMAIL = 'gokulkrishnadass@websitesupports.com';

// SMTP settings (get from your email hosting / cPanel)
$SMTP_HOST = 'mail.websitesupports.com';   // e.g. mail.yourdomain.com
$SMTP_USER = 'gokulkrishnadass@websitesupports.com';
$SMTP_PASS = 'Krishnadass04!';        // use app password if needed
$SMTP_PORT = 587;                          // usually 587 (TLS) or 465 (SSL)
$SMTP_SEC  = 'tls';                        // 'tls' or 'ssl'
// =======================

// Parse JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

// Honeypot
if (!empty($data['company'])) {
  respond(200, ['ok' => true, 'message' => 'Thanks']);
}

// Validate
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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$subject = "New Lead - {$name} ({$country})";
$body =
"New lead received:\n\n".
"Name: {$name}\n".
"Email: {$email}\n".
"Country: {$country}\n".
"Website: {$website}\n".
"Need: {$need}\n\n".
"Message:\n".($message ?: "-")."\n\n".
"IP: {$ip}\n".
"Time: ".date('Y-m-d H:i:s')."\n";

// PHPMailer
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host       = $SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = $SMTP_USER;
  $mail->Password   = $SMTP_PASS;
  $mail->Port       = $SMTP_PORT;
  $mail->SMTPSecure = $SMTP_SEC; // 'tls' or 'ssl'

  // Sender should be your domain email
  $mail->setFrom($SMTP_USER, 'websitesupports.com Leads');
  $mail->addAddress($TO_EMAIL);

  // Reply-to should be the customer
  $mail->addReplyTo($email, $name);

  $mail->Subject = $subject;
  $mail->Body    = $body;

  $mail->send();
  respond(200, ['ok' => true, 'message' => 'Thanks! Your request was sent successfully.']);
} catch (Exception $e) {
  respond(500, ['ok' => false, 'error' => 'SMTP send failed: '.$mail->ErrorInfo]);
}
