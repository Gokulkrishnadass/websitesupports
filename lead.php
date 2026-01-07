<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
  exit;
}

// Basic sanitize
$name    = trim($data['name'] ?? '');
$email   = trim($data['email'] ?? '');
$company = trim($data['company'] ?? '');
$website = trim($data['website'] ?? '');
$need    = trim($data['need'] ?? '');
$message = trim($data['message'] ?? '');

// Validate
if ($name === '' || $email === '' || $company === '' || $website === '' || $need === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please fill all required fields.']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
  exit;
}
if (!preg_match('#^https?://#i', $website)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Website URL must start with http:// or https://']);
  exit;
}

// ✅ Change this to your real receiving email:
$to = 'you@yourdomain.com';

$subject = "Free Website Audit Request ($company)";
$body =
  "Name: $name\n" .
  "Email: $email\n" .
  "Company: $company\n" .
  "Website: $website\n" .
  "Need: $need\n\n" .
  "Message:\n" . ($message !== '' ? $message : '-');

$headers = "From: WebsiteSupports Lead <no-reply@websitesupports.com>\r\n";
$headers .= "Reply-To: $email\r\n";

$sent = mail($to, $subject, $body, $headers);

if (!$sent) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Mail failed. Please check server mail configuration.']);
  exit;
}

echo json_encode(['ok' => true, 'message' => 'Thanks! Your request was sent.']);
