<?php
declare(strict_types=1);

require __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=UTF-8');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

rate_limit('newsletter-subscribe', 3, 900);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$website = trim((string) ($_POST['website'] ?? ''));

if ($website !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thank you.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'A valid email is required.']);
    exit;
}

$captchaToken = trim((string) ($_POST['h-captcha-response'] ?? ''));
if (!verify_hcaptcha($captchaToken)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'CAPTCHA verification failed. Please try again.']);
    exit;
}

$to = getenv('CYBEROX_SALES_EMAIL') ?: 'sales@cyberoxtech.com';
$subject = 'New Cyberox newsletter subscriber';
$sourcePage = safe_server($_SERVER['HTTP_REFERER'] ?? 'Blog page');
$body = "New newsletter subscriber\n\nEmail: {$email}\nSubmitted at: " . gmdate('Y-m-d H:i:s') . " UTC\nSource page: {$sourcePage}";

if (!cyberox_send_mail($to, $subject, $body, $email, '')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Subscription could not be sent right now.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Subscription request sent.']);
