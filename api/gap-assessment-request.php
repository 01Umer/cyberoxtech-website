<?php
declare(strict_types=1);

require __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=UTF-8');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

rate_limit('gap-assessment-request', 5, 900);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function gap_value(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_array($value) ? '' : trim((string) $value);
}

function gap_safe(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', strip_tags($value)) ?? $value);
}

$fullName = gap_safe(gap_value('full_name'));
$company = gap_safe(gap_value('company'));
$email = gap_safe(gap_value('email'));
$phone = gap_safe(gap_value('phone'));
$country = gap_safe(gap_value('country'));
$companySize = gap_safe(gap_value('company_size'));
$challenge = gap_safe(gap_value('challenge'));
$website = gap_value('website');

if ($website !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thank you.']);
    exit;
}

$errors = [];
if ($fullName === '') $errors[] = 'Full name is required.';
if ($company === '') $errors[] = 'Company is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid business email is required.';
if ($country === '') $errors[] = 'Country is required.';
if ($challenge === '') $errors[] = 'Current security challenge is required.';

if ($errors !== []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$captchaToken = gap_safe(gap_value('h-captcha-response'));
if (!verify_hcaptcha($captchaToken)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'CAPTCHA verification failed. Please try again.']);
    exit;
}

$to = getenv('CYBEROX_SALES_EMAIL') ?: 'sales@cyberoxtech.com';
$subject = 'New free gap assessment request - ' . $company;
$submittedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$sourcePage = safe_server($_SERVER['HTTP_REFERER'] ?? 'Gap assessment page');
$ipAddress  = safe_server($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
$body = <<<EMAIL
New free gap assessment request

Full name: {$fullName}
Company: {$company}
Business email: {$email}
Phone: {$phone}
Country: {$country}
Company size: {$companySize}
Current security challenge: {$challenge}

Submitted at: {$submittedAt}
Source page: {$sourcePage}
IP address: {$ipAddress}
EMAIL;

if (!cyberox_send_mail($to, $subject, $body, $email, $fullName)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'We could not send your request right now. Please email sales@cyberoxtech.com directly.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Your gap assessment request has been sent.']);
