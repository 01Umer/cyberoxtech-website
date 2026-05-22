<?php
declare(strict_types=1);

require __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=UTF-8');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

rate_limit('demo-request', 5, 900);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function field(string $name): string
{
    $value = $_POST[$name] ?? '';
    if (is_array($value)) {
        return '';
    }

    return trim((string) $value);
}

function clean(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? $value;
    return trim($value);
}

$fullName = clean(field('fullName'));
$companyName = clean(field('companyName'));
$workEmail = clean(field('workEmail'));
$country = clean(field('country'));
$phone = clean(field('phone'));
$challenge = clean(field('challenge'));
$hearAbout = clean(field('hearAbout'));
$website = field('website'); // honeypot

if ($website !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thank you.']);
    exit;
}

$errors = [];

if ($fullName === '') {
    $errors[] = 'Full name is required.';
}

if ($companyName === '') {
    $errors[] = 'Company name is required.';
}

if (!filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid work email is required.';
}

if ($country === '') {
    $errors[] = 'Country is required.';
}

if ($challenge === '') {
    $errors[] = 'Current challenge is required.';
}

if ($errors !== []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$to = getenv('CYBEROX_SALES_EMAIL') ?: 'sales@cyberoxtech.com';
$subject = 'New CyberShield demo request - ' . $companyName;
$submittedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$ipAddress   = safe_server($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
$userAgent   = safe_server($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
$page        = safe_server($_SERVER['HTTP_REFERER'] ?? 'CyberShield demo page');

$body = <<<EMAIL
New CyberShield demo request

Full name: {$fullName}
Company: {$companyName}
Work email: {$workEmail}
Country: {$country}
Phone: {$phone}
Current challenge: {$challenge}
Heard about Cyberox via: {$hearAbout}

Submitted at: {$submittedAt}
Source page: {$page}
IP address: {$ipAddress}
User agent: {$userAgent}
EMAIL;

$sent = cyberox_send_mail($to, $subject, $body, $workEmail, $fullName);

if (!$sent) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'We could not send your request right now. Please email sales@cyberoxtech.com directly.',
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Your demo request has been sent. We will be in touch shortly.',
]);
