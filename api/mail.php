<?php
declare(strict_types=1);

header_remove('X-Powered-By');

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

/** Strip newlines and tags from untrusted server variables before putting them in email bodies. */
function safe_server(string $value): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($value)) ?? $value);
}

/**
 * Returns the real client IP, trusting CF-Connecting-IP or X-Forwarded-For
 * only when the connection comes from a known Cloudflare range.
 * Falls back to REMOTE_ADDR (always safe, cannot be spoofed at TCP level).
 */
function real_ip(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Cloudflare sends CF-Connecting-IP; trust it only if REMOTE_ADDR is a CF range.
    $cfRanges = [
        '173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22',
        '141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20',
        '197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
        '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22',
        '2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32',
        '2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32',
    ];

    if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && ip_in_ranges($remoteAddr, $cfRanges)) {
        return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: $remoteAddr;
    }

    // Honour X-Forwarded-For only for private/loopback (i.e. local reverse proxies on Laragon/nginx).
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_private_ip($remoteAddr)) {
        $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        return filter_var($forwarded, FILTER_VALIDATE_IP) ?: $remoteAddr;
    }

    return $remoteAddr;
}

function ip_in_ranges(string $ip, array $ranges): bool
{
    foreach ($ranges as $range) {
        if (ip_in_cidr($ip, $range)) return true;
    }
    return false;
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr);
    if (str_contains($cidr, ':')) {
        // IPv6 — skip for now; Cloudflare v6 ranges are rarely used in shared hosting
        return false;
    }
    $ip_long     = ip2long($ip);
    $subnet_long = ip2long($subnet);
    if ($ip_long === false || $subnet_long === false) return false;
    $mask = $bits === '0' ? 0 : (~0 << (32 - (int)$bits));
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

function is_private_ip(string $ip): bool
{
    return (bool) filter_var($ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Atomic IP-based rate limiter using exclusive file locking across the full
 * read-check-write cycle, preventing concurrent bypass.
 * Exits with HTTP 429 if the limit is exceeded.
 */
function rate_limit(string $endpoint, int $max = 5, int $window = 900): void
{
    $ip   = real_ip();
    $file = sys_get_temp_dir() . '/cyberox_rl_' . md5($endpoint . $ip) . '.json';
    $now  = time();

    $fh = fopen($file, 'c+');
    if ($fh === false) return; // can't lock → skip silently rather than blocking everyone

    flock($fh, LOCK_EX);

    $content = '';
    while (!feof($fh)) $content .= fread($fh, 4096);
    $timestamps = json_decode($content, true) ?? [];
    $timestamps = array_values(array_filter($timestamps, fn($t) => $now - $t < $window));

    if (count($timestamps) >= $max) {
        flock($fh, LOCK_UN);
        fclose($fh);
        http_response_code(429);
        header('Retry-After: ' . $window);
        echo json_encode(['ok' => false, 'message' => 'Too many requests. Please wait before trying again.']);
        exit;
    }

    $timestamps[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) json_encode($timestamps));
    flock($fh, LOCK_UN);
    fclose($fh);
}

/**
 * Validates an hCaptcha token against the hCaptcha API.
 * Uses dev pass-through secret by default (always passes on localhost).
 * Set HCAPTCHA_SECRET env var in production.
 */
function verify_hcaptcha(string $token): bool
{
    if ($token === '') return false;

    $secret = getenv('HCAPTCHA_SECRET') ?: '0x0000000000000000000000000000000000000000';
    $payload = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => real_ip()]);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.hcaptcha.com/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $payload,
            'timeout' => 5,
        ]]);
        $response = @file_get_contents('https://api.hcaptcha.com/siteverify', false, $ctx);
    }

    if (!$response) return false;
    $data = json_decode((string) $response, true);
    return ($data['success'] ?? false) === true;
}
