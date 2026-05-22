<?php
declare(strict_types=1);

function cyberox_send_mail(string $to, string $subject, string $body, string $replyToEmail = '', string $replyToName = ''): bool
{
    $fromEmail = getenv('CYBEROX_MAIL_FROM') ?: 'no-reply@cyberoxtech.com';
    $fromName = getenv('CYBEROX_MAIL_FROM_NAME') ?: 'Cyberox Website';

    $headers = [
        'From: ' . cyberox_header_name($fromName) . ' <' . $fromEmail . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: Cyberox Website',
    ];

    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . cyberox_header_name($replyToName ?: $replyToEmail) . ' <' . $replyToEmail . '>';
    }

    return mail($to, cyberox_header_line($subject), $body, implode("\r\n", $headers));
}

function cyberox_header_line(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);
}

function cyberox_header_name(string $value): string
{
    $value = cyberox_header_line($value);
    return str_replace(['"', '<', '>'], '', $value);
}

/**
 * IP-based sliding-window rate limiter using tmp files.
 * Exits with HTTP 429 if the limit is exceeded.
 */
function rate_limit(string $endpoint, int $max = 5, int $window = 900): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/cyberox_rl_' . md5($endpoint . $ip) . '.json';
    $now  = time();

    $timestamps = [];
    if (file_exists($file)) {
        $timestamps = json_decode((string) file_get_contents($file), true) ?? [];
    }

    $timestamps = array_values(array_filter($timestamps, fn($t) => $now - $t < $window));

    if (count($timestamps) >= $max) {
        http_response_code(429);
        header('Retry-After: ' . $window);
        echo json_encode(['ok' => false, 'message' => 'Too many requests. Please wait before trying again.']);
        exit;
    }

    $timestamps[] = $now;
    file_put_contents($file, json_encode($timestamps), LOCK_EX);
}

/** Strip newlines and tags from untrusted server variables before putting them in email bodies. */
function safe_server(string $value): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($value)) ?? $value);
}
