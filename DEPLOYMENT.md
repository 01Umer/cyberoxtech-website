# Cyberox Technologies Website Deployment

## Upload Target

Upload the contents of this folder to the web root for `www.cyberoxtech.com`, usually `public_html/`.

## Server Requirements

- Apache with `.htaccess` enabled
- PHP 8.1 or newer
- PHP `mail()` enabled, or server-level SMTP routing configured for PHP mail
- SSL certificate enabled for `cyberoxtech.com` and `www.cyberoxtech.com`

## Email

The website sends contact, demo, and newsletter requests to:

- `sales@cyberoxtech.com`

Optional environment variables:

- `CYBEROX_SALES_EMAIL`
- `CYBEROX_MAIL_FROM`
- `CYBEROX_MAIL_FROM_NAME`

For reliable delivery, configure DNS records for the domain:

- SPF
- DKIM
- DMARC

## Post-Launch Checks

- Submit `https://www.cyberoxtech.com/sitemap.xml` in Google Search Console.
- Test `https://www.cyberoxtech.com/api/demo-request.php` through the demo form, not directly.
- Confirm messages arrive in `sales@cyberoxtech.com`.
- Verify SSL redirects and `www` redirect behavior.
