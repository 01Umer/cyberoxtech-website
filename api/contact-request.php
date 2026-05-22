<?php
declare(strict_types=1);

require __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

rate_limit('contact-request', 5, 900);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

function value(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_array($value) ? '' : trim((string) $value);
}

function safe_text(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', strip_tags($value)) ?? $value);
}

$firstName = safe_text(value('first_name'));
$lastName = safe_text(value('last_name'));
$company = safe_text(value('company'));
$email = safe_text(value('email'));
$phone = safe_text(value('phone'));
$country = safe_text(value('country'));
$service = safe_text(value('service'));
$message = trim(strip_tags(value('message')));
$website = value('website');

if ($website !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thank you.']);
    exit;
}

$errors = [];
if ($firstName === '') $errors[] = 'First name is required.';
if ($lastName === '') $errors[] = 'Last name is required.';
if ($company === '') $errors[] = 'Company is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid work email is required.';
if ($country === '') $errors[] = 'Country is required.';

if ($errors !== []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$to = getenv('CYBEROX_SALES_EMAIL') ?: 'sales@cyberoxtech.com';
$subject = 'New Cyberox website contact - ' . $company;
$submittedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$sourcePage = safe_server($_SERVER['HTTP_REFERER'] ?? 'Contact page');
$ipAddress  = safe_server($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
$body = <<<EMAIL
New Cyberox website contact

Name: {$firstName} {$lastName}
Company: {$company}
Email: {$email}
Phone: {$phone}
Country: {$country}
Service interest: {$service}

Message:
{$message}

Submitted at: {$submittedAt}
Source page: {$sourcePage}
IP address: {$ipAddress}
EMAIL;

if (!cyberox_send_mail($to, $subject, $body, $email, trim($firstName . ' ' . $lastName))) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'We could not send your message right now. Please email sales@cyberoxtech.com directly.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Your message has been sent.']);
