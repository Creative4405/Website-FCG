<?php
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$fcgHost = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
$fcgPortalBasePath = strpos($fcgHost, 'portal.fcgcloud.co.za') !== false ? '' : '/portal';
$fcgCookiePath = $fcgPortalBasePath === '' ? '/' : $fcgPortalBasePath;

session_name('fcg_portal_session');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 8 * 3600,
        'path' => $fcgCookiePath,
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(8 * 3600, $fcgCookiePath . '; samesite=Lax', '', $secureCookie, true);
}
session_start();
$sessionIdleLimit = 45 * 60;
if (!empty($_SESSION['fcg_user']) && !empty($_SESSION['fcg_last_activity']) && time() - (int) $_SESSION['fcg_last_activity'] > $sessionIdleLimit) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (!empty($_SESSION['fcg_user'])) {
    $_SESSION['fcg_last_activity'] = time();
}
if (empty($_SESSION['fcg_csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['fcg_csrf_token'] = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['fcg_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $_SESSION['fcg_csrf_token'] = sha1(uniqid('', true) . mt_rand());
    }
}

$basePath = $fcgPortalBasePath;
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
if ($requestPath === '' || $requestPath === false) {
    $requestPath = '/';
}

function fcg_paths($relative)
{
    return [
        '/home/futurec2/fcgportal/' . $relative,
        __DIR__ . '/../../../../fcgportal/' . $relative,
        __DIR__ . '/../fcgportal/' . $relative,
    ];
}

function fcg_first_file($relative)
{
    foreach (fcg_paths($relative) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function fcg_json($payload, $status = 200)
{
    if (is_array($payload) && !isset($payload['csrf_token']) && function_exists('fcg_user') && fcg_user()) {
        $payload['csrf_token'] = fcg_csrf_token();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function fcg_value($array, $key, $default = '')
{
    return is_array($array) && isset($array[$key]) ? $array[$key] : $default;
}

function fcg_business_unit_options()
{
    return ['future_creative_group', 'fcg_cloud', 'both'];
}

function fcg_normalize_business_unit($value, $default = 'future_creative_group')
{
    $unit = strtolower(trim((string) $value));
    if ($unit === 'future creative group' || $unit === 'futurecreativegroup' || $unit === 'fcg') {
        $unit = 'future_creative_group';
    }
    if ($unit === 'fcgcloud' || $unit === 'cloud' || $unit === 'portal.fcgcloud.co.za') {
        $unit = 'fcg_cloud';
    }
    return in_array($unit, fcg_business_unit_options(), true) ? $unit : $default;
}

function fcg_business_unit_label($unit)
{
    $unit = fcg_normalize_business_unit($unit);
    if ($unit === 'fcg_cloud') return 'FCG Cloud';
    if ($unit === 'both') return 'Both Businesses';
    return 'Future Creative Group';
}

function fcg_requested_business_unit()
{
    $host = strtolower(fcg_value($_SERVER, 'HTTP_HOST', ''));
    if (strpos($host, 'portal.fcgcloud.co.za') !== false || strpos($host, 'fcgcloud.co.za') !== false) {
        return 'fcg_cloud';
    }
    $brand = strtolower(trim(fcg_value($_GET, 'brand', '')));
    if (in_array($brand, ['fcgcloud', 'fcg_cloud', 'cloud'], true)) {
        return 'fcg_cloud';
    }
    return 'future_creative_group';
}

function fcg_portal_path($path = '')
{
    global $fcgPortalBasePath;
    $path = '/' . ltrim((string) $path, '/');
    return ($fcgPortalBasePath === '' ? '' : $fcgPortalBasePath) . $path;
}

function fcg_brand_config($unit = 'future_creative_group')
{
    $unit = fcg_normalize_business_unit($unit);
    $future = [
        'business_unit' => 'future_creative_group',
        'label' => 'Future Creative Group',
        'portal_title' => 'Future Creative Group Client Portal',
        'portal_url' => 'https://futurecreativegroup.co.za/portal/',
        'home_url' => 'https://futurecreativegroup.co.za/',
        'logo_url' => fcg_portal_path('/static/logo.png'),
        'favicon_url' => fcg_portal_path('/static/favicon-36x36.png'),
        'intro' => 'Access your dedicated Future Creative Group workspace to manage projects, quotations, invoices, support requests, payments, and important business documents.',
        'footer_note' => 'Future Creative Group Pty Ltd. Innovative Digital Solutions & Security Technology. Secure business records are managed through role-based portal access.',
        'colors' => ['primary' => '#061321', 'secondary' => '#0b1d31', 'accent' => '#28c3f4'],
        'contacts' => [
            'sales' => 'sales@futurecreativegroup.co.za',
            'support' => 'support@futurecreativegroup.co.za',
            'billing' => 'account@futurecreativegroup.co.za',
            'general' => 'info@futurecreativegroup.co.za',
            'phone' => '011 568 0279',
            'facebook' => 'https://www.facebook.com/profile.php?id=61555267733008',
        ],
        'quick_links' => [],
    ];
    if ($unit !== 'fcg_cloud') {
        return $future;
    }
    return [
        'business_unit' => 'fcg_cloud',
        'label' => 'FCG Cloud',
        'portal_title' => 'FCG Cloud Client Portal',
        'portal_url' => 'https://portal.fcgcloud.co.za/',
        'fallback_portal_url' => 'https://futurecreativegroup.co.za/portal/?brand=fcgcloud',
        'home_url' => 'https://fcgcloud.co.za/',
        'logo_url' => fcg_portal_path('/static/fcg-cloud-logo.png'),
        'favicon_url' => fcg_portal_path('/static/fcg-cloud-favicon.png'),
        'intro' => 'Manage your FCG Cloud hosting, business email, domains, invoices, renewals and support requests from one secure workspace.',
        'footer_note' => 'FCG Cloud is the dedicated hosting, business email, domain and cloud services division of Future Creative Group Pty Ltd.',
        'colors' => ['primary' => '#03111f', 'secondary' => '#082238', 'accent' => '#18d0b3'],
        'contacts' => [
            'sales' => 'sales@fcgcloud.co.za',
            'support' => 'support@fcgcloud.co.za',
            'billing' => 'billing@fcgcloud.co.za',
            'general' => 'info@fcgcloud.co.za',
            'phone' => '011 568 0279',
            'facebook' => 'https://www.facebook.com/profile.php?id=61591614040250',
        ],
        'quick_links' => [
            'directadmin' => 'https://panel.fcgcloud.co.za:2222',
            'webmail' => 'https://webmail.fcgcloud.co.za/roundcube',
        ],
    ];
}

function fcg_templates_for_business_unit($templates, $unit = 'future_creative_group')
{
    $unit = fcg_normalize_business_unit($unit);
    if ($unit !== 'fcg_cloud') {
        return $templates;
    }
    $brand = fcg_brand_config('fcg_cloud');
    $cloudTemplates = [
        'default_quote_template' => 'modern-technology',
        'default_invoice_template' => 'modern-technology',
        'prefixes' => [
            'quote' => 'FCC-QUO',
            'invoice' => 'FCC-INV',
            'receipt' => 'FCC-REC',
            'proforma' => 'FCC-PRO',
            'credit_note' => 'FCC-CN',
            'statement' => 'FCC-STA',
        ],
        'company' => [
            'name' => 'FCG Cloud',
            'website' => 'fcgcloud.co.za',
            'email' => $brand['contacts']['general'],
            'phone' => $brand['contacts']['phone'],
            'address' => '32 Mir Street, Makhulong, Tembisa 1632',
            'slogan' => 'Hosting & Cloud Solutions',
            'footer' => 'Hosting, Business Email, Domains and Cloud Services',
            'logo_url' => $brand['logo_url'],
            'logo_path' => '',
            'logo_pdf_path' => '',
        ],
        'terms' => [
            'invoice' => 'Payment is due on or before the due date shown. Please use your invoice number or company name as the payment reference. Hosting, domains, SSL, business email and cloud services may be suspended, paused or delayed if payment is not received and verified.',
            'receipt' => 'Thank you for your FCG Cloud payment. This receipt confirms that payment has been recorded for the referenced hosting, email, domain or cloud service.',
            'statement' => 'This FCG Cloud statement reflects invoices, payments, credits and outstanding balances recorded on your hosting, email, domain or cloud services account.',
            'payment_notes' => 'Please use your FCG Cloud invoice number or company name as your payment reference. Services renew or continue once payment has been received and confirmed.',
        ],
        'smtp' => [
            'from_email' => 'billing@fcgcloud.co.za',
        ],
        'email_templates' => [
            'welcome' => 'Welcome to the FCG Cloud client portal. Manage hosting, business email, domains, invoices, renewals and support at {{portal_url}}.',
            'client_account_created' => 'Your FCG Cloud client portal profile is ready. Sign in at {{portal_url}} using the access details issued to you.',
            'hosting_account_ready' => 'Your FCG Cloud hosting account is ready. DirectAdmin and webmail access details are available in your secure portal.',
            'business_email_ready' => 'Your FCG Cloud business email service is ready. Setup documents and support options are available in your portal.',
            'invoice_created' => 'FCG Cloud invoice {{document_number}} has been issued. Please review the amount, due date and payment notes in your secure portal: {{portal_url}}.',
            'payment_received' => 'We have recorded your FCG Cloud payment against {{document_number}}. The account balance will update after verification.',
            'renewal_reminder' => '{{service_name}} is due for renewal on {{renewal_date}}. Please review the renewal record in your FCG Cloud portal.',
            'payment_overdue' => 'FCG Cloud invoice {{document_number}} is overdue with {{balance_due}} outstanding. Please review your billing workspace at {{portal_url}}.',
            'support_request_received' => 'FCG Cloud support ticket {{ticket_number}} has been received and will be reviewed by support.',
            'support_request_updated' => 'FCG Cloud support ticket {{ticket_number}} has been updated. Sign in to review the response.',
            'domain_renewal_reminder' => 'Your domain renewal is due on {{renewal_date}}. Please review the renewal record in the FCG Cloud portal.',
            'service_suspended' => 'An FCG Cloud service has been suspended. Please review your portal or contact support@fcgcloud.co.za.',
            'service_restored' => 'Your FCG Cloud service has been restored. Please review your portal for the latest status.',
        ],
        'whatsapp_templates' => [
            'welcome' => 'Welcome to FCG Cloud. Your secure client portal is ready: {{portal_url}}',
            'invoice_created' => 'FCG Cloud invoice {{document_number}} is ready: {{secure_url}}',
            'renewal_reminder' => '{{service_name}} renews on {{renewal_date}}. Review it in FCG Cloud: {{portal_url}}',
            'support_update' => 'FCG Cloud support ticket {{ticket_number}} has been updated: {{portal_url}}',
        ],
        'footer_notes' => $brand['footer_note'],
    ];
    $configured = fcg_value(fcg_value($templates, 'business_unit_settings', []), 'fcg_cloud', []);
    return array_replace_recursive($templates, $cloudTemplates, is_array($configured) ? $configured : []);
}

function fcg_directadmin_config()
{
    return [
        'base_url' => trim((string) getenv('FCG_DIRECTADMIN_API_URL')),
        'username_configured' => trim((string) getenv('FCG_DIRECTADMIN_API_USERNAME')) !== '',
        'token_configured' => trim((string) getenv('FCG_DIRECTADMIN_API_TOKEN')) !== '',
        'enabled' => trim((string) getenv('FCG_DIRECTADMIN_API_URL')) !== '' && trim((string) getenv('FCG_DIRECTADMIN_API_TOKEN')) !== '',
    ];
}

function fcg_user_business_unit($user)
{
    return fcg_normalize_business_unit(fcg_value($user, 'business_unit', 'future_creative_group'));
}

function fcg_user_business_units($user)
{
    $unit = fcg_user_business_unit($user);
    return $unit === 'both' ? ['future_creative_group', 'fcg_cloud'] : [$unit];
}

function fcg_user_can_access_business_unit($user, $unit)
{
    if (!$user) return false;
    if (fcg_value($user, 'role', '') === 'admin') return true;
    $unit = fcg_normalize_business_unit($unit);
    return in_array($unit, fcg_user_business_units($user), true);
}

function fcg_active_business_unit($user = null)
{
    if (!$user) {
        $user = fcg_user();
    }
    $requested = fcg_requested_business_unit();
    if (!$user) {
        return $requested;
    }
    $sessionUnit = fcg_normalize_business_unit(fcg_value($_SESSION, 'fcg_active_business_unit', ''), '');
    if ($sessionUnit && fcg_user_can_access_business_unit($user, $sessionUnit)) {
        return $sessionUnit;
    }
    if (fcg_user_can_access_business_unit($user, $requested)) {
        $_SESSION['fcg_active_business_unit'] = $requested;
        return $requested;
    }
    $units = fcg_user_business_units($user);
    $_SESSION['fcg_active_business_unit'] = $units[0];
    return $units[0];
}

function fcg_record_business_unit($record, $default = 'future_creative_group')
{
    return fcg_normalize_business_unit(fcg_value($record, 'business_unit', $default));
}

function fcg_record_matches_business_unit($record, $unit)
{
    $unit = fcg_normalize_business_unit($unit);
    $recordUnit = fcg_record_business_unit($record);
    return $recordUnit === 'both' || $recordUnit === $unit;
}

function fcg_business_unit_from_input($input, $existing = null, $user = null)
{
    $default = $existing ? fcg_record_business_unit($existing) : fcg_active_business_unit($user);
    $unit = fcg_normalize_business_unit(fcg_value($input, 'business_unit', $default), $default);
    if ($user && fcg_value($user, 'role', '') !== 'admin' && !fcg_user_can_access_business_unit($user, $unit)) {
        fcg_json(['error' => 'This account is not authorised for the selected business workspace'], 403);
    }
    return $unit;
}

function fcg_request_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : $_POST;
}

function fcg_csrf_token()
{
    return isset($_SESSION['fcg_csrf_token']) ? $_SESSION['fcg_csrf_token'] : '';
}

function fcg_require_csrf()
{
    $token = isset($_SERVER['HTTP_X_FCG_CSRF']) ? $_SERVER['HTTP_X_FCG_CSRF'] : '';
    if ($token === '' || !hash_equals(fcg_csrf_token(), $token)) {
        fcg_json(['error' => 'Security token expired. Please refresh and sign in again.'], 419);
    }
}

function fcg_users_file()
{
    return fcg_first_file('data/portal-users.json');
}

function fcg_writable_data_file($name)
{
    foreach (fcg_paths('data') as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return rtrim($dir, '/') . '/' . $name;
        }
    }
    return __DIR__ . '/../fcgportal/data/' . $name;
}

function fcg_data_dir()
{
    foreach (fcg_paths('data') as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return rtrim($dir, '/');
        }
    }
    $fallback = __DIR__ . '/../fcgportal/data';
    if (!is_dir($fallback)) {
        mkdir($fallback, 0755, true);
    }
    return $fallback;
}

function fcg_login_attempts_file()
{
    return fcg_writable_data_file('login-attempts.json');
}

function fcg_login_attempt_key($email)
{
    $ip = fcg_value($_SERVER, 'REMOTE_ADDR', 'unknown');
    return hash('sha256', strtolower(trim((string) $email)) . '|' . $ip);
}

function fcg_login_attempts($email)
{
    $file = fcg_login_attempts_file();
    $records = [];
    if (is_file($file)) {
        $decoded = json_decode(file_get_contents($file), true);
        $records = is_array($decoded) ? $decoded : [];
    }
    $key = fcg_login_attempt_key($email);
    $cutoff = time() - 15 * 60;
    $attempts = array_values(array_filter(fcg_value($records, $key, []), function ($timestamp) use ($cutoff) {
        return (int) $timestamp >= $cutoff;
    }));
    return [$records, $key, $attempts];
}

function fcg_login_rate_check($email)
{
    list($records, $key, $attempts) = fcg_login_attempts($email);
    if (count($attempts) >= 5) {
        $retryAfter = max(1, 15 * 60 - (time() - (int) min($attempts)));
        header('Retry-After: ' . $retryAfter);
        fcg_json(['error' => 'Too many unsuccessful sign-in attempts. Please wait before trying again.'], 429);
    }
}

function fcg_record_login_attempt($email, $successful)
{
    list($records, $key, $attempts) = fcg_login_attempts($email);
    if ($successful) {
        unset($records[$key]);
    } else {
        $attempts[] = time();
        $records[$key] = array_slice($attempts, -5);
        if (count($attempts) === 5) {
            fcg_send_notification('Portal security warning: repeated sign-in failures', [
                'Five unsuccessful portal sign-in attempts were recorded.',
                'Email: ' . strtolower(trim((string) $email)),
                'IP: ' . fcg_value($_SERVER, 'REMOTE_ADDR', 'unknown'),
                'Time: ' . date('c'),
            ], '', 'support@futurecreativegroup.co.za');
        }
    }
    @file_put_contents(fcg_login_attempts_file(), json_encode($records, JSON_PRETTY_PRINT) . PHP_EOL, LOCK_EX);
}

function fcg_load_users()
{
    $file = fcg_users_file();
    if (!$file) {
        return [];
    }

    $decoded = json_decode(file_get_contents($file), true);
    if (!isset($decoded['users']) || !is_array($decoded['users'])) {
        return [];
    }

    foreach ($decoded['users'] as &$user) {
        if (fcg_value($user, 'name', '') === 'Client Portal Preview') $user['active'] = false;
        $user['business_unit'] = fcg_normalize_business_unit(fcg_value($user, 'business_unit', 'future_creative_group'));
    }
    unset($user);
    return $decoded['users'];
}

function fcg_save_users($users)
{
    $file = fcg_users_file() ?: fcg_writable_data_file('portal-users.json');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload = [
        'updated_at' => date('c'),
        'users' => array_values(array_map(function ($user) {
            $user['business_unit'] = fcg_normalize_business_unit(fcg_value($user, 'business_unit', 'future_creative_group'));
            return $user;
        }, $users)),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
        fcg_json(['error' => 'Could not save the client account file'], 500);
    }
}

function fcg_public_user($user)
{
    $displayName = fcg_value($user, 'name', 'Client Workspace');
    if ($displayName === 'Client Portal Preview') {
        $displayName = 'Client Workspace';
    }
    return [
        'id' => (int) fcg_value($user, 'id', 0),
        'name' => $displayName,
        'email' => fcg_value($user, 'email', ''),
        'role' => fcg_value($user, 'role', 'client'),
        'business_unit' => fcg_user_business_unit($user),
        'business_units' => fcg_user_business_units($user),
        'company' => fcg_value($user, 'company', ''),
        'phone' => fcg_value($user, 'phone', ''),
        'address' => fcg_value($user, 'address', ''),
        'vat_number' => fcg_value($user, 'vat_number', ''),
        'notification_preference' => fcg_value($user, 'notification_preference', 'Email'),
        'notification_consent' => fcg_bool_value(fcg_value($user, 'notification_consent', true)),
        'auth_provider' => fcg_value($user, 'auth_provider', 'access_code'),
        'last_login_at' => fcg_value($user, 'last_login_at', ''),
        'google_linked' => trim((string) fcg_value($user, 'google_id', '')) !== '',
        'apple_linked' => trim((string) fcg_value($user, 'apple_id', '')) !== '',
    ];
}

function fcg_find_user($email)
{
    foreach (fcg_load_users() as $user) {
        if (!empty($user['active']) && strtolower(fcg_value($user, 'email', '')) === $email) {
            return $user;
        }
    }

    return null;
}

function fcg_user()
{
    return isset($_SESSION['fcg_user']) && is_array($_SESSION['fcg_user']) ? $_SESSION['fcg_user'] : null;
}

function fcg_require_user()
{
    $user = fcg_user();
    if (!$user) {
        fcg_json(['error' => 'Authentication required'], 401);
    }
    $_SESSION['fcg_last_activity'] = time();
    return $user;
}

function fcg_require_admin()
{
    $user = fcg_require_user();
    if (fcg_value($user, 'role', '') !== 'admin') {
        fcg_json(['error' => 'Admin access required'], 403);
    }
    return $user;
}

function fcg_random_bytes($length)
{
    if (function_exists('random_bytes')) {
        return random_bytes($length);
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($bytes !== false && $strong) {
            return $bytes;
        }
    }
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }
    return $bytes;
}

function fcg_generate_access_code()
{
    return rtrim(strtr(base64_encode(fcg_random_bytes(18)), '+/', '-_'), '=');
}

function fcg_env($key, $default = '')
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (string) $value;
}

function fcg_base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function fcg_base64url_decode($value)
{
    $value = strtr((string) $value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding) {
        $value .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($value, true);
    return $decoded === false ? '' : $decoded;
}

function fcg_absolute_portal_url($path)
{
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || fcg_value($_SERVER, 'HTTP_X_FORWARDED_PROTO', '') === 'https') ? 'https' : 'http';
    $host = fcg_value($_SERVER, 'HTTP_HOST', 'futurecreativegroup.co.za');
    return $scheme . '://' . $host . fcg_portal_path($path);
}

function fcg_oauth_redirect_uri($provider)
{
    $provider = strtolower((string) $provider);
    $envKey = $provider === 'apple' ? 'APPLE_REDIRECT_URI' : 'GOOGLE_REDIRECT_URI';
    $configured = trim(fcg_env($envKey));
    if ($configured !== '') {
        return $configured;
    }
    return fcg_absolute_portal_url('/auth/' . $provider . '/callback');
}

function fcg_oauth_config($provider)
{
    $provider = strtolower((string) $provider);
    if ($provider === 'google') {
        return [
            'client_id' => trim(fcg_env('GOOGLE_CLIENT_ID')),
            'client_secret' => trim(fcg_env('GOOGLE_CLIENT_SECRET')),
            'redirect_uri' => fcg_oauth_redirect_uri('google'),
        ];
    }
    if ($provider === 'apple') {
        return [
            'client_id' => trim(fcg_env('APPLE_CLIENT_ID')),
            'team_id' => trim(fcg_env('APPLE_TEAM_ID')),
            'key_id' => trim(fcg_env('APPLE_KEY_ID')),
            'private_key' => trim(str_replace('\\n', "\n", fcg_env('APPLE_PRIVATE_KEY'))),
            'client_secret' => trim(fcg_env('APPLE_CLIENT_SECRET')),
            'redirect_uri' => fcg_oauth_redirect_uri('apple'),
        ];
    }
    return [];
}

function fcg_oauth_configured($provider)
{
    $config = fcg_oauth_config($provider);
    if ($provider === 'google') {
        return $config['client_id'] !== '' && $config['client_secret'] !== '' && $config['redirect_uri'] !== '';
    }
    if ($provider === 'apple') {
        $hasSecret = $config['client_secret'] !== ''
            || ($config['team_id'] !== '' && $config['key_id'] !== '' && $config['private_key'] !== '');
        return $config['client_id'] !== '' && $config['redirect_uri'] !== '' && $hasSecret;
    }
    return false;
}

function fcg_oauth_public_config()
{
    return [
        'google' => fcg_oauth_configured('google'),
        'apple' => fcg_oauth_configured('apple'),
    ];
}

function fcg_http_json($url, $method = 'GET', $fields = null)
{
    $headers = ['Accept: application/json'];
    $options = [
        'method' => $method,
        'timeout' => 14,
        'ignore_errors' => true,
    ];
    if ($fields !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['content'] = http_build_query($fields);
    }
    $options['header'] = implode("\r\n", $headers);
    $response = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    if ($response === false) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    return [$decoded, ''];
}

function fcg_asn1_length($length)
{
    if ($length < 128) {
        return chr($length);
    }
    $bytes = '';
    while ($length > 0) {
        $bytes = chr($length & 0xff) . $bytes;
        $length >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function fcg_asn1_read_length($data, &$offset)
{
    if ($offset >= strlen($data)) {
        return 0;
    }
    $length = ord($data[$offset++]);
    if (($length & 0x80) === 0) {
        return $length;
    }
    $count = $length & 0x7f;
    $length = 0;
    for ($i = 0; $i < $count && $offset < strlen($data); $i++) {
        $length = ($length << 8) | ord($data[$offset++]);
    }
    return $length;
}

function fcg_jwk_to_pem($jwk)
{
    if (!isset($jwk['n'], $jwk['e'])) {
        return '';
    }
    $modulus = fcg_base64url_decode($jwk['n']);
    $exponent = fcg_base64url_decode($jwk['e']);
    if ($modulus === '' || $exponent === '') {
        return '';
    }
    if (ord($modulus[0]) > 0x7f) {
        $modulus = "\x00" . $modulus;
    }
    if (ord($exponent[0]) > 0x7f) {
        $exponent = "\x00" . $exponent;
    }
    $rsaPublicKey = "\x30" . fcg_asn1_length(strlen("\x02" . fcg_asn1_length(strlen($modulus)) . $modulus . "\x02" . fcg_asn1_length(strlen($exponent)) . $exponent))
        . "\x02" . fcg_asn1_length(strlen($modulus)) . $modulus
        . "\x02" . fcg_asn1_length(strlen($exponent)) . $exponent;
    $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $bitString = "\x03" . fcg_asn1_length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
    $spki = "\x30" . fcg_asn1_length(strlen($algorithm . $bitString)) . $algorithm . $bitString;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function fcg_verify_oauth_jwt($jwt, $jwksUrl, $audience, $issuers, $nonce = '')
{
    $parts = explode('.', (string) $jwt);
    if (count($parts) !== 3) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $header = json_decode(fcg_base64url_decode($parts[0]), true);
    $payload = json_decode(fcg_base64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($payload) || fcg_value($header, 'alg', '') !== 'RS256') {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    list($jwks, $error) = fcg_http_json($jwksUrl);
    if ($error || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $pem = '';
    foreach ($jwks['keys'] as $key) {
        if (fcg_value($key, 'kid', '') === fcg_value($header, 'kid', '')) {
            $pem = fcg_jwk_to_pem($key);
            break;
        }
    }
    if ($pem === '' || !function_exists('openssl_verify')) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $valid = openssl_verify($parts[0] . '.' . $parts[1], fcg_base64url_decode($parts[2]), $pem, OPENSSL_ALGO_SHA256);
    if ($valid !== 1) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $now = time();
    if ((int) fcg_value($payload, 'exp', 0) < $now - 300 || (int) fcg_value($payload, 'iat', $now) > $now + 300) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $acceptedIssuers = is_array($issuers) ? $issuers : [$issuers];
    if (!in_array(fcg_value($payload, 'iss', ''), $acceptedIssuers, true)) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $aud = fcg_value($payload, 'aud', '');
    $audiences = is_array($aud) ? $aud : [$aud];
    if (!in_array($audience, $audiences, true)) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    if ($nonce !== '' && isset($payload['nonce']) && !hash_equals($nonce, (string) $payload['nonce'])) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    return [$payload, ''];
}

function fcg_ecdsa_der_to_jose($der, $partLength = 32)
{
    $offset = 0;
    if ($der === '' || ord($der[$offset++]) !== 0x30) {
        return '';
    }
    fcg_asn1_read_length($der, $offset);
    if ($offset >= strlen($der) || ord($der[$offset++]) !== 0x02) {
        return '';
    }
    $rLength = fcg_asn1_read_length($der, $offset);
    $r = substr($der, $offset, $rLength);
    $offset += $rLength;
    if ($offset >= strlen($der) || ord($der[$offset++]) !== 0x02) {
        return '';
    }
    $sLength = fcg_asn1_read_length($der, $offset);
    $s = substr($der, $offset, $sLength);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    if (strlen($r) > $partLength || strlen($s) > $partLength) {
        return '';
    }
    return str_pad($r, $partLength, "\x00", STR_PAD_LEFT) . str_pad($s, $partLength, "\x00", STR_PAD_LEFT);
}

function fcg_apple_client_secret($config)
{
    if (fcg_value($config, 'client_secret', '') !== '') {
        return $config['client_secret'];
    }
    if (!function_exists('openssl_sign')) {
        return '';
    }
    $header = ['alg' => 'ES256', 'kid' => $config['key_id']];
    $payload = [
        'iss' => $config['team_id'],
        'iat' => time(),
        'exp' => time() + 3600,
        'aud' => 'https://appleid.apple.com',
        'sub' => $config['client_id'],
    ];
    $unsigned = fcg_base64url_encode(json_encode($header)) . '.' . fcg_base64url_encode(json_encode($payload));
    $privateKey = openssl_pkey_get_private($config['private_key']);
    if (!$privateKey || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $joseSignature = fcg_ecdsa_der_to_jose($signature, 32);
    return $joseSignature === '' ? '' : $unsigned . '.' . fcg_base64url_encode($joseSignature);
}

function fcg_oauth_email_verified($claims)
{
    $value = fcg_value($claims, 'email_verified', false);
    return $value === true || $value === 1 || $value === '1' || strtolower((string) $value) === 'true';
}

function fcg_oauth_unknown_message($provider)
{
    return $provider === 'apple'
        ? 'Your Apple account is not linked to an active Future Creative Group portal account. Please request portal access.'
        : 'Your Google account is not linked to an active Future Creative Group portal account. Please request portal access.';
}

function fcg_link_oauth_user($provider, $claims)
{
    $provider = strtolower((string) $provider);
    $email = strtolower(trim(fcg_value($claims, 'email', '')));
    $subject = trim(fcg_value($claims, 'sub', ''));
    if ($subject === '') {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    if (!fcg_oauth_email_verified($claims)) {
        return [null, 'Please use a verified email address to access the portal.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [null, fcg_oauth_unknown_message($provider)];
    }
    $users = fcg_load_users();
    $idKey = $provider === 'apple' ? 'apple_id' : 'google_id';
    $matchIndex = -1;
    foreach ($users as $index => $user) {
        if (hash_equals((string) fcg_value($user, $idKey, ''), $subject)) {
            $matchIndex = $index;
            break;
        }
    }
    if ($matchIndex < 0) {
        foreach ($users as $index => $user) {
            if (strtolower(fcg_value($user, 'email', '')) === $email) {
                $matchIndex = $index;
                break;
            }
        }
    }
    if ($matchIndex < 0) {
        return [null, fcg_oauth_unknown_message($provider)];
    }
    if (empty($users[$matchIndex]['active'])) {
        return [null, 'Your portal account is currently inactive. Please contact Future Creative Group support.'];
    }
    $existingProviderId = trim((string) fcg_value($users[$matchIndex], $idKey, ''));
    if ($existingProviderId !== '' && !hash_equals($existingProviderId, $subject)) {
        return [null, 'We could not complete the sign-in request. Please try again or use your email and access code.'];
    }
    $users[$matchIndex][$idKey] = $subject;
    $users[$matchIndex]['auth_provider'] = $provider;
    $users[$matchIndex]['last_login_at'] = date('c');
    $users[$matchIndex][$provider . '_linked_at'] = fcg_value($users[$matchIndex], $provider . '_linked_at', date('c'));
    $users[$matchIndex]['oauth_email_verified_at'] = date('c');
    fcg_save_users($users);
    return [$users[$matchIndex], ''];
}

function fcg_update_login_metadata($account, $provider)
{
    $users = fcg_load_users();
    foreach ($users as &$user) {
        if ((int) fcg_value($user, 'id', 0) === (int) fcg_value($account, 'id', 0)) {
            $user['auth_provider'] = $provider;
            $user['last_login_at'] = date('c');
            break;
        }
    }
    unset($user);
    if ($users) {
        fcg_save_users($users);
    }
}

function fcg_start_portal_session($account, $provider = 'access_code', &$error = '')
{
    session_regenerate_id(true);
    fcg_update_login_metadata($account, $provider);
    $account['auth_provider'] = $provider;
    $account['last_login_at'] = date('c');
    $user = fcg_public_user($account);
    $requestedUnit = fcg_requested_business_unit();
    if ($requestedUnit === 'fcg_cloud' && !fcg_user_can_access_business_unit($user, 'fcg_cloud')) {
        $error = 'This portal account is not assigned to the FCG Cloud workspace.';
        return null;
    }
    $availableUnits = fcg_user_business_units($user);
    $_SESSION['fcg_active_business_unit'] = fcg_user_can_access_business_unit($user, $requestedUnit) ? $requestedUnit : $availableUnits[0];
    $_SESSION['fcg_user'] = $user;
    $_SESSION['fcg_last_activity'] = time();
    $_SESSION['fcg_csrf_token'] = bin2hex(fcg_random_bytes(32));
    $portalData = fcg_load_portal_data();
    $actor = fcg_value($user, 'role', '') === 'admin' ? 'Admin' : 'Client';
    fcg_log_activity($portalData, 'login', $actor . ' signed in using ' . str_replace('_', ' ', $provider), fcg_value($user, 'id', 0), $_SESSION['fcg_active_business_unit']);
    fcg_save_portal_data($portalData);
    return $user;
}

function fcg_oauth_error_redirect($message)
{
    header('Location: ' . fcg_portal_path('/?auth_error=' . rawurlencode($message)), true, 302);
    exit;
}

function fcg_oauth_start($provider)
{
    if (!fcg_oauth_configured($provider)) {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    $provider = strtolower((string) $provider);
    $config = fcg_oauth_config($provider);
    $state = bin2hex(fcg_random_bytes(24));
    $nonce = bin2hex(fcg_random_bytes(18));
    $_SESSION['fcg_oauth_state_' . $provider] = $state;
    $_SESSION['fcg_oauth_nonce_' . $provider] = $nonce;
    if ($provider === 'google') {
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
            'access_type' => 'online',
        ];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params), true, 302);
        exit;
    }
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'email',
        'response_mode' => 'form_post',
        'state' => $state,
        'nonce' => $nonce,
    ];
    header('Location: https://appleid.apple.com/auth/authorize?' . http_build_query($params), true, 302);
    exit;
}

function fcg_oauth_callback_google()
{
    $state = fcg_value($_GET, 'state', '');
    $code = fcg_value($_GET, 'code', '');
    if (fcg_value($_GET, 'error', '') !== '') {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    if ($state === '' || $code === '' || !hash_equals(fcg_value($_SESSION, 'fcg_oauth_state_google', ''), $state)) {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    $nonce = fcg_value($_SESSION, 'fcg_oauth_nonce_google', '');
    unset($_SESSION['fcg_oauth_state_google'], $_SESSION['fcg_oauth_nonce_google']);
    $config = fcg_oauth_config('google');
    list($token, $tokenError) = fcg_http_json('https://oauth2.googleapis.com/token', 'POST', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
    if ($tokenError || empty($token['id_token'])) {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    list($claims, $verifyError) = fcg_verify_oauth_jwt($token['id_token'], 'https://www.googleapis.com/oauth2/v3/certs', $config['client_id'], ['accounts.google.com', 'https://accounts.google.com'], $nonce);
    if ($verifyError) {
        fcg_oauth_error_redirect($verifyError);
    }
    list($account, $accountError) = fcg_link_oauth_user('google', $claims);
    if ($accountError) {
        fcg_oauth_error_redirect($accountError);
    }
    $sessionError = '';
    if (!fcg_start_portal_session($account, 'google', $sessionError)) {
        fcg_oauth_error_redirect($sessionError ?: 'We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    header('Location: ' . fcg_portal_path('/'), true, 302);
    exit;
}

function fcg_oauth_callback_apple()
{
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $state = fcg_value($input, 'state', '');
    $code = fcg_value($input, 'code', '');
    if (fcg_value($input, 'error', '') !== '') {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    if ($state === '' || $code === '' || !hash_equals(fcg_value($_SESSION, 'fcg_oauth_state_apple', ''), $state)) {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    $nonce = fcg_value($_SESSION, 'fcg_oauth_nonce_apple', '');
    unset($_SESSION['fcg_oauth_state_apple'], $_SESSION['fcg_oauth_nonce_apple']);
    $config = fcg_oauth_config('apple');
    $clientSecret = fcg_apple_client_secret($config);
    if ($clientSecret === '') {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    list($token, $tokenError) = fcg_http_json('https://appleid.apple.com/auth/token', 'POST', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $clientSecret,
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
    $idToken = fcg_value($token ?: [], 'id_token', fcg_value($input, 'id_token', ''));
    if ($tokenError || $idToken === '') {
        fcg_oauth_error_redirect('We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    list($claims, $verifyError) = fcg_verify_oauth_jwt($idToken, 'https://appleid.apple.com/auth/keys', $config['client_id'], ['https://appleid.apple.com'], $nonce);
    if ($verifyError) {
        fcg_oauth_error_redirect($verifyError);
    }
    list($account, $accountError) = fcg_link_oauth_user('apple', $claims);
    if ($accountError) {
        fcg_oauth_error_redirect($accountError);
    }
    $sessionError = '';
    if (!fcg_start_portal_session($account, 'apple', $sessionError)) {
        fcg_oauth_error_redirect($sessionError ?: 'We could not complete the sign-in request. Please try again or use your email and access code.');
    }
    header('Location: ' . fcg_portal_path('/'), true, 302);
    exit;
}

function fcg_next_user_id($users)
{
    $max = 0;
    foreach ($users as $user) {
        $max = max($max, (int) fcg_value($user, 'id', 0));
    }
    return $max + 1;
}

function fcg_client_summary($user)
{
    $displayName = fcg_value($user, 'name', 'Client Workspace');
    if ($displayName === 'Client Portal Preview') {
        $displayName = 'Client Workspace';
    }
    return [
        'id' => (int) fcg_value($user, 'id', 0),
        'name' => $displayName,
        'email' => fcg_value($user, 'email', ''),
        'company' => fcg_value($user, 'company', ''),
        'phone' => fcg_value($user, 'phone', ''),
        'address' => fcg_value($user, 'address', ''),
        'vat_number' => fcg_value($user, 'vat_number', ''),
        'role' => fcg_value($user, 'role', 'client'),
        'business_unit' => fcg_user_business_unit($user),
        'business_units' => fcg_user_business_units($user),
        'notification_preference' => fcg_value($user, 'notification_preference', 'Email'),
        'notification_consent' => fcg_bool_value(fcg_value($user, 'notification_consent', true)),
        'active' => !empty($user['active']),
        'created_at' => fcg_value($user, 'created_at', ''),
        'auth_provider' => fcg_value($user, 'auth_provider', 'access_code'),
        'last_login_at' => fcg_value($user, 'last_login_at', ''),
        'google_linked' => trim((string) fcg_value($user, 'google_id', '')) !== '',
        'apple_linked' => trim((string) fcg_value($user, 'apple_id', '')) !== '',
    ];
}

function fcg_admin_templates($templates)
{
    $safe = $templates;
    if (!isset($safe['smtp']) || !is_array($safe['smtp'])) $safe['smtp'] = [];
    $safe['smtp']['password_configured'] = trim(fcg_value($safe['smtp'], 'password', '')) !== '';
    $safe['smtp']['password'] = '';
    return $safe;
}

function fcg_admin_clients_payload()
{
    $clients = fcg_client_accounts();
    $data = fcg_load_portal_data();
    $invoiceStats = fcg_invoice_stats($data['invoices']);
    $quoteStats = fcg_quote_stats($data['quotes']);
    $analytics = fcg_portal_analytics($data, $clients);
    $paymentsToReview = fcg_pending_payments($data['invoices']);

    return [
        'business_units' => [
            'future_creative_group' => fcg_brand_config('future_creative_group'),
            'fcg_cloud' => fcg_brand_config('fcg_cloud'),
        ],
        'active_business_unit' => fcg_active_business_unit(fcg_user()),
        'brand' => fcg_brand_config(fcg_active_business_unit(fcg_user())),
        'clients' => $clients,
        'projects' => array_values($data['projects']),
        'quotes' => array_map('fcg_normalize_quote', array_values($data['quotes'])),
        'invoices' => array_map(function ($invoice) {
            return fcg_public_invoice(fcg_normalize_invoice($invoice));
        }, array_values($data['invoices'])),
        'documents' => array_map('fcg_public_document', array_values($data['documents'])),
        'tickets' => array_map('fcg_public_ticket', array_values($data['tickets'])),
        'services' => array_values($data['services']),
        'templates' => fcg_admin_templates($data['templates']),
        'onboarding' => array_map('fcg_public_onboarding', array_values($data['onboarding'])),
        'subscriptions' => array_values($data['subscriptions']),
        'hosting_records' => array_values($data['hosting_records']),
        'job_cards' => array_map('fcg_public_job_card', array_values($data['job_cards'])),
        'feedback' => array_values($data['feedback']),
        'guest_customers' => array_values($data['guest_customers']),
        'proformas' => array_map('fcg_normalize_invoice', array_values($data['proformas'])),
        'credit_notes' => array_map('fcg_normalize_invoice', array_values($data['credit_notes'])),
        'statements' => array_map('fcg_normalize_invoice', array_values($data['statements'])),
        'notifications' => array_slice(array_values($data['notifications']), 0, 250),
        'internal_notes' => array_slice(array_values($data['internal_notes']), 0, 250),
        'reminder_history' => array_slice(array_values($data['reminder_history']), 0, 250),
        'email_history' => array_slice(array_values($data['email_history']), 0, 100),
        'document_links' => array_values($data['document_links']),
        'document_access' => array_slice(array_values($data['document_access']), 0, 100),
        'payments_to_review' => $paymentsToReview,
        'analytics' => $analytics,
        'activity' => array_slice(array_values($data['activity']), 0, 30),
        'stats' => [
            'total_clients' => count($clients),
            'active_clients' => count(array_filter($clients, function ($client) {
                return !empty($client['active']);
            })),
            'active_projects' => count($data['projects']),
            'total_revenue' => $invoiceStats['total_revenue'],
            'paid_invoices' => $invoiceStats['paid_invoices'],
            'unpaid_invoices' => $invoiceStats['unpaid_invoices'],
            'partial_invoices' => $invoiceStats['partial_invoices'],
            'pending_quotations' => $quoteStats['pending_quotations'],
            'approved_quotations' => $quoteStats['approved_quotations'],
            'open_tickets' => count(array_filter($data['tickets'], function ($ticket) {
                return !in_array(fcg_value($ticket, 'status', 'Open'), ['Resolved', 'Closed'], true);
            })),
            'documents' => count($data['documents']),
            'overdue_invoices' => $analytics['overdue_invoices'],
            'active_subscriptions' => count(array_filter($data['subscriptions'], function ($subscription) {
                return fcg_value($subscription, 'status', 'Active') === 'Active';
            })),
            'guest_customers' => count($data['guest_customers']),
            'total_quotations' => count($data['quotes']),
            'total_invoices' => count($data['invoices']),
            'payment_pending_review' => count($paymentsToReview),
            'outstanding_balance' => fcg_money_format(array_reduce($data['invoices'], function ($total, $invoice) {
                return $total + fcg_money_value(fcg_value(fcg_normalize_invoice($invoice), 'balance_due', 0));
            }, 0)),
            'unread_notifications' => count(array_filter($data['notifications'], function ($item) {
                return empty($item['read_at']);
            })),
            'proformas' => count($data['proformas']),
            'credit_notes' => count($data['credit_notes']),
        ],
    ];
}

function fcg_client_accounts()
{
    $clients = [];
    foreach (fcg_load_users() as $user) {
        if (fcg_value($user, 'role', 'client') === 'client') {
            $clients[] = fcg_client_summary($user);
        }
    }
    return $clients;
}

function fcg_fcg_cloud_service_catalogue()
{
    $now = date('c');
    $services = [
        ['Starter Hosting', 'Website hosting starter package.', '150.00', 'Hosting'],
        ['Business Hosting', 'Website hosting package for growing businesses.', '260.00', 'Hosting'],
        ['Premium Hosting', 'Premium website hosting package with increased resources.', '480.00', 'Hosting'],
        ['E-Commerce Hosting', 'Hosting package for online stores and commerce websites.', '750.00', 'Hosting'],
        ['Starter Email', 'Business email starter package.', '95.00', 'Business Email'],
        ['Business Email', 'Business email package for small teams.', '199.00', 'Business Email'],
        ['Professional Email', 'Professional business email package for growing teams.', '350.00', 'Business Email'],
        ['Corporate Email', 'Corporate business email package for departments and teams.', '599.00', 'Business Email'],
        ['Domain Registration', 'Register a new business domain.', '0.00', 'Domains'],
        ['Domain Renewal', 'Renew an existing domain registration.', '0.00', 'Domains'],
        ['Domain Transfer', 'Transfer a domain into FCG Cloud management.', '0.00', 'Domains'],
        ['Website Migration', 'Move an existing website to FCG Cloud hosting.', '0.00', 'Migration'],
        ['Email Migration', 'Move existing email accounts to FCG Cloud email hosting.', '0.00', 'Migration'],
        ['DNS Configuration', 'Configure DNS records, nameservers and routing.', '0.00', 'DNS'],
        ['SSL Assistance', 'SSL certificate setup, renewal or troubleshooting.', '0.00', 'SSL'],
        ['Outlook Setup', 'Outlook desktop or Microsoft 365 profile setup guidance.', '0.00', 'Email Setup'],
        ['Mobile Email Setup', 'Mobile device business email setup guidance.', '0.00', 'Email Setup'],
        ['Hosting Support', 'Hosting, control panel, webmail or server support.', '0.00', 'Support'],
    ];
    return array_map(function ($item, $index) use ($now) {
        return [
            'id' => 1000 + $index + 1,
            'name' => $item[0],
            'description' => $item[1],
            'default_price' => $item[2],
            'category' => $item[3],
            'vat' => false,
            'active' => true,
            'business_unit' => 'fcg_cloud',
            'created_at' => $now,
        ];
    }, $services, array_keys($services));
}

function fcg_apply_business_unit_defaults(&$data)
{
    $collections = ['projects', 'quotes', 'invoices', 'documents', 'tickets', 'services', 'activity', 'onboarding', 'subscriptions', 'hosting_records', 'job_cards', 'feedback', 'guest_customers', 'email_history', 'document_links', 'document_access', 'proformas', 'credit_notes', 'statements', 'notifications', 'internal_notes', 'reminder_history'];
    foreach ($collections as $collection) {
        if (!isset($data[$collection]) || !is_array($data[$collection])) continue;
        foreach ($data[$collection] as &$record) {
            $default = 'future_creative_group';
            if ($collection === 'services' && stripos(fcg_value($record, 'name', ''), 'Hosting') !== false) {
                $default = fcg_value($record, 'category', '') === 'Hosting' ? 'fcg_cloud' : 'future_creative_group';
            }
            $record['business_unit'] = fcg_normalize_business_unit(fcg_value($record, 'business_unit', $default));
        }
        unset($record);
    }
    if (!isset($data['portal_settings']) || !is_array($data['portal_settings'])) $data['portal_settings'] = [];
    $data['portal_settings']['business_units'] = [
        'future_creative_group' => fcg_brand_config('future_creative_group'),
        'fcg_cloud' => fcg_brand_config('fcg_cloud'),
    ];
    $existingNames = [];
    foreach (fcg_value($data, 'services', []) as $service) {
        $existingNames[strtolower(trim(fcg_value($service, 'name', '')))] = true;
    }
    foreach (fcg_fcg_cloud_service_catalogue() as $service) {
        if (!isset($existingNames[strtolower($service['name'])])) {
            $data['services'][] = $service;
        }
    }
}

function fcg_portal_data_seed()
{
    $seed = [
        'projects' => [
            [
                'id' => 1,
                'client_id' => 2,
                'title' => 'CCTV & Network Upgrade',
                'description' => 'Site assessment, structured network points, CCTV installation and handover documentation.',
                'status' => 'On schedule',
                'progress' => 68,
                'next_step' => 'Confirm handover date and final user training.',
                'updated_at' => date('c'),
            ],
            [
                'id' => 2,
                'client_id' => 2,
                'title' => 'Business Website Refresh',
                'description' => 'Content review, mobile polish, SEO and final publishing checks.',
                'status' => 'In progress',
                'progress' => 42,
                'next_step' => 'Complete mobile QA and publish checklist.',
                'updated_at' => date('c'),
            ],
        ],
        'quotes' => [
            [
                'id' => 1,
                'client_id' => 2,
                'quote_no' => 'FCG-Q-1042',
                'title' => 'Website care plan and hosting support',
                'amount' => 'R2,850',
                'status' => 'Sent',
                'updated_at' => date('c'),
            ],
            [
                'id' => 2,
                'client_id' => 2,
                'quote_no' => 'FCG-Q-1038',
                'title' => 'CCTV maintenance and WiFi audit',
                'amount' => 'R7,420',
                'status' => 'Review',
                'updated_at' => date('c'),
            ],
            [
                'id' => 3,
                'client_id' => 2,
                'quote_no' => 'FCG-Q-1027',
                'title' => 'Access control and network installation',
                'amount' => 'R18,900',
                'status' => 'Approved',
                'updated_at' => date('c'),
            ],
        ],
        'documents' => [
            [
                'id' => 1,
                'client_id' => 2,
                'title' => 'Future Creative Group Company Profile',
                'description' => 'PDF company profile for clients and procurement teams.',
                'status' => 'PDF',
                'url' => '/Future_Creative_Group_Company_Profile.pdf',
                'file_path' => '',
                'file_name' => '',
                'mime_type' => '',
                'created_at' => date('c'),
            ],
            [
                'id' => 2,
                'client_id' => 2,
                'title' => 'Project Onboarding Checklist',
                'description' => 'Items needed before security, networking or website project kickoff.',
                'status' => 'Planned',
                'url' => '#',
                'file_path' => '',
                'file_name' => '',
                'mime_type' => '',
                'created_at' => date('c'),
            ],
        ],
        'invoices' => [],
        'services' => [
            [
                'id' => 1,
                'name' => 'Website Design',
                'description' => 'Corporate website design, mobile layout, SEO basics and launch support.',
                'default_price' => '8500.00',
                'category' => 'Websites',
                'vat' => false,
                'active' => true,
                'business_unit' => 'future_creative_group',
                'created_at' => date('c'),
            ],
            [
                'id' => 2,
                'name' => 'CCTV Installation',
                'description' => 'Camera installation, cabling, NVR setup, mobile app handover and testing.',
                'default_price' => '12500.00',
                'category' => 'Security',
                'vat' => false,
                'active' => true,
                'business_unit' => 'future_creative_group',
                'created_at' => date('c'),
            ],
            [
                'id' => 3,
                'name' => 'VoIP / Landline Setup',
                'description' => 'VoIP readiness, device setup, routing and user handover.',
                'default_price' => '2850.00',
                'category' => 'Communications',
                'vat' => false,
                'active' => true,
                'business_unit' => 'future_creative_group',
                'created_at' => date('c'),
            ],
            [
                'id' => 4,
                'name' => 'IT Support',
                'description' => 'Remote or onsite support for workstations, software, email and networks.',
                'default_price' => '750.00',
                'category' => 'IT Support',
                'vat' => false,
                'active' => true,
                'business_unit' => 'future_creative_group',
                'created_at' => date('c'),
            ],
        ],
        'templates' => fcg_default_templates(),
        'activity' => [],
        'onboarding' => [],
        'subscriptions' => [],
        'hosting_records' => [],
        'job_cards' => [],
        'feedback' => [],
        'guest_customers' => [],
        'email_history' => [],
        'document_links' => [],
        'document_access' => [],
        'proformas' => [],
        'credit_notes' => [],
        'statements' => [],
        'notifications' => [],
        'internal_notes' => [],
        'reminder_history' => [],
        'portal_settings' => [],
        'tickets' => [
            [
                'id' => 1,
                'client_id' => 2,
                'request_type' => 'Networking / WiFi',
                'priority' => 'Normal',
                'message' => 'Client requested a review of signal strength near office extension.',
                'status' => 'Open',
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
            [
                'id' => 2,
                'client_id' => 2,
                'request_type' => 'CCTV / Security',
                'priority' => 'Urgent',
                'message' => 'Assistance requested with exported footage and user access.',
                'status' => 'Pending',
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ],
    ];
    // Production starts clean; the sample records above remain only as developer fixtures.
    $seed['projects'] = [];
    $seed['quotes'] = [];
    $seed['invoices'] = [];
    $seed['documents'] = [];
    $seed['tickets'] = [];
    fcg_apply_business_unit_defaults($seed);
    return $seed;
}

function fcg_portal_data_file()
{
    return fcg_writable_data_file('portal-data.json');
}

function fcg_load_portal_data()
{
    $file = fcg_portal_data_file();
    $seed = fcg_portal_data_seed();
    if (is_file($file)) {
        $decoded = json_decode(file_get_contents($file), true);
        if (is_array($decoded)) {
            foreach (['projects', 'quotes', 'invoices', 'documents', 'tickets', 'services', 'activity', 'onboarding', 'subscriptions', 'hosting_records', 'job_cards', 'feedback', 'guest_customers', 'email_history', 'document_links', 'document_access', 'proformas', 'credit_notes', 'statements', 'notifications', 'internal_notes', 'reminder_history', 'portal_settings'] as $key) {
                if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                    $decoded[$key] = $seed[$key];
                }
            }
            if (!isset($decoded['templates']) || !is_array($decoded['templates'])) {
                $decoded['templates'] = $seed['templates'];
            } else {
                $decoded['templates'] = array_replace_recursive($seed['templates'], $decoded['templates']);
            }
            fcg_apply_business_unit_defaults($decoded);
            $decoded['quotes'] = array_map('fcg_normalize_quote', $decoded['quotes']);
            $decoded['invoices'] = array_map('fcg_normalize_invoice', $decoded['invoices']);
            $decoded['tickets'] = array_map('fcg_normalize_ticket', $decoded['tickets']);
            return $decoded;
        }
    }
    fcg_save_portal_data($seed);
    return $seed;
}

function fcg_save_portal_data($data)
{
    $file = fcg_portal_data_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    fcg_apply_business_unit_defaults($data);
    $data['updated_at'] = date('c');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
        fcg_json(['error' => 'Could not save portal data'], 500);
    }
}

function fcg_next_record_id($records)
{
    $max = 0;
    foreach ($records as $record) {
        $max = max($max, (int) fcg_value($record, 'id', 0));
    }
    return $max + 1;
}

function fcg_client_exists($clientId)
{
    return fcg_client_account_exists($clientId, true);
}

function fcg_client_account_exists($clientId, $requireActive = false)
{
    foreach (fcg_client_accounts() as $client) {
        if ((int) $client['id'] === (int) $clientId && (!$requireActive || !empty($client['active']))) {
            return true;
        }
    }
    return false;
}

function fcg_client_can_use_business_unit($clientId, $unit)
{
    $unit = fcg_normalize_business_unit($unit);
    foreach (fcg_client_accounts() as $client) {
        if ((int) $client['id'] === (int) $clientId && !empty($client['active'])) {
            return in_array($unit, fcg_value($client, 'business_units', []), true);
        }
    }
    return false;
}

function fcg_bool_value($value)
{
    if (is_bool($value)) {
        return $value;
    }
    $normal = strtolower(trim((string) $value));
    return in_array($normal, ['1', 'true', 'yes', 'active', 'on'], true);
}

function fcg_find_record_index($records, $id)
{
    foreach ($records as $index => $record) {
        if ((int) fcg_value($record, 'id', 0) === (int) $id) {
            return $index;
        }
    }
    return -1;
}

function fcg_delete_uploaded_file($filePath)
{
    if ($filePath === '' || !is_file($filePath)) {
        return;
    }
    $uploadsDir = realpath(fcg_data_dir());
    $realFile = realpath($filePath);
    if ($uploadsDir && $realFile && strpos($realFile, $uploadsDir) === 0) {
        @unlink($realFile);
    }
}

function fcg_validate_upload($file, $allowedExtensions, $maxBytes)
{
    if (!is_array($file) || (int) fcg_value($file, 'error', UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        fcg_json(['error' => 'A valid file upload is required'], 400);
    }
    if (!is_uploaded_file(fcg_value($file, 'tmp_name', ''))) {
        fcg_json(['error' => 'The uploaded file could not be verified'], 400);
    }
    if ((int) fcg_value($file, 'size', 0) <= 0 || (int) fcg_value($file, 'size', 0) > $maxBytes) {
        fcg_json(['error' => 'The uploaded file exceeds the permitted size'], 400);
    }
    $original = basename(fcg_value($file, 'name', 'file'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        fcg_json(['error' => 'This file type is not permitted'], 400);
    }
    return [
        'original' => $original,
        'safe_name' => preg_replace('/[^A-Za-z0-9._-]+/', '-', $original),
        'extension' => $extension,
    ];
}

function fcg_default_templates()
{
    return [
        'default_quote_template' => 'executive-corporate',
        'default_invoice_template' => 'executive-corporate',
        'default_theme' => 'dark',
        'vat_rate' => 15,
        'prefixes' => [
            'quote' => 'FCG/QT',
            'invoice' => 'FCG/INV',
            'receipt' => 'FCG/RCPT',
            'proforma' => 'FCG/PRO',
            'credit_note' => 'FCG/CN',
            'statement' => 'FCG/STMT',
        ],
        'business_unit_settings' => [
            'fcg_cloud' => [
                'prefixes' => [
                    'quote' => 'FCC-QUO',
                    'invoice' => 'FCC-INV',
                    'receipt' => 'FCC-REC',
                    'proforma' => 'FCC-PRO',
                    'credit_note' => 'FCC-CN',
                    'statement' => 'FCC-STA',
                ],
                'smtp' => [
                    'from_email' => 'billing@fcgcloud.co.za',
                ],
            ],
        ],
        'available_templates' => [
            'executive-corporate' => 'Executive Corporate',
            'modern-technology' => 'Modern Technology',
            'luxury-blue' => 'Luxury Blue',
            'minimal-professional' => 'Minimal Professional',
        ],
        'company' => [
            'name' => 'Future Creative Group Pty Ltd',
            'website' => 'www.futurecreativegroup.co.za',
            'email' => 'info@futurecreativegroup.co.za',
            'phone' => '011 568 0279',
            'address' => '32 Mir Street, Makhulong, Tembisa 1632',
            'slogan' => 'Innovative Digital Solutions & Security Technology',
            'footer' => 'Connecting Your Business To The Future',
            'logo_url' => fcg_portal_path('/static/logo.png'),
            'logo_path' => '',
            'logo_pdf_path' => '',
        ],
        'banking' => [
            'bank' => 'First National Bank / FNB',
            'account_holder' => 'Future Creative Group (Pty) Ltd',
            'account_type' => 'Gold Business Account',
            'account_number' => '63215619909',
            'branch_code' => '200632',
            'branch_name' => 'PHUMULANI SERVICE BRANCH',
            'swift_code' => 'FIRNZAJJ',
            'payment_reference' => 'Please use your invoice number or company name as the payment reference.',
        ],
        'terms' => [
            'quote' => 'This quotation is valid until the expiry date shown. Prices may change after expiry. Work begins once the quotation is approved and the required deposit or payment has been received. Additional work outside this quotation will be quoted separately. Timelines depend on client feedback, payment confirmation, content submission, supplier availability and project requirements.',
            'invoice' => 'Payment is due on or before the due date shown. Please use your invoice number or company name as the payment reference. Proof of payment must be uploaded through the client portal or sent to Future Creative Group. Services may be delayed, suspended or placed on hold if payment is not received on time. Proof of payment does not confirm payment until verified by Future Creative Group Pty Ltd.',
            'receipt' => 'Thank you for your payment. This receipt confirms that payment has been received and recorded by Future Creative Group Pty Ltd. Please keep this receipt for your records. If this was a partial payment, the remaining balance will still reflect on your invoice or statement.',
            'proforma' => 'This pro-forma invoice is issued for planning purposes and is not a tax invoice or proof of payment.',
            'credit_note' => 'This credit note adjusts the referenced invoice and should be retained with the original tax document.',
            'statement' => 'This statement reflects invoices, payments, credits and outstanding balances recorded on your account. Outstanding balances must be settled by the applicable due date. Please contact Future Creative Group Pty Ltd if any payment or invoice appears to be missing.',
            'payment_notes' => 'Please use your invoice number or company name as your payment reference. Proof of payment must be uploaded through the client portal or sent to Future Creative Group. Services will be activated, renewed, delivered or continued once payment has been received and confirmed. For partial payments, the remaining balance must be paid by the agreed due date. Late or overdue payments may delay service delivery, renewals, hosting, domain services, support or project handover.',
        ],
        'notifications' => [
            'automatic_email' => false,
            'manual_whatsapp' => true,
            'automatic_whatsapp' => false,
            'renewal_reminders' => true,
            'invoice_reminders' => true,
        ],
        'security' => [
            'admin_2fa' => false,
            'suspicious_login_email' => true,
        ],
        'smtp' => [
            'host' => '',
            'port' => '587',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_email' => 'no-reply@futurecreativegroup.co.za',
        ],
        'whatsapp' => [
            'business_api_enabled' => false,
            'phone_number_id' => '',
        ],
        'email_templates' => [
            'welcome' => 'Welcome to the Future Creative Group Client Portal. Your secure profile is ready. Sign in at {{portal_url}} using the access details issued to you.',
            'quotation_created' => 'Quotation {{document_number}} is ready for review. View, approve, decline or request changes securely at {{portal_url}}.',
            'invoice_created' => 'Invoice {{document_number}} has been issued. Please review the amount, due date and banking details in your secure portal: {{portal_url}}. Payment Reference: please use your invoice number or company name.',
            'payment_received' => 'We have recorded your payment against {{document_number}}. The account balance will update after verification. Payment Reference: please use your invoice number or company name.',
            'payment_approved' => 'Your payment for {{document_number}} has been approved. Your receipt is available in the portal.',
            'payment_rejected' => 'We could not approve the payment submitted for {{document_number}}. Please review the portal update or contact support.',
            'receipt_ready' => 'Receipt {{document_number}} is ready to download securely from {{portal_url}}.',
            'project_update' => 'A new update has been posted for {{project_name}}. View progress and next actions at {{portal_url}}.',
            'document_uploaded' => 'A new document, {{document_name}}, has been added to your secure client workspace.',
            'support_update' => 'Support ticket {{ticket_number}} has a new update. Sign in to review the response.',
            'renewal_reminder' => '{{service_name}} is due for renewal on {{renewal_date}}. Please review the renewal record in your portal.',
            'overdue_reminder' => 'Invoice {{document_number}} is overdue with {{balance_due}} outstanding. Please review the account at {{portal_url}}. Payment Reference: please use your invoice number or company name.',
        ],
        'whatsapp_templates' => [
            'welcome' => 'Welcome to Future Creative Group. Your secure client portal is ready: {{portal_url}}',
            'quotation_created' => 'Future Creative Group: quotation {{document_number}} is ready for review: {{secure_url}}',
            'invoice_created' => 'Future Creative Group: invoice {{document_number}} is ready: {{secure_url}} Payment reference: invoice number or company name.',
            'payment_received' => 'Thank you. We recorded your payment for {{document_number}} and will confirm verification shortly.',
            'receipt_ready' => 'Your Future Creative Group receipt is ready: {{secure_url}}',
            'project_update' => 'A project update is available in your Future Creative Group portal: {{portal_url}}',
            'document_uploaded' => 'A new document has been added to your secure portal: {{portal_url}}',
            'support_update' => 'Support ticket {{ticket_number}} has been updated: {{portal_url}}',
            'renewal_reminder' => '{{service_name}} renews on {{renewal_date}}. Review it here: {{portal_url}}',
            'overdue_reminder' => 'Invoice {{document_number}} is overdue. Outstanding: {{balance_due}}. Payment reference: invoice number or company name. Portal: {{portal_url}}',
        ],
        'footer_notes' => 'Connecting Your Business To The Future',
    ];
}

function fcg_money_value($value)
{
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }
    $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
    return $clean === '' ? 0.0 : round((float) $clean, 2);
}

function fcg_money_format($value)
{
    return 'R' . number_format(fcg_money_value($value), 2, '.', ',');
}

function fcg_array_input($value)
{
    if (is_array($value)) {
        return array_values($value);
    }
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }
    return [];
}

function fcg_normalize_items($input)
{
    $items = [];
    $rawItems = fcg_value($input, 'items', []);
    if (is_string($rawItems)) {
        $decoded = json_decode($rawItems, true);
        $rawItems = is_array($decoded) ? $decoded : [];
    }
    if (is_array($rawItems) && count($rawItems) > 0) {
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim(fcg_value($item, 'description', fcg_value($item, 'item_description', '')));
            if ($description === '') {
                continue;
            }
            $items[] = [
                'description' => $description,
                'quantity' => max(1, (float) fcg_value($item, 'quantity', 1)),
                'unit_price' => fcg_money_value(fcg_value($item, 'unit_price', 0)),
                'discount' => fcg_money_value(fcg_value($item, 'discount', 0)),
            ];
        }
    }

    if (!$items) {
        $description = trim(fcg_value($input, 'item_description', fcg_value($input, 'description', fcg_value($input, 'title', 'Service'))));
        $items[] = [
            'description' => $description === '' ? 'Service' : $description,
            'quantity' => max(1, (float) fcg_value($input, 'quantity', 1)),
            'unit_price' => fcg_money_value(fcg_value($input, 'unit_price', fcg_value($input, 'amount', 0))),
            'discount' => fcg_money_value(fcg_value($input, 'discount', 0)),
        ];
    }

    return $items;
}

function fcg_document_totals($items, $vatEnabled)
{
    $subtotal = 0.0;
    $discount = 0.0;
    foreach ($items as $item) {
        $lineSubtotal = fcg_money_value(fcg_value($item, 'quantity', 1)) * fcg_money_value(fcg_value($item, 'unit_price', 0));
        $lineDiscount = fcg_money_value(fcg_value($item, 'discount', 0));
        $subtotal += $lineSubtotal;
        $discount += min($lineSubtotal, $lineDiscount);
    }
    $taxable = max(0, $subtotal - $discount);
    $vat = $vatEnabled ? round($taxable * 0.15, 2) : 0.0;
    $total = round($taxable + $vat, 2);
    return [
        'subtotal' => round($subtotal, 2),
        'discount' => round($discount, 2),
        'vat_enabled' => (bool) $vatEnabled,
        'vat' => $vat,
        'total' => $total,
        'amount' => fcg_money_format($total),
    ];
}

function fcg_business_number($records, $prefix, $field)
{
    $stamp = date('my');
    $max = 0;
    $pattern = '#^' . preg_quote($prefix . '/' . $stamp . '/', '#') . '([0-9]+)$#';
    foreach ($records as $record) {
        $number = fcg_value($record, $field, fcg_value($record, 'quote_no', fcg_value($record, 'invoice_no', '')));
        if (preg_match($pattern, $number, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }
    return $prefix . '/' . $stamp . '/' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

function fcg_client_contact($clientId)
{
    foreach (fcg_client_accounts() as $client) {
        if ((int) fcg_value($client, 'id', 0) === (int) $clientId) {
            return $client;
        }
    }
    return ['name' => '', 'company' => '', 'email' => ''];
}

function fcg_quote_stats($quotes)
{
    return [
        'pending_quotations' => count(array_filter($quotes, function ($quote) {
            return in_array(fcg_value($quote, 'status', 'Draft'), ['Draft', 'Sent', 'Viewed', 'Request Changes', 'Changes Requested', 'Expired'], true);
        })),
        'approved_quotations' => count(array_filter($quotes, function ($quote) {
            return fcg_value($quote, 'status', '') === 'Approved';
        })),
    ];
}

function fcg_invoice_stats($invoices)
{
    $totalRevenue = 0.0;
    $paid = 0;
    $unpaid = 0;
    $partial = 0;
    foreach ($invoices as $invoice) {
        $status = fcg_value($invoice, 'payment_status', 'Unpaid');
        if (in_array($status, ['Paid', 'Paid In Full'], true)) {
            $paid++;
            $totalRevenue += fcg_money_value(fcg_value($invoice, 'total', fcg_value($invoice, 'total_amount', 0)));
        } elseif ($status === 'Partially Paid') {
            $partial++;
            $totalRevenue += fcg_money_value(fcg_value($invoice, 'amount_paid', 0));
        } else {
            $unpaid++;
        }
    }
    return [
        'total_revenue' => fcg_money_format($totalRevenue),
        'paid_invoices' => $paid,
        'unpaid_invoices' => $unpaid,
        'partial_invoices' => $partial,
    ];
}

function fcg_portal_analytics($data, $clients)
{
    $monthly = [];
    $serviceSales = [];
    $overdue = 0;
    foreach ($data['invoices'] as $invoice) {
        $invoice = fcg_normalize_invoice($invoice);
        $status = fcg_value($invoice, 'payment_status', 'Unpaid');
        $date = fcg_value($invoice, 'payment_date', fcg_value($invoice, 'invoice_date', ''));
        $month = $date !== '' ? substr($date, 0, 7) : date('Y-m');
        if (!isset($monthly[$month])) {
            $monthly[$month] = 0.0;
        }
        $monthly[$month] += fcg_money_value(fcg_value($invoice, 'amount_paid', 0));
        if (!in_array($status, ['Paid', 'Paid In Full', 'Cancelled'], true)
            && fcg_value($invoice, 'due_date', '') !== ''
            && fcg_value($invoice, 'due_date', '') < date('Y-m-d')) {
            $overdue++;
        }
        foreach (fcg_value($invoice, 'items', []) as $item) {
            $name = trim(fcg_value($item, 'description', 'Service'));
            if ($name === '') {
                continue;
            }
            if (!isset($serviceSales[$name])) {
                $serviceSales[$name] = 0;
            }
            $serviceSales[$name] += (float) fcg_value($item, 'quantity', 1);
        }
    }
    ksort($monthly);
    $monthly = array_slice($monthly, -6, 6, true);
    arsort($serviceSales);
    $newClients = count(array_filter($clients, function ($client) {
        return substr(fcg_value($client, 'created_at', ''), 0, 7) === date('Y-m');
    }));
    return [
        'monthly_revenue' => array_map('fcg_money_format', $monthly),
        'overdue_invoices' => $overdue,
        'new_clients' => $newClients,
        'active_projects' => count(array_filter($data['projects'], function ($project) {
            return !in_array(fcg_value($project, 'status', ''), ['Completed', 'Handover done'], true);
        })),
        'support_requests' => count($data['tickets']),
        'best_selling_services' => array_slice($serviceSales, 0, 5, true),
    ];
}

function fcg_public_onboarding($record)
{
    unset($record['file_path']);
    return $record;
}

function fcg_public_job_card($record)
{
    unset($record['before_photo_path'], $record['after_photo_path']);
    return $record;
}

function fcg_normalize_quote($quote)
{
    $items = isset($quote['items']) && is_array($quote['items']) ? $quote['items'] : fcg_normalize_items($quote);
    $totals = fcg_document_totals($items, fcg_bool_value(fcg_value($quote, 'vat_enabled', fcg_value($quote, 'vat', false))));
    $quote['items'] = $items;
    $quote['business_unit'] = fcg_record_business_unit($quote);
    $quote['quote_no'] = fcg_value($quote, 'quote_no', fcg_value($quote, 'number', ''));
    $quote['quote_date'] = fcg_value($quote, 'quote_date', substr(fcg_value($quote, 'updated_at', date('c')), 0, 10));
    $quote['valid_until'] = fcg_value($quote, 'valid_until', date('Y-m-d', strtotime('+14 days')));
    $quote['status'] = fcg_value($quote, 'status', 'Draft') === 'Review' ? 'Viewed' : fcg_value($quote, 'status', 'Draft');
    if ($quote['status'] === 'Request Changes') {
        $quote['status'] = 'Changes Requested';
    }
    $quote['subtotal'] = fcg_value($quote, 'subtotal', $totals['subtotal']);
    $quote['discount'] = fcg_value($quote, 'discount', $totals['discount']);
    $quote['vat_enabled'] = fcg_bool_value(fcg_value($quote, 'vat_enabled', $totals['vat_enabled']));
    $quote['vat'] = fcg_value($quote, 'vat', $totals['vat']);
    $quote['total'] = fcg_value($quote, 'total', $totals['total']);
    $quote['amount'] = fcg_value($quote, 'amount', fcg_money_format($quote['total']));
    $quote['terms'] = fcg_value($quote, 'terms', fcg_default_templates()['terms']['quote']);
    $quote['notes'] = fcg_value($quote, 'notes', '');
    $quote['prepared_by'] = fcg_value($quote, 'prepared_by', 'Future Creative Group');
    $quote['approval_status'] = fcg_value($quote, 'approval_status', $quote['status']);
    $quote['customer_type'] = fcg_value($quote, 'customer_type', (int) fcg_value($quote, 'client_id', 0) > 0 ? 'registered' : 'guest');
    $quote['guest_customer_id'] = (int) fcg_value($quote, 'guest_customer_id', 0);
    return $quote;
}

function fcg_normalize_invoice($invoice)
{
    $items = isset($invoice['items']) && is_array($invoice['items']) ? $invoice['items'] : fcg_normalize_items($invoice);
    $totals = fcg_document_totals($items, fcg_bool_value(fcg_value($invoice, 'vat_enabled', fcg_value($invoice, 'vat', false))));
    $invoice['items'] = $items;
    $invoice['business_unit'] = fcg_record_business_unit($invoice);
    $invoice['invoice_no'] = fcg_value($invoice, 'invoice_no', fcg_value($invoice, 'number', ''));
    $invoice['invoice_date'] = fcg_value($invoice, 'invoice_date', substr(fcg_value($invoice, 'updated_at', date('c')), 0, 10));
    $invoice['due_date'] = fcg_value($invoice, 'due_date', date('Y-m-d', strtotime('+7 days')));
    $invoice['payment_status'] = fcg_value($invoice, 'payment_status', 'Unpaid');
    if ($invoice['payment_status'] === 'Paid') {
        $invoice['payment_status'] = 'Paid In Full';
    }
    $invoice['subtotal'] = fcg_value($invoice, 'subtotal', $totals['subtotal']);
    $invoice['discount'] = fcg_value($invoice, 'discount', $totals['discount']);
    $invoice['vat_enabled'] = fcg_bool_value(fcg_value($invoice, 'vat_enabled', $totals['vat_enabled']));
    $invoice['vat'] = fcg_value($invoice, 'vat', $totals['vat']);
    $invoice['total'] = fcg_value($invoice, 'total', $totals['total']);
    $invoice['amount'] = fcg_value($invoice, 'amount', fcg_money_format($invoice['total']));
    $invoice['amount_paid'] = fcg_money_value(fcg_value($invoice, 'amount_paid', 0));
    $invoice['balance_due'] = max(0, fcg_money_value($invoice['total']) - fcg_money_value($invoice['amount_paid']));
    $invoice['payment_history'] = fcg_array_input(fcg_value($invoice, 'payment_history', []));
    $invoice['customer_type'] = fcg_value($invoice, 'customer_type', (int) fcg_value($invoice, 'client_id', 0) > 0 ? 'registered' : 'guest');
    $invoice['guest_customer_id'] = (int) fcg_value($invoice, 'guest_customer_id', 0);
    $invoice['terms'] = fcg_value($invoice, 'terms', fcg_default_templates()['terms']['invoice']);
    $invoice['notes'] = fcg_value($invoice, 'notes', '');
    return $invoice;
}

function fcg_normalize_ticket($ticket)
{
    $ticket['ticket_no'] = fcg_value($ticket, 'ticket_no', 'FCG/TK/' . str_pad((string) fcg_value($ticket, 'id', 0), 4, '0', STR_PAD_LEFT));
    $ticket['business_unit'] = fcg_record_business_unit($ticket);
    $ticket['category'] = fcg_value($ticket, 'category', fcg_value($ticket, 'request_type', 'Other'));
    $ticket['request_type'] = fcg_value($ticket, 'request_type', $ticket['category']);
    $ticket['priority'] = fcg_value($ticket, 'priority', 'Normal');
    $ticket['assigned_technician'] = fcg_value($ticket, 'assigned_technician', '');
    $ticket['admin_response'] = fcg_value($ticket, 'admin_response', '');
    $ticket['client_response'] = fcg_value($ticket, 'client_response', '');
    $ticket['completion_notes'] = fcg_value($ticket, 'completion_notes', '');
    $ticket['resolution_date'] = fcg_value($ticket, 'resolution_date', '');
    return $ticket;
}

function fcg_public_invoice($invoice)
{
    unset($invoice['proof_path']);
    if (isset($invoice['payment_history']) && is_array($invoice['payment_history'])) {
        foreach ($invoice['payment_history'] as &$payment) {
            unset($payment['proof_path']);
            if (!empty($payment['proof_file'])) {
                $payment['proof_url'] = fcg_portal_path('/api/invoices/proof/download?invoice_id=' . (int) fcg_value($invoice, 'id', 0) . '&payment_id=' . (int) fcg_value($payment, 'id', 0));
            }
        }
        unset($payment);
    }
    return $invoice;
}

function fcg_pending_payments($invoices)
{
    $pending = [];
    foreach ($invoices as $invoice) {
        $invoice = fcg_normalize_invoice($invoice);
        foreach (fcg_value($invoice, 'payment_history', []) as $payment) {
            if (fcg_value($payment, 'status', '') !== 'Pending') {
                continue;
            }
            unset($payment['proof_path']);
            $payment['client_id'] = (int) fcg_value($invoice, 'client_id', 0);
            $payment['guest_customer_id'] = (int) fcg_value($invoice, 'guest_customer_id', 0);
            $payment['invoice_id'] = (int) fcg_value($invoice, 'id', 0);
            $payment['invoice_no'] = fcg_value($invoice, 'invoice_no', '');
            $payment['client_name'] = fcg_value($invoice, 'client_name', '');
            $payment['client_company'] = fcg_value($invoice, 'client_company', '');
            $payment['payment_status'] = fcg_value($invoice, 'payment_status', '');
            $payment['invoice_total'] = fcg_money_value(fcg_value($invoice, 'total', 0));
            $payment['invoice_balance_due'] = fcg_money_value(fcg_value($invoice, 'balance_due', 0));
            $payment['proof_url'] = !empty($payment['proof_file'])
                ? fcg_portal_path('/api/invoices/proof/download?invoice_id=' . (int) fcg_value($invoice, 'id', 0) . '&payment_id=' . (int) fcg_value($payment, 'id', 0))
                : '';
            $pending[] = $payment;
        }
    }
    usort($pending, function ($a, $b) {
        return strcmp(fcg_value($b, 'uploaded_at', fcg_value($b, 'created_at', '')), fcg_value($a, 'uploaded_at', fcg_value($a, 'created_at', '')));
    });
    return $pending;
}

function fcg_payment_status(&$invoice)
{
    $total = fcg_money_value(fcg_value($invoice, 'total', 0));
    $paid = fcg_money_value(fcg_value($invoice, 'amount_paid', 0));
    $invoice['amount_paid'] = min($total, max(0, $paid));
    $invoice['balance_due'] = max(0, $total - $invoice['amount_paid']);
    $hasPending = count(array_filter(fcg_value($invoice, 'payment_history', []), function ($payment) {
        return fcg_value($payment, 'status', '') === 'Pending';
    })) > 0;
    if ($hasPending) {
        $invoice['payment_status'] = 'Payment Pending Review';
    } elseif ($invoice['balance_due'] <= 0 && $total > 0) {
        $invoice['payment_status'] = 'Paid In Full';
    } elseif ($invoice['amount_paid'] > 0) {
        $invoice['payment_status'] = 'Partially Paid';
    } else {
        $invoice['payment_status'] = 'Unpaid';
    }
}

function fcg_log_activity(&$data, $type, $message, $clientId = 0, $businessUnit = null)
{
    if (!isset($data['activity']) || !is_array($data['activity'])) {
        $data['activity'] = [];
    }
    $businessUnit = $businessUnit ? fcg_normalize_business_unit($businessUnit) : fcg_active_business_unit(fcg_user());
    array_unshift($data['activity'], [
        'id' => fcg_next_record_id($data['activity']),
        'type' => $type,
        'message' => $message,
        'client_id' => (int) $clientId,
        'business_unit' => $businessUnit,
        'created_at' => date('c'),
    ]);
    $data['activity'] = array_slice($data['activity'], 0, 100);
}

function fcg_add_notification(&$data, $clientId, $type, $title, $message, $relatedType = '', $relatedId = 0, $channel = 'Portal', $guestCustomerId = 0, $businessUnit = null)
{
    if (!isset($data['notifications']) || !is_array($data['notifications'])) $data['notifications'] = [];
    $businessUnit = $businessUnit ? fcg_normalize_business_unit($businessUnit) : fcg_active_business_unit(fcg_user());
    $notification = [
        'id' => fcg_next_record_id($data['notifications']),
        'client_id' => (int) $clientId,
        'guest_customer_id' => (int) $guestCustomerId,
        'business_unit' => $businessUnit,
        'type' => $type,
        'title' => trim((string) $title),
        'message' => trim((string) $message),
        'related_type' => $relatedType,
        'related_id' => (int) $relatedId,
        'channel' => $channel,
        'status' => 'Sent',
        'sent_by' => 'Portal Automation',
        'sent_to' => '',
        'read_at' => '',
        'created_at' => date('c'),
    ];
    array_unshift($data['notifications'], $notification);
    $data['notifications'] = array_slice($data['notifications'], 0, 1000);
    return $notification;
}

function fcg_notify_record(&$data, $record, $type, $title, $message)
{
    $clientId = (int) fcg_value($record, 'client_id', 0);
    $guestId = (int) fcg_value($record, 'guest_customer_id', 0);
    $relatedId = (int) fcg_value($record, 'id', 0);
    $businessUnit = fcg_record_business_unit($record);
    $brand = fcg_brand_config($businessUnit);
    $notification = fcg_add_notification($data, $clientId, $type, $title, $message, $type, $relatedId, 'Portal', $guestId, $businessUnit);
    $settings = fcg_value(fcg_value($data, 'templates', []), 'notifications', []);
    $email = strtolower(trim(fcg_value($record, 'client_email', '')));
    if ($clientId > 0 && $email === '') {
        foreach (fcg_load_users() as $user) {
            if ((int) fcg_value($user, 'id', 0) === $clientId) {
                $email = strtolower(trim(fcg_value($user, 'email', '')));
                break;
            }
        }
    }
    if (fcg_bool_value(fcg_value($settings, 'automatic_email', false)) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sent = fcg_send_customer_email($email, $title . ' - ' . $brand['label'], [$message, '', 'Portal: ' . $brand['portal_url'], '', $brand['label']], $businessUnit);
        $notification['channel'] = 'Email';
        $notification['status'] = $sent ? 'Sent' : 'Failed';
        $notification['sent_to'] = $email;
        $notificationIndex = fcg_find_record_index($data['notifications'], $notification['id']);
        if ($notificationIndex >= 0) $data['notifications'][$notificationIndex] = $notification;
        array_unshift($data['email_history'], [
            'id' => fcg_next_record_id($data['email_history']),
            'sent_at' => date('c'), 'sent_by' => 'Portal Automation', 'sent_to' => $email,
            'business_unit' => $businessUnit,
            'document_type' => $type, 'document_number' => fcg_value($record, 'quote_no', fcg_value($record, 'invoice_no', '')),
            'delivery_method' => 'Email', 'delivery_status' => $sent ? 'Sent' : 'Failed',
        ]);
    }
    fcg_log_activity($data, 'notification', $title . ' notification created', $clientId, $businessUnit);
    return $notification;
}

function fcg_financial_document_from_input($input, &$data, $collection, $existing = null)
{
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $input['business_unit'] = $businessUnit;
    $unitTemplates = fcg_templates_for_business_unit($data['templates'], $businessUnit);
    if (trim(fcg_value($input, 'terms', '')) === '') {
        $termsKey = $collection === 'credit_notes' ? 'credit_note' : ($collection === 'statements' ? 'statement' : 'proforma');
        $input['terms'] = fcg_value(fcg_value($unitTemplates, 'terms', []), $termsKey, '');
    }
    $document = fcg_invoice_from_input($input, $data, $existing);
    $isCredit = $collection === 'credit_notes';
    $numberKey = $isCredit ? 'credit_note_no' : ($collection === 'statements' ? 'statement_no' : 'proforma_no');
    $prefixKey = $isCredit ? 'credit_note' : ($collection === 'statements' ? 'statement' : 'proforma');
    $prefixes = fcg_value($unitTemplates, 'prefixes', []);
    $prefix = fcg_value($prefixes, $prefixKey, $isCredit ? 'FCG/CN' : ($collection === 'statements' ? 'FCG/STMT' : 'FCG/PRO'));
    $document['id'] = $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data[$collection]);
    $document[$numberKey] = trim(fcg_value($input, $numberKey, fcg_value($existing, $numberKey, '')));
    if ($document[$numberKey] === '') $document[$numberKey] = fcg_business_number($data[$collection], $prefix, $numberKey);
    $document['document_type'] = $isCredit ? 'Credit Note' : ($collection === 'statements' ? 'Statement' : 'Pro-forma Invoice');
    $document['status'] = fcg_value($input, 'status', fcg_value($existing, 'status', 'Draft'));
    $document['reference_invoice_id'] = (int) fcg_value($input, 'reference_invoice_id', fcg_value($existing, 'reference_invoice_id', 0));
    $document['reason'] = trim(fcg_value($input, 'reason', fcg_value($existing, 'reason', '')));
    $document['internal_notes'] = trim(fcg_value($input, 'internal_notes', fcg_value($existing, 'internal_notes', '')));
    if ($isCredit) {
        $document['credit_amount'] = fcg_money_value($document['total']);
        $document['balance_due'] = 0;
        $document['amount_paid'] = 0;
    }
    unset($document['invoice_no']);
    return $document;
}

function fcg_statement_from_input($input, &$data)
{
    $businessUnit = fcg_business_unit_from_input($input, null, fcg_user());
    $input['business_unit'] = $businessUnit;
    $customer = fcg_document_customer($input, $data);
    $from = fcg_value($input, 'date_from', date('Y-m-01', strtotime('-2 months')));
    $to = fcg_value($input, 'date_to', date('Y-m-d'));
    $invoices = array_values(array_filter($data['invoices'], function ($invoice) use ($customer, $from, $to, $businessUnit) {
        $same = $customer['client_id'] > 0
            ? (int) fcg_value($invoice, 'client_id', 0) === $customer['client_id']
            : (int) fcg_value($invoice, 'guest_customer_id', 0) === $customer['guest_customer_id'];
        $date = fcg_value($invoice, 'invoice_date', '');
        return $same && fcg_record_matches_business_unit($invoice, $businessUnit) && $date >= $from && $date <= $to;
    }));
    $credits = array_values(array_filter($data['credit_notes'], function ($credit) use ($customer, $from, $to, $businessUnit) {
        $same = $customer['client_id'] > 0
            ? (int) fcg_value($credit, 'client_id', 0) === $customer['client_id']
            : (int) fcg_value($credit, 'guest_customer_id', 0) === $customer['guest_customer_id'];
        $date = fcg_value($credit, 'invoice_date', '');
        return $same && fcg_record_matches_business_unit($credit, $businessUnit) && $date >= $from && $date <= $to;
    }));
    $items = [];
    $totalInvoiced = 0; $totalPaid = 0; $totalCredits = 0;
    foreach ($invoices as $invoice) {
        $normal = fcg_normalize_invoice($invoice);
        $totalInvoiced += fcg_money_value($normal['total']);
        $totalPaid += fcg_money_value($normal['amount_paid']);
        $items[] = ['description' => fcg_value($normal, 'invoice_no', '') . ' - ' . fcg_value($normal, 'title', 'Invoice'), 'quantity' => 1, 'unit_price' => fcg_money_value($normal['total']), 'discount' => 0];
    }
    foreach ($credits as $credit) {
        $creditAmount = fcg_money_value(fcg_value($credit, 'credit_amount', fcg_value($credit, 'total', 0)));
        $totalCredits += $creditAmount;
        $items[] = ['description' => fcg_value($credit, 'credit_note_no', '') . ' - ' . fcg_value($credit, 'title', 'Credit Note'), 'quantity' => 1, 'unit_price' => -$creditAmount, 'discount' => 0];
    }
    $balance = max(0, $totalInvoiced - $totalPaid - $totalCredits);
    $input['items'] = $items ?: [['description' => 'No account activity for this period', 'quantity' => 1, 'unit_price' => 0, 'discount' => 0]];
    $input['title'] = 'Statement of Account';
    $input['invoice_date'] = $to;
    $input['due_date'] = $to;
    $input['amount_paid'] = $totalPaid + $totalCredits;
    $input['status'] = 'Issued';
    $input['terms'] = fcg_value(fcg_value(fcg_templates_for_business_unit($data['templates'], $businessUnit), 'terms', []), 'statement', 'Statement of account.');
    $document = fcg_financial_document_from_input($input, $data, 'statements');
    $document['date_from'] = $from; $document['date_to'] = $to;
    $document['total_invoiced'] = $totalInvoiced; $document['total_paid'] = $totalPaid;
    $document['total_credits'] = $totalCredits; $document['total'] = $totalInvoiced;
    $document['amount_paid'] = $totalPaid + $totalCredits; $document['balance_due'] = $balance;
    return $document;
}

function fcg_invoice_ageing($invoices)
{
    $buckets = ['Current' => [], '0-7 days' => [], '8-14 days' => [], '15-30 days' => [], '30+ days' => []];
    $total = 0;
    foreach ($invoices as $invoice) {
        $invoice = fcg_normalize_invoice($invoice);
        if (in_array(fcg_value($invoice, 'payment_status', ''), ['Paid', 'Paid In Full', 'Cancelled'], true) || fcg_money_value($invoice['balance_due']) <= 0) continue;
        $days = max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime(fcg_value($invoice, 'due_date', date('Y-m-d')))) / 86400));
        $bucket = $days <= 0 ? 'Current' : ($days <= 7 ? '0-7 days' : ($days <= 14 ? '8-14 days' : ($days <= 30 ? '15-30 days' : '30+ days')));
        $invoice['ageing_bucket'] = $bucket; $invoice['days_overdue'] = $days;
        $buckets[$bucket][] = $invoice; $total += fcg_money_value($invoice['balance_due']);
    }
    return ['buckets' => $buckets, 'total_outstanding' => $total];
}

function fcg_pdf_escape($value)
{
    $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', (string) $value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function fcg_pdf_text_command($x, $y, $size, $text, $font = 'F1', $color = '0 0 0')
{
    return $color . " rg BT /" . $font . " " . $size . " Tf " . $x . " " . $y . " Td (" . fcg_pdf_escape($text) . ") Tj ET\n";
}

function fcg_pdf_rect($x, $y, $width, $height, $color)
{
    return $color . ' rg ' . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re f\n";
}

function fcg_pdf_theme($key)
{
    if ($key === 'premium') {
        $key = 'executive-corporate';
    }
    $themes = [
        'executive-corporate' => [
            'name' => 'Executive Corporate',
            'primary' => '0.02 0.10 0.18',
            'secondary' => '0.04 0.23 0.43',
            'accent' => '0.08 0.49 0.82',
            'pale' => '0.93 0.97 1',
            'ink' => '0.08 0.14 0.21',
            'muted' => '0.35 0.43 0.51',
        ],
        'modern-technology' => [
            'name' => 'Modern Technology',
            'primary' => '0.01 0.08 0.14',
            'secondary' => '0.02 0.31 0.46',
            'accent' => '0.00 0.67 0.86',
            'pale' => '0.91 0.98 1',
            'ink' => '0.04 0.12 0.18',
            'muted' => '0.31 0.43 0.50',
        ],
        'luxury-blue' => [
            'name' => 'Luxury Blue',
            'primary' => '0.02 0.08 0.25',
            'secondary' => '0.07 0.18 0.48',
            'accent' => '0.20 0.42 0.82',
            'pale' => '0.94 0.96 1',
            'ink' => '0.05 0.09 0.22',
            'muted' => '0.34 0.39 0.51',
        ],
        'minimal-professional' => [
            'name' => 'Minimal Professional',
            'primary' => '0.10 0.16 0.23',
            'secondary' => '0.18 0.27 0.38',
            'accent' => '0.18 0.42 0.72',
            'pale' => '0.96 0.97 0.98',
            'ink' => '0.10 0.14 0.18',
            'muted' => '0.40 0.45 0.50',
        ],
    ];
    return isset($themes[$key]) ? $themes[$key] : $themes['executive-corporate'];
}

function fcg_pdf_short_lines($text, $lineLength, $maxLines)
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') {
        return [];
    }
    return array_slice(explode("\n", wordwrap($text, $lineLength, "\n", true)), 0, $maxLines);
}

function fcg_pdf_document($title, $badge, $document, $settings)
{
    $company = fcg_value($settings, 'company', []);
    $banking = fcg_value($settings, 'banking', []);
    $items = fcg_value($document, 'items', []);
    $isQuote = in_array($title, ['QUOTATION', 'PRO-FORMA INVOICE'], true);
    $templateKey = fcg_value($settings, '_active_template', $isQuote
        ? fcg_value($settings, 'default_quote_template', 'executive-corporate')
        : fcg_value($settings, 'default_invoice_template', 'executive-corporate'));
    $theme = fcg_pdf_theme($templateKey);
    $logoPath = fcg_value($company, 'logo_pdf_path', '');
    if ($logoPath === '' || !is_file($logoPath)) $logoPath = fcg_first_file('static/logo-pdf.jpg');
    $logoBytes = is_file($logoPath) ? file_get_contents($logoPath) : '';
    $hasLogo = is_string($logoBytes) && $logoBytes !== '';
    $lines = [];
    $lines[] = fcg_pdf_rect(0, 0, 595, 842, '1 1 1');
    $lines[] = fcg_pdf_rect(0, 720, 595, 122, $theme['primary']);
    $lines[] = fcg_pdf_rect(0, 720, 9, 122, $theme['accent']);
    if ($hasLogo) {
        $lines[] = 'q 180 0 0 90 24 744 cm /Im1 Do Q' . "\n";
    } else {
        $lines[] = fcg_pdf_rect(30, 770, 38, 38, $theme['accent']);
        $lines[] = fcg_pdf_text_command(38, 783, 11, 'FCG', 'F2', '1 1 1');
    }
    $headerX = $hasLogo ? 218 : 82;
    $lines[] = fcg_pdf_text_command($headerX, 807, $hasLogo ? 11 : 16, fcg_value($company, 'name', 'Future Creative Group Pty Ltd'), 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command($headerX, 790, 7, fcg_value($company, 'slogan', 'Innovative Digital Solutions & Security Technology'), 'F1', '0.79 0.89 1');
    $lines[] = fcg_pdf_text_command($headerX, 765, 7, fcg_value($company, 'website', 'www.futurecreativegroup.co.za') . '  |  ' . fcg_value($company, 'phone', '011 568 0279'), 'F1', '1 1 1');
    $lines[] = fcg_pdf_text_command($headerX, 750, 7, fcg_value($company, 'email', 'info@futurecreativegroup.co.za'), 'F1', '0.79 0.89 1');
    $titleLength = strlen($title);
    $titleSize = $titleLength > 16 ? 12 : ($titleLength > 10 ? 15 : 22);
    $lines[] = fcg_pdf_text_command(405, 792, $titleSize, $title, 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(425, 774, 7, strtoupper($theme['name']), 'F1', '0.79 0.89 1');
    $lines[] = fcg_pdf_rect(425, 738, 136, 22, $theme['accent']);
    $lines[] = fcg_pdf_text_command(438, 745, 9, strtoupper($badge), 'F2', '1 1 1');
    if ($title === 'RECEIPT') $number = fcg_value($document, 'receipt_no', fcg_value($document, 'invoice_no', ''));
    elseif ($title === 'QUOTATION') $number = fcg_value($document, 'quote_no', '');
    elseif ($title === 'PRO-FORMA INVOICE') $number = fcg_value($document, 'proforma_no', '');
    elseif ($title === 'CREDIT NOTE') $number = fcg_value($document, 'credit_note_no', '');
    elseif ($title === 'STATEMENT') $number = fcg_value($document, 'statement_no', '');
    else $number = fcg_value($document, 'invoice_no', '');
    $date = fcg_value($document, 'quote_date', fcg_value($document, 'invoice_date', ''));
    $due = fcg_value($document, 'valid_until', fcg_value($document, 'due_date', ''));
    $lines[] = fcg_pdf_rect(30, 615, 258, 88, $theme['pale']);
    $lines[] = fcg_pdf_rect(307, 615, 258, 88, $theme['pale']);
    $lines[] = fcg_pdf_text_command(44, 682, 8, 'CLIENT DETAILS', 'F2', $theme['accent']);
    $lines[] = fcg_pdf_text_command(44, 663, 10, fcg_value($document, 'client_name', 'Client'), 'F2', $theme['ink']);
    $lines[] = fcg_pdf_text_command(44, 648, 8, fcg_value($document, 'client_company', ''), 'F1', $theme['muted']);
    $lines[] = fcg_pdf_text_command(44, 634, 7, fcg_value($document, 'client_email', '') . '  ' . fcg_value($document, 'client_phone', ''), 'F1', $theme['muted']);
    $lines[] = fcg_pdf_text_command(44, 621, 7, substr(fcg_value($document, 'client_address', ''), 0, 54), 'F1', $theme['muted']);
    $lines[] = fcg_pdf_text_command(321, 682, 8, 'DOCUMENT DETAILS', 'F2', $theme['accent']);
    $lines[] = fcg_pdf_text_command(321, 663, 9, 'Number  ' . $number, 'F2', $theme['ink']);
    $lines[] = fcg_pdf_text_command(321, 648, 8, 'Date  ' . $date, 'F1', $theme['muted']);
    $lines[] = fcg_pdf_text_command(321, 634, 8, ($isQuote ? 'Valid until  ' : 'Due date  ') . $due, 'F1', $theme['muted']);
    $summary = fcg_value($document, 'executive_summary', fcg_value($document, 'notes', fcg_value($document, 'title', '')));
    $summaryLines = fcg_pdf_short_lines($summary, 105, 2);
    $lines[] = fcg_pdf_text_command(30, 592, 8, $isQuote ? 'EXECUTIVE SUMMARY' : 'SERVICE SUMMARY', 'F2', $theme['accent']);
    $summaryY = 577;
    foreach ($summaryLines as $summaryLine) {
        $lines[] = fcg_pdf_text_command(30, $summaryY, 7, $summaryLine, 'F1', $theme['muted']);
        $summaryY -= 12;
    }
    $lines[] = fcg_pdf_rect(30, 516, 535, 26, $theme['secondary']);
    $lines[] = fcg_pdf_text_command(40, 525, 7, 'ITEM', 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(76, 525, 7, 'DESCRIPTION', 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(349, 525, 7, 'QTY', 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(388, 525, 7, 'UNIT PRICE', 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(458, 525, 7, 'DISCOUNT', 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(518, 525, 7, 'TOTAL', 'F2', '1 1 1');
    $y = 489;
    foreach ($items as $itemIndex => $item) {
        if ($y < 365) {
            break;
        }
        $qty = fcg_money_value(fcg_value($item, 'quantity', 1));
        $unit = fcg_money_value(fcg_value($item, 'unit_price', 0));
        $discount = fcg_money_value(fcg_value($item, 'discount', 0));
        $lineTotal = $title === 'STATEMENT' ? (($qty * $unit) - $discount) : max(0, ($qty * $unit) - $discount);
        $description = substr(fcg_value($item, 'description', 'Service'), 0, 54);
        $rowColor = $itemIndex % 2 === 0 ? $theme['pale'] : '1 1 1';
        $lines[] = fcg_pdf_rect(30, $y - 8, 535, 27, $rowColor);
        $lines[] = fcg_pdf_text_command(42, $y, 7, str_pad((string) ($itemIndex + 1), 2, '0', STR_PAD_LEFT), 'F2', $theme['accent']);
        $lines[] = fcg_pdf_text_command(76, $y, 7, $description, 'F1', $theme['ink']);
        $lines[] = fcg_pdf_text_command(350, $y, 7, $qty, 'F1', $theme['ink']);
        $lines[] = fcg_pdf_text_command(384, $y, 7, fcg_money_format($unit), 'F1', $theme['ink']);
        $lines[] = fcg_pdf_text_command(459, $y, 7, fcg_money_format($discount), 'F1', $theme['ink']);
        $lines[] = fcg_pdf_text_command(513, $y, 7, fcg_money_format($lineTotal), 'F2', $theme['ink']);
        $y -= 28;
    }
    $lines[] = fcg_pdf_rect(350, 202, 215, 142, $theme['primary']);
    $lines[] = fcg_pdf_text_command(366, 326, 8, 'FINANCIAL SUMMARY', 'F2', '0.72 0.86 1');
    if ($title === 'CREDIT NOTE') {
        $summaryRows = [
            ['Subtotal', fcg_value($document, 'subtotal', 0)], ['Discount', fcg_value($document, 'discount', 0)],
            ['VAT', fcg_value($document, 'vat', 0)], ['Account Credit', fcg_value($document, 'credit_amount', fcg_value($document, 'total', 0))], ['Balance Due', 0],
        ];
    } elseif ($title === 'STATEMENT') {
        $summaryRows = [
            ['Total Invoiced', fcg_value($document, 'total_invoiced', 0)], ['Payments', fcg_value($document, 'total_paid', 0)],
            ['Credits', fcg_value($document, 'total_credits', 0)], ['Account Activity', fcg_value($document, 'total', 0)], ['Balance Due', fcg_value($document, 'balance_due', 0)],
        ];
    } else {
        $summaryRows = [
            ['Subtotal', fcg_value($document, 'subtotal', 0)], ['Discount', fcg_value($document, 'discount', 0)],
            ['VAT', fcg_value($document, 'vat', 0)], ['Amount Paid', fcg_value($document, 'amount_paid', 0)], ['Balance Due', fcg_value($document, 'balance_due', fcg_value($document, 'total', 0))],
        ];
    }
    $summaryY = 306;
    foreach ($summaryRows as $summaryRow) {
        $lines[] = fcg_pdf_text_command(366, $summaryY, 7, $summaryRow[0], 'F1', '0.78 0.86 0.93');
        $lines[] = fcg_pdf_text_command(485, $summaryY, 8, fcg_money_format($summaryRow[1]), 'F2', '1 1 1');
        $summaryY -= 17;
    }
    $lines[] = fcg_pdf_rect(350, 202, 215, 34, $theme['accent']);
    $grandLabel = $title === 'CREDIT NOTE' ? 'TOTAL CREDIT' : ($title === 'STATEMENT' ? 'BALANCE DUE' : 'GRAND TOTAL');
    $grandValue = $title === 'CREDIT NOTE' ? fcg_value($document, 'credit_amount', fcg_value($document, 'total', 0)) : ($title === 'STATEMENT' ? fcg_value($document, 'balance_due', 0) : fcg_value($document, 'total', 0));
    $lines[] = fcg_pdf_text_command(366, 214, 9, $grandLabel, 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(475, 212, 14, fcg_money_format($grandValue), 'F2', '1 1 1');
    $lines[] = fcg_pdf_rect(30, 250, 295, 94, $theme['pale']);
    $cardTitle = $title === 'CREDIT NOTE' ? 'ACCOUNT CREDIT' : ($title === 'STATEMENT' ? 'ACCOUNT SUMMARY' : ($isQuote ? 'CLIENT APPROVAL' : strtoupper(in_array($badge, ['Paid', 'Paid In Full'], true) ? 'PAID IN FULL' : ($badge === 'Partially Paid' ? 'PARTIALLY PAID' : 'TOTAL DUE'))));
    $cardText = $isQuote
        ? (fcg_value($document, 'digital_signature', '') !== '' ? 'Approved by ' . fcg_value($document, 'digital_signature', '') . ' on ' . fcg_value($document, 'acceptance_date', '') : 'Secure digital acceptance is recorded through the client portal.')
        : ($title === 'CREDIT NOTE' ? fcg_value($document, 'reason', 'Credit applied to the customer account.') : ($title === 'STATEMENT' ? 'Account activity for the selected statement period.' : (in_array($badge, ['Paid', 'Paid In Full'], true) ? 'Payment received and verified.' : ($badge === 'Partially Paid' ? 'Outstanding balance remaining.' : 'Awaiting payment by the due date.'))));
    $lines[] = fcg_pdf_text_command(46, 323, 9, $cardTitle, 'F2', $theme['accent']);
    $lines[] = fcg_pdf_text_command(46, 301, 8, $cardText, 'F1', $theme['ink']);
    if ($isQuote) {
        $lines[] = fcg_pdf_text_command(46, 276, 7, 'Signature: ' . fcg_value($document, 'digital_signature', 'Pending secure approval'), 'F1', $theme['muted']);
    } else {
        $lines[] = fcg_pdf_text_command(46, 276, 7, 'Payment method: ' . fcg_value($document, 'payment_method', 'Bank transfer'), 'F1', $theme['muted']);
        $lines[] = fcg_pdf_text_command(46, 260, 7, 'Deposit: ' . fcg_money_format(fcg_value($document, 'deposit_paid', 0)) . ' / ' . fcg_money_format(fcg_value($document, 'deposit_required', 0)) . '  |  Final: ' . fcg_money_format(fcg_value($document, 'final_payment', 0)), 'F1', $theme['muted']);
    }
    $lines[] = fcg_pdf_rect(30, 92, 295, 138, $theme['pale']);
    $lines[] = fcg_pdf_text_command(46, 211, 8, 'BANKING DETAILS', 'F2', $theme['accent']);
    $paymentReference = fcg_value($banking, 'payment_reference', 'Please use your invoice number or company name as the payment reference.');
    $lines[] = fcg_pdf_text_command(46, 190, 7, 'Bank  ' . fcg_value($banking, 'bank', 'First National Bank / FNB'), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 176, 7, 'Holder  ' . substr(fcg_value($banking, 'account_holder', ''), 0, 42), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 162, 7, 'Type  ' . fcg_value($banking, 'account_type', 'Gold Business Account'), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 148, 7, 'Account  ' . fcg_value($banking, 'account_number', ''), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 134, 7, 'Branch  ' . fcg_value($banking, 'branch_code', '') . ' - ' . substr(fcg_value($banking, 'branch_name', ''), 0, 28), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 120, 7, 'SWIFT  ' . fcg_value($banking, 'swift_code', ''), 'F1', $theme['ink']);
    $lines[] = fcg_pdf_text_command(46, 102, 7, 'Reference  ' . ($number !== '' ? $number : 'Invoice number or company name'), 'F2', $theme['secondary']);
    $lines[] = fcg_pdf_text_command(46, 94, 5, substr($paymentReference, 0, 80), 'F1', $theme['muted']);
    $lines[] = '0.55 0.62 0.68 RG 1 w 260 120 44 44 re S' . "\n";
    $lines[] = fcg_pdf_text_command(269, 139, 6, 'PAY', 'F2', $theme['muted']);
    $termsText = fcg_value($document, 'terms', fcg_value($document, 'notes', ''));
    if ($isQuote) {
        $scope = trim(fcg_value($document, 'project_scope', ''));
        $included = trim(fcg_value($document, 'included_services', ''));
        $termsText = trim(($scope !== '' ? 'Scope: ' . $scope . ' ' : '') . ($included !== '' ? 'Included: ' . $included . ' ' : '') . $termsText);
    }
    $terms = fcg_pdf_short_lines($termsText, 47, 6);
    $lines[] = fcg_pdf_text_command(350, 178, 8, $isQuote ? 'TERMS & PROJECT SCOPE' : 'TERMS & PAYMENT NOTES', 'F2', $theme['accent']);
    $termsY = 160;
    foreach ($terms as $termLine) {
        $lines[] = fcg_pdf_text_command(350, $termsY, 6, $termLine, 'F1', $theme['muted']);
        $termsY -= 12;
    }
    $lines[] = fcg_pdf_rect(0, 0, 595, 58, $theme['primary']);
    $lines[] = fcg_pdf_text_command(30, 34, 8, fcg_value($company, 'name', 'Future Creative Group Pty Ltd'), 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(30, 20, 7, fcg_value($company, 'slogan', 'Innovative Digital Solutions & Security Technology'), 'F1', '0.76 0.87 1');
    $lines[] = fcg_pdf_text_command(375, 30, 7, fcg_value($company, 'website', 'www.futurecreativegroup.co.za'), 'F2', '1 1 1');
    $lines[] = fcg_pdf_text_command(375, 16, 7, fcg_value($settings, 'footer_notes', 'Connecting Your Business To The Future'), 'F1', '0.76 0.87 1');

    $content = implode('', $lines);
    $pageResources = '<< /Font << /F1 5 0 R /F2 6 0 R >>' . ($hasLogo ? ' /XObject << /Im1 7 0 R >>' : '') . ' >>';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $pageResources . ' /Contents 4 0 R >>',
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    ];
    if ($hasLogo) {
        $objects[] = '<< /Type /XObject /Subtype /Image /Width 500 /Height 250 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logoBytes) . ">>\nstream\n" . $logoBytes . "\nendstream";
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

function fcg_client_records($records, $user, $allowGlobal = false, $businessUnit = null)
{
    $businessUnit = $businessUnit ? fcg_normalize_business_unit($businessUnit) : fcg_active_business_unit($user);
    if (fcg_value($user, 'role', '') === 'admin') {
        return array_values(array_filter($records, function ($record) use ($businessUnit) {
            return fcg_record_matches_business_unit($record, $businessUnit);
        }));
    }
    return array_values(array_filter($records, function ($record) use ($user, $allowGlobal, $businessUnit) {
        $recordClient = (int) fcg_value($record, 'client_id', 0);
        return fcg_record_matches_business_unit($record, $businessUnit)
            && ($recordClient === (int) $user['id'] || ($allowGlobal && $recordClient === 0));
    }));
}

function fcg_support_categories($unit)
{
    $unit = fcg_normalize_business_unit($unit);
    if ($unit === 'fcg_cloud') {
        return [
            'Website Hosting',
            'Business Email',
            'Domain Registration',
            'DNS',
            'SSL',
            'DirectAdmin',
            'Webmail',
            'Outlook Setup',
            'Mobile Email Setup',
            'Website Migration',
            'Email Migration',
            'Billing',
            'Renewal',
            'Other',
        ];
    }
    return ['CCTV / Security', 'IT Support', 'Networking / WiFi', 'VoIP / Landline', 'Website / Hosting', 'Billing / Quote', 'Other'];
}

function fcg_dashboard_navigation($unit)
{
    $unit = fcg_normalize_business_unit($unit);
    if ($unit === 'fcg_cloud') {
        return [
            'dashboard' => 'Dashboard',
            'managed' => 'My Services',
            'invoices' => 'Invoices',
            'payments' => 'Payments',
            'support' => 'Support Tickets',
            'documents' => 'Documents',
            'notifications' => 'Notifications',
            'profile' => 'Profile',
        ];
    }
    return [
        'dashboard' => 'Dashboard',
        'projects' => 'Projects',
        'quotes' => 'Quotations',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'managed' => 'Managed Services',
        'onboarding' => 'Onboarding',
        'support' => 'Support Tickets',
        'documents' => 'Documents',
        'notifications' => 'Notifications',
        'profile' => 'Profile',
        'feedback' => 'Feedback',
    ];
}

function fcg_cloud_dashboard_summary($invoices, $tickets, $subscriptions, $hostingRecords, $documents = [], $notifications = [])
{
    $outstanding = 0.0;
    $nextBilling = '';
    foreach ($invoices as $invoice) {
        $normal = fcg_normalize_invoice($invoice);
        $outstanding += fcg_money_value(fcg_value($normal, 'balance_due', 0));
        $due = fcg_value($normal, 'due_date', '');
        if ($due !== '' && ($nextBilling === '' || $due < $nextBilling) && !in_array(fcg_value($normal, 'payment_status', ''), ['Paid', 'Paid In Full', 'Cancelled'], true)) {
            $nextBilling = $due;
        }
    }
    foreach ($subscriptions as $subscription) {
        $date = fcg_value($subscription, 'next_billing_date', fcg_value($subscription, 'renewal_date', ''));
        if ($date !== '' && ($nextBilling === '' || $date < $nextBilling)) {
            $nextBilling = $date;
        }
    }
    $primaryHosting = $hostingRecords ? $hostingRecords[0] : [];
    $emailAccounts = array_reduce($hostingRecords, function ($total, $record) {
        return $total + (int) fcg_value($record, 'email_accounts', 0);
    }, 0);
    $openTickets = count(array_filter($tickets, function ($ticket) {
        return !in_array(fcg_value($ticket, 'status', 'Open'), ['Resolved', 'Closed'], true);
    }));
    return [
        'active_services' => count(array_filter($subscriptions, function ($subscription) {
            return fcg_value($subscription, 'status', 'Active') === 'Active';
        })),
        'hosting_package' => fcg_value($primaryHosting, 'hosting_package', fcg_value($primaryHosting, 'hosting_provider', fcg_value($subscriptions[0] ?? [], 'service_name', 'Not assigned'))),
        'domain_name' => fcg_value($primaryHosting, 'domain_name', 'Not assigned'),
        'domain_renewal_date' => fcg_value($primaryHosting, 'domain_renewal_date', fcg_value($primaryHosting, 'renewal_date', '')),
        'next_billing_date' => $nextBilling,
        'recent_documents' => count(array_slice($documents, 0, 5)),
        'recent_notifications' => count(array_slice($notifications, 0, 5)),
        'outstanding_balance' => fcg_money_format($outstanding),
        'payment_status' => $outstanding > 0 ? 'Payment due' : 'Paid',
        'ssl_status' => fcg_value($primaryHosting, 'ssl_status', 'Not recorded'),
        'backup_status' => fcg_value($primaryHosting, 'backup_status', 'Not recorded'),
        'email_accounts' => $emailAccounts,
        'open_support_tickets' => $openTickets,
        'directadmin_url' => fcg_value($primaryHosting, 'directadmin_url', 'https://panel.fcgcloud.co.za:2222'),
        'webmail_url' => fcg_value($primaryHosting, 'webmail_url', 'https://webmail.fcgcloud.co.za/roundcube'),
    ];
}

function fcg_public_document($document)
{
    if (!empty($document['file_path'])) {
        $document['url'] = fcg_portal_path('/api/documents/download?id=' . urlencode((string) $document['id']));
    }
    unset($document['file_path']);
    return $document;
}

function fcg_public_ticket($ticket)
{
    if (!empty($ticket['attachment_path'])) {
        $ticket['attachment_url'] = fcg_portal_path('/api/tickets/attachment?id=' . urlencode((string) fcg_value($ticket, 'id', 0)));
    }
    unset($ticket['attachment_path']);
    return $ticket;
}

function fcg_load_tickets()
{
    $data = fcg_load_portal_data();
    return $data['tickets'];
}

function fcg_send_notification($subject, $lines, $replyTo = '', $to = 'info@futurecreativegroup.co.za')
{
    $body = implode("\n", $lines);
    $headers = "From: Future Creative Group Portal <no-reply@futurecreativegroup.co.za>\r\n";
    if ($replyTo !== '') {
        $headers .= "Reply-To: " . $replyTo . "\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $sent = @mail($to, $subject, $body, $headers);
    $logLine = '[' . date('c') . '] ' . ($sent ? 'sent' : 'failed') . ' | ' . $subject . "\n";
    @file_put_contents(fcg_data_dir() . '/notifications.log', $logLine, FILE_APPEND | LOCK_EX);
    return $sent;
}

function fcg_send_customer_email($to, $subject, $lines, $businessUnit = 'future_creative_group')
{
    $result = fcg_send_customer_email_result($to, $subject, $lines, $businessUnit);
    return !empty($result['sent']);
}

function fcg_send_customer_email_result($to, $subject, $lines, $businessUnit = 'future_creative_group')
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'status' => 'Failed', 'error' => 'Invalid recipient'];
    }
    $businessUnit = fcg_normalize_business_unit($businessUnit);
    if ($businessUnit === 'both') {
        $businessUnit = 'future_creative_group';
    }
    $brand = fcg_brand_config($businessUnit);
    $body = implode("\n", $lines);
    $data = fcg_load_portal_data();
    $templates = fcg_templates_for_business_unit(fcg_value($data, 'templates', []), $businessUnit);
    $smtp = fcg_value($templates, 'smtp', []);
    $from = fcg_value($smtp, 'from_email', 'no-reply@futurecreativegroup.co.za');
    $replyTo = fcg_value(fcg_value($brand, 'contacts', []), 'general', 'info@futurecreativegroup.co.za');
    $autoload = fcg_first_file('vendor/autoload.php');
    if ($autoload && trim(fcg_value($smtp, 'host', '')) !== '') {
        require_once $autoload;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = trim(fcg_value($smtp, 'host', ''));
                $mailer->Port = max(1, (int) fcg_value($smtp, 'port', 587));
                $mailer->SMTPAuth = trim(fcg_value($smtp, 'username', '')) !== '';
                if ($mailer->SMTPAuth) {
                    $mailer->Username = fcg_value($smtp, 'username', '');
                    $mailer->Password = fcg_value($smtp, 'password', '');
                }
                $encryption = strtolower(trim(fcg_value($smtp, 'encryption', 'tls')));
                if ($encryption === 'ssl') $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                elseif ($encryption === 'tls') $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                else $mailer->SMTPSecure = '';
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($from, $brand['label']);
                $mailer->addReplyTo($replyTo, $brand['label']);
                $mailer->addAddress($to);
                $mailer->Subject = $subject;
                $mailer->Body = $body;
                $mailer->isHTML(false);
                $mailer->send();
                return ['sent' => true, 'status' => 'Sent', 'transport' => 'SMTP'];
            } catch (Throwable $error) {
                @file_put_contents(fcg_data_dir() . '/email-errors.log', '[' . date('c') . '] SMTP failed | ' . $subject . ' | ' . $error->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                return ['sent' => false, 'status' => 'Failed', 'transport' => 'SMTP'];
            }
        }
    }
    $headers = 'From: ' . $brand['label'] . ' <' . $from . ">\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $sent = @mail($to, $subject, $body, $headers);
    return ['sent' => $sent, 'status' => $sent ? 'Sent' : 'Failed', 'transport' => 'PHP mail'];
}

function fcg_payment_message_lines($settings, $number)
{
    $banking = fcg_value($settings, 'banking', []);
    $reference = $number !== '' ? $number : 'invoice number or company name';
    return [
        '',
        'Banking Details:',
        'Bank: ' . fcg_value($banking, 'bank', 'First National Bank / FNB'),
        'Account Holder: ' . fcg_value($banking, 'account_holder', 'Future Creative Group (Pty) Ltd'),
        'Account Type: ' . fcg_value($banking, 'account_type', 'Gold Business Account'),
        'Account Number: ' . fcg_value($banking, 'account_number', '63215619909'),
        'Branch Code: ' . fcg_value($banking, 'branch_code', '200632'),
        'Branch Name: ' . fcg_value($banking, 'branch_name', 'PHUMULANI SERVICE BRANCH'),
        'SWIFT Code: ' . fcg_value($banking, 'swift_code', 'FIRNZAJJ'),
        'Payment Reference: ' . $reference,
        fcg_value($banking, 'payment_reference', 'Please use your invoice number or company name as the payment reference.'),
    ];
}

function fcg_customer_phone($phone)
{
    $digits = preg_replace('/[^0-9]+/', '', (string) $phone);
    if (strpos($digits, '0') === 0) {
        $digits = '27' . substr($digits, 1);
    }
    return $digits;
}

function fcg_notify_ticket($ticket, $user)
{
    $requestType = fcg_value($ticket, 'request_type', 'Support');
    $businessUnit = fcg_record_business_unit($ticket);
    $brand = fcg_brand_config($businessUnit);
    $contacts = fcg_value($brand, 'contacts', []);
    $departmentEmail = stripos($requestType, 'Billing') !== false || stripos($requestType, 'Renewal') !== false
        ? fcg_value($contacts, 'billing', 'account@futurecreativegroup.co.za')
        : fcg_value($contacts, 'support', 'support@futurecreativegroup.co.za');
    return fcg_send_notification(
        $brand['label'] . ' Support Request: ' . $requestType,
        [
            'New client portal support request',
            '',
            'Client: ' . fcg_value($user, 'name', 'Client') . ' <' . fcg_value($user, 'email', '') . '>',
            'Type: ' . fcg_value($ticket, 'request_type', ''),
            'Priority: ' . fcg_value($ticket, 'priority', ''),
            'Status: ' . fcg_value($ticket, 'status', ''),
            'Ticket ID: ' . fcg_value($ticket, 'id', ''),
            '',
            fcg_value($ticket, 'message', ''),
            '',
            'Portal: ' . fcg_value($brand, 'portal_url', 'https://futurecreativegroup.co.za/portal/'),
        ],
        fcg_value($user, 'email', ''),
        $departmentEmail
    );
}

function fcg_notify_client_created($client)
{
    $businessUnit = fcg_user_business_unit($client);
    if ($businessUnit === 'both') {
        $businessUnit = 'future_creative_group';
    }
    $brand = fcg_brand_config($businessUnit);
    return fcg_send_notification(
        'New ' . $brand['label'] . ' Portal Account: ' . fcg_value($client, 'name', 'Client'),
        [
            'A new ' . $brand['label'] . ' client portal account was created.',
            '',
            'Client: ' . fcg_value($client, 'name', ''),
            'Company: ' . fcg_value($client, 'company', ''),
            'Email: ' . fcg_value($client, 'email', ''),
            '',
            'Portal: ' . $brand['portal_url'],
        ],
        fcg_value($client, 'email', ''),
        fcg_value(fcg_value($brand, 'contacts', []), 'general', 'admin@futurecreativegroup.co.za')
    );
}

function fcg_notify_business_event($subject, $record, $client)
{
    $businessUnit = fcg_record_business_unit($record, fcg_user_business_unit($client));
    if ($businessUnit === 'both') {
        $businessUnit = 'future_creative_group';
    }
    $brand = fcg_brand_config($businessUnit);
    $contacts = fcg_value($brand, 'contacts', []);
    $departmentEmail = fcg_value($contacts, 'general', 'admin@futurecreativegroup.co.za');
    if (stripos($subject, 'quotation') !== false || stripos($subject, 'quote') !== false) {
        $departmentEmail = fcg_value($contacts, 'sales', $departmentEmail);
    } elseif (stripos($subject, 'invoice') !== false || stripos($subject, 'payment') !== false || stripos($subject, 'receipt') !== false) {
        $departmentEmail = fcg_value($contacts, 'billing', $departmentEmail);
    }
    return fcg_send_notification(
        $subject,
        [
            $subject,
            '',
            'Client: ' . fcg_value($client, 'name', 'Client') . ' <' . fcg_value($client, 'email', '') . '>',
            'Company: ' . fcg_value($client, 'company', ''),
            'Reference: ' . fcg_value($record, 'quote_no', fcg_value($record, 'invoice_no', '')),
            'Status: ' . fcg_value($record, 'status', fcg_value($record, 'payment_status', '')),
            'Total: ' . fcg_money_format(fcg_value($record, 'total', 0)),
            '',
            'Portal: ' . $brand['portal_url'],
        ],
        fcg_value($client, 'email', ''),
        $departmentEmail
    );
}

function fcg_document_customer($input, &$data, $existing = null)
{
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $customerType = strtolower(trim(fcg_value($input, 'customer_type', fcg_value($existing, 'customer_type', 'registered'))));
    if (!in_array($customerType, ['guest', 'unregistered', 'walk-in'], true)) {
        $clientId = (int) fcg_value($input, 'client_id', fcg_value($existing, 'client_id', 0));
        if (!fcg_client_exists($clientId)) {
            fcg_json(['error' => 'Please select a valid active client'], 400);
        }
        if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
            fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
        }
        $client = fcg_client_contact($clientId);
        return [
            'customer_type' => 'registered',
            'client_id' => $clientId,
            'guest_customer_id' => 0,
            'name' => fcg_value($client, 'name', ''),
            'company' => fcg_value($client, 'company', ''),
            'email' => fcg_value($client, 'email', ''),
            'phone' => fcg_value($client, 'phone', fcg_value($existing, 'client_phone', '')),
            'address' => fcg_value($client, 'address', fcg_value($existing, 'client_address', '')),
            'vat_number' => fcg_value($client, 'vat_number', fcg_value($existing, 'customer_vat_number', '')),
            'notes' => fcg_value($existing, 'customer_notes', ''),
        ];
    }

    $guestId = (int) fcg_value($input, 'guest_customer_id', fcg_value($existing, 'guest_customer_id', 0));
    $guestIndex = $guestId > 0 ? fcg_find_record_index($data['guest_customers'], $guestId) : -1;
    $current = $guestIndex >= 0 ? $data['guest_customers'][$guestIndex] : [];
    $name = trim(fcg_value($input, 'guest_name', fcg_value($input, 'client_name', fcg_value($current, 'name', fcg_value($existing, 'client_name', '')))));
    $email = strtolower(trim(fcg_value($input, 'guest_email', fcg_value($input, 'client_email', fcg_value($current, 'email', fcg_value($existing, 'client_email', ''))))));
    if ($name === '') {
        fcg_json(['error' => 'Guest customer name is required'], 400);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fcg_json(['error' => 'Enter a valid guest customer email address'], 400);
    }
    $guest = [
        'id' => $guestIndex >= 0 ? (int) $current['id'] : fcg_next_record_id($data['guest_customers']),
        'business_unit' => $businessUnit,
        'name' => $name,
        'company' => trim(fcg_value($input, 'guest_company', fcg_value($input, 'client_company', fcg_value($current, 'company', fcg_value($existing, 'client_company', ''))))),
        'email' => $email,
        'phone' => trim(fcg_value($input, 'guest_phone', fcg_value($input, 'client_phone', fcg_value($current, 'phone', fcg_value($existing, 'client_phone', ''))))),
        'address' => trim(fcg_value($input, 'guest_address', fcg_value($input, 'client_address', fcg_value($current, 'address', fcg_value($existing, 'client_address', ''))))),
        'vat_number' => trim(fcg_value($input, 'guest_vat_number', fcg_value($current, 'vat_number', fcg_value($existing, 'customer_vat_number', '')))),
        'notes' => trim(fcg_value($input, 'guest_notes', fcg_value($current, 'notes', fcg_value($existing, 'customer_notes', '')))),
        'pipeline_stage' => fcg_value($input, 'pipeline_stage', fcg_value($current, 'pipeline_stage', 'New Lead')),
        'internal_notes' => trim(fcg_value($input, 'internal_notes', fcg_value($current, 'internal_notes', ''))),
        'status' => fcg_value($current, 'status', 'Guest'),
        'updated_at' => date('c'),
        'created_at' => fcg_value($current, 'created_at', date('c')),
    ];
    if ($guestIndex >= 0) {
        $data['guest_customers'][$guestIndex] = $guest;
    } else {
        array_unshift($data['guest_customers'], $guest);
        fcg_log_activity($data, 'guest', 'Guest customer ' . $guest['name'] . ' created');
    }
    return [
        'customer_type' => 'guest',
        'client_id' => 0,
        'guest_customer_id' => $guest['id'],
        'name' => $guest['name'],
        'company' => $guest['company'],
        'email' => $guest['email'],
        'phone' => $guest['phone'],
        'address' => $guest['address'],
        'vat_number' => $guest['vat_number'],
        'notes' => $guest['notes'],
    ];
}

function fcg_quote_from_input($input, &$data, $existing = null)
{
    $customer = fcg_document_customer($input, $data, $existing);
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $unitTemplates = fcg_templates_for_business_unit($data['templates'], $businessUnit);
    $brand = fcg_brand_config($businessUnit);
    $clientId = $customer['client_id'];
    $items = fcg_normalize_items($input);
    $totals = fcg_document_totals($items, fcg_bool_value(fcg_value($input, 'vat_enabled', fcg_value($input, 'vat', fcg_value($existing, 'vat_enabled', false)))));
    $title = trim(fcg_value($input, 'title', fcg_value($input, 'project_title', fcg_value($existing, 'title', ''))));
    if ($title === '') {
        fcg_json(['error' => 'Service or project title is required'], 400);
    }
    $quoteNo = trim(fcg_value($input, 'quote_no', fcg_value($existing, 'quote_no', '')));
    if ($quoteNo === '') {
        $quoteNo = fcg_business_number($data['quotes'], fcg_value(fcg_value($unitTemplates, 'prefixes', []), 'quote', 'FCG/QT'), 'quote_no');
    }
    return [
        'id' => $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data['quotes']),
        'business_unit' => $businessUnit,
        'client_id' => $clientId,
        'customer_type' => $customer['customer_type'],
        'guest_customer_id' => $customer['guest_customer_id'],
        'quote_no' => $quoteNo,
        'quote_date' => fcg_value($input, 'quote_date', fcg_value($existing, 'quote_date', date('Y-m-d'))),
        'valid_until' => fcg_value($input, 'valid_until', fcg_value($existing, 'valid_until', date('Y-m-d', strtotime('+14 days')))),
        'client_name' => $customer['name'],
        'client_company' => $customer['company'],
        'client_email' => $customer['email'],
        'client_phone' => $customer['phone'],
        'client_address' => $customer['address'],
        'customer_vat_number' => $customer['vat_number'],
        'customer_notes' => $customer['notes'],
        'title' => $title,
        'executive_summary' => trim(fcg_value($input, 'executive_summary', fcg_value($existing, 'executive_summary', ''))),
        'project_scope' => trim(fcg_value($input, 'project_scope', fcg_value($existing, 'project_scope', ''))),
        'included_services' => trim(fcg_value($input, 'included_services', fcg_value($existing, 'included_services', ''))),
        'items' => $items,
        'subtotal' => $totals['subtotal'],
        'discount' => $totals['discount'],
        'vat_enabled' => $totals['vat_enabled'],
        'vat' => $totals['vat'],
        'total' => $totals['total'],
        'amount' => $totals['amount'],
        'status' => fcg_value($input, 'status', fcg_value($existing, 'status', 'Draft')),
        'notes' => fcg_value($input, 'notes', fcg_value($existing, 'notes', '')),
        'terms' => fcg_value($input, 'terms', fcg_value($existing, 'terms', fcg_value(fcg_value($unitTemplates, 'terms', []), 'quote', fcg_default_templates()['terms']['quote']))),
        'prepared_by' => fcg_value($input, 'prepared_by', fcg_value($existing, 'prepared_by', $brand['label'])),
        'approval_status' => fcg_value($input, 'approval_status', fcg_value($existing, 'approval_status', fcg_value($input, 'status', 'Draft'))),
        'digital_signature' => trim(fcg_value($input, 'digital_signature', fcg_value($existing, 'digital_signature', ''))),
        'acceptance_date' => fcg_value($input, 'acceptance_date', fcg_value($existing, 'acceptance_date', '')),
        'approval_record' => trim(fcg_value($input, 'approval_record', fcg_value($existing, 'approval_record', ''))),
        'updated_at' => date('c'),
        'created_at' => fcg_value($existing, 'created_at', date('c')),
    ];
}

function fcg_invoice_from_input($input, &$data, $existing = null)
{
    $customer = fcg_document_customer($input, $data, $existing);
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $unitTemplates = fcg_templates_for_business_unit($data['templates'], $businessUnit);
    $clientId = $customer['client_id'];
    $items = fcg_normalize_items($input);
    $totals = fcg_document_totals($items, fcg_bool_value(fcg_value($input, 'vat_enabled', fcg_value($input, 'vat', fcg_value($existing, 'vat_enabled', false)))));
    $title = trim(fcg_value($input, 'title', fcg_value($input, 'project_title', fcg_value($existing, 'title', ''))));
    if ($title === '') {
        fcg_json(['error' => 'Project or service title is required'], 400);
    }
    $invoiceNo = trim(fcg_value($input, 'invoice_no', fcg_value($existing, 'invoice_no', '')));
    if ($invoiceNo === '') {
        $invoiceNo = fcg_business_number($data['invoices'], fcg_value(fcg_value($unitTemplates, 'prefixes', []), 'invoice', 'FCG/INV'), 'invoice_no');
    }
    $amountPaid = fcg_money_value(fcg_value($input, 'amount_paid', fcg_value($existing, 'amount_paid', 0)));
    $balance = max(0, $totals['total'] - $amountPaid);
    $paymentStatus = fcg_value($input, 'payment_status', fcg_value($existing, 'payment_status', 'Unpaid'));
    if ($amountPaid >= $totals['total'] && $totals['total'] > 0) {
        $paymentStatus = 'Paid In Full';
    } elseif ($amountPaid > 0) {
        $paymentStatus = 'Partially Paid';
    }
    return [
        'id' => $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data['invoices']),
        'business_unit' => $businessUnit,
        'client_id' => $clientId,
        'customer_type' => $customer['customer_type'],
        'guest_customer_id' => $customer['guest_customer_id'],
        'quote_id' => fcg_value($input, 'quote_id', fcg_value($existing, 'quote_id', 0)),
        'invoice_no' => $invoiceNo,
        'invoice_date' => fcg_value($input, 'invoice_date', fcg_value($existing, 'invoice_date', date('Y-m-d'))),
        'due_date' => fcg_value($input, 'due_date', fcg_value($existing, 'due_date', date('Y-m-d', strtotime('+7 days')))),
        'client_name' => $customer['name'],
        'client_company' => $customer['company'],
        'client_email' => $customer['email'],
        'client_phone' => $customer['phone'],
        'client_address' => $customer['address'],
        'customer_vat_number' => $customer['vat_number'],
        'customer_notes' => $customer['notes'],
        'title' => $title,
        'items' => $items,
        'subtotal' => $totals['subtotal'],
        'discount' => $totals['discount'],
        'vat_enabled' => $totals['vat_enabled'],
        'vat' => $totals['vat'],
        'total' => $totals['total'],
        'amount' => $totals['amount'],
        'amount_paid' => $amountPaid,
        'balance_due' => $balance,
        'deposit_required' => fcg_money_value(fcg_value($input, 'deposit_required', fcg_value($existing, 'deposit_required', 0))),
        'deposit_paid' => fcg_money_value(fcg_value($input, 'deposit_paid', fcg_value($existing, 'deposit_paid', 0))),
        'milestone_payments' => fcg_array_input(fcg_value($input, 'milestone_payments', fcg_value($existing, 'milestone_payments', []))),
        'final_payment' => fcg_money_value(fcg_value($input, 'final_payment', fcg_value($existing, 'final_payment', 0))),
        'payment_history' => fcg_array_input(fcg_value($input, 'payment_history', fcg_value($existing, 'payment_history', []))),
        'payment_status' => $paymentStatus,
        'payment_date' => fcg_value($input, 'payment_date', fcg_value($existing, 'payment_date', '')),
        'payment_method' => fcg_value($input, 'payment_method', fcg_value($existing, 'payment_method', '')),
        'notes' => fcg_value($input, 'notes', fcg_value($existing, 'notes', '')),
        'terms' => fcg_value($input, 'terms', fcg_value($existing, 'terms', fcg_value(fcg_value($unitTemplates, 'terms', []), 'invoice', fcg_default_templates()['terms']['invoice']))),
        'proof_path' => fcg_value($existing, 'proof_path', ''),
        'proof_file' => fcg_value($existing, 'proof_file', ''),
        'updated_at' => date('c'),
        'created_at' => fcg_value($existing, 'created_at', date('c')),
    ];
}

function fcg_subscription_from_input($input, $data, $existing = null)
{
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $clientId = (int) fcg_value($input, 'client_id', fcg_value($existing, 'client_id', 0));
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid active client'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $serviceName = trim(fcg_value($input, 'service_name', fcg_value($existing, 'service_name', '')));
    if ($serviceName === '') {
        fcg_json(['error' => 'Recurring service name is required'], 400);
    }
    return [
        'id' => $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data['subscriptions']),
        'business_unit' => $businessUnit,
        'client_id' => $clientId,
        'service_id' => (int) fcg_value($input, 'service_id', fcg_value($existing, 'service_id', 0)),
        'service_name' => $serviceName,
        'package' => trim(fcg_value($input, 'package', fcg_value($existing, 'package', $serviceName))),
        'domain_name' => strtolower(trim(fcg_value($input, 'domain_name', fcg_value($input, 'domain', fcg_value($existing, 'domain_name', ''))))),
        'description' => trim(fcg_value($input, 'description', fcg_value($existing, 'description', ''))),
        'amount' => fcg_money_value(fcg_value($input, 'amount', fcg_value($input, 'monthly_amount', fcg_value($existing, 'amount', 0)))),
        'monthly_amount' => fcg_money_value(fcg_value($input, 'monthly_amount', fcg_value($input, 'amount', fcg_value($existing, 'monthly_amount', fcg_value($existing, 'amount', 0))))),
        'billing_cycle' => fcg_value($input, 'billing_cycle', fcg_value($existing, 'billing_cycle', 'Monthly')),
        'start_date' => fcg_value($input, 'start_date', fcg_value($existing, 'start_date', date('Y-m-d'))),
        'next_billing_date' => fcg_value($input, 'next_billing_date', fcg_value($existing, 'next_billing_date', date('Y-m-d', strtotime('+1 month')))),
        'renewal_date' => fcg_value($input, 'renewal_date', fcg_value($existing, 'renewal_date', '')),
        'status' => fcg_value($input, 'status', fcg_value($existing, 'status', 'Active')),
        'auto_invoice' => fcg_bool_value(fcg_value($input, 'auto_invoice', fcg_value($existing, 'auto_invoice', true))),
        'automatic_invoice_status' => fcg_value($input, 'automatic_invoice_status', fcg_value($existing, 'automatic_invoice_status', 'Enabled')),
        'notes' => trim(fcg_value($input, 'notes', fcg_value($existing, 'notes', ''))),
        'updated_at' => date('c'),
        'created_at' => fcg_value($existing, 'created_at', date('c')),
    ];
}

function fcg_hosting_record_from_input($input, $data, $existing = null)
{
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $clientId = (int) fcg_value($input, 'client_id', fcg_value($existing, 'client_id', 0));
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid active client'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $domain = strtolower(trim(fcg_value($input, 'domain_name', fcg_value($existing, 'domain_name', ''))));
    if ($domain === '') {
        fcg_json(['error' => 'Domain name is required'], 400);
    }
    $brand = fcg_brand_config($businessUnit);
    $quickLinks = fcg_value($brand, 'quick_links', []);
    return [
        'id' => $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data['hosting_records']),
        'business_unit' => $businessUnit,
        'client_id' => $clientId,
        'domain_name' => $domain,
        'hosting_package' => trim(fcg_value($input, 'hosting_package', fcg_value($input, 'package', fcg_value($existing, 'hosting_package', '')))),
        'hosting_provider' => trim(fcg_value($input, 'hosting_provider', fcg_value($existing, 'hosting_provider', $businessUnit === 'fcg_cloud' ? 'FCG Cloud' : ''))),
        'server_hostname' => trim(fcg_value($input, 'server_hostname', fcg_value($existing, 'server_hostname', ''))),
        'directadmin_username' => trim(fcg_value($input, 'directadmin_username', fcg_value($existing, 'directadmin_username', ''))),
        'hosting_start_date' => fcg_value($input, 'hosting_start_date', fcg_value($existing, 'hosting_start_date', date('Y-m-d'))),
        'renewal_date' => fcg_value($input, 'renewal_date', fcg_value($existing, 'renewal_date', '')),
        'next_billing_date' => fcg_value($input, 'next_billing_date', fcg_value($existing, 'next_billing_date', '')),
        'domain_renewal_date' => fcg_value($input, 'domain_renewal_date', fcg_value($existing, 'domain_renewal_date', fcg_value($input, 'renewal_date', fcg_value($existing, 'renewal_date', '')))),
        'ssl_status' => fcg_value($input, 'ssl_status', fcg_value($existing, 'ssl_status', 'Active')),
        'ssl_expiry_date' => fcg_value($input, 'ssl_expiry_date', fcg_value($existing, 'ssl_expiry_date', '')),
        'website_url' => trim(fcg_value($input, 'website_url', fcg_value($existing, 'website_url', ''))),
        'admin_url' => trim(fcg_value($input, 'admin_url', fcg_value($existing, 'admin_url', ''))),
        'email_accounts' => max(0, (int) fcg_value($input, 'email_accounts', fcg_value($existing, 'email_accounts', 0))),
        'storage_allocation' => trim(fcg_value($input, 'storage_allocation', fcg_value($existing, 'storage_allocation', ''))),
        'storage_usage' => trim(fcg_value($input, 'storage_usage', fcg_value($existing, 'storage_usage', ''))),
        'backup_status' => fcg_value($input, 'backup_status', fcg_value($existing, 'backup_status', 'Scheduled')),
        'last_backup_date' => fcg_value($input, 'last_backup_date', fcg_value($existing, 'last_backup_date', '')),
        'directadmin_url' => trim(fcg_value($input, 'directadmin_url', fcg_value($existing, 'directadmin_url', fcg_value($quickLinks, 'directadmin', '')))),
        'webmail_url' => trim(fcg_value($input, 'webmail_url', fcg_value($existing, 'webmail_url', fcg_value($quickLinks, 'webmail', '')))),
        'nameservers' => trim(fcg_value($input, 'nameservers', fcg_value($existing, 'nameservers', ''))),
        'account_status' => fcg_value($input, 'account_status', fcg_value($existing, 'account_status', 'Active')),
        'internal_notes' => trim(fcg_value($input, 'internal_notes', fcg_value($existing, 'internal_notes', ''))),
        'updated_at' => date('c'),
        'created_at' => fcg_value($existing, 'created_at', date('c')),
    ];
}

function fcg_job_card_from_input($input, $data, $existing = null)
{
    $businessUnit = fcg_business_unit_from_input($input, $existing, fcg_user());
    $clientId = (int) fcg_value($input, 'client_id', fcg_value($existing, 'client_id', 0));
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid active client'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $jobNo = trim(fcg_value($input, 'job_no', fcg_value($existing, 'job_no', '')));
    if ($jobNo === '') {
        $jobNo = fcg_business_number($data['job_cards'], 'FCG/JOB', 'job_no');
    }
    return [
        'id' => $existing ? (int) fcg_value($existing, 'id', 0) : fcg_next_record_id($data['job_cards']),
        'business_unit' => $businessUnit,
        'client_id' => $clientId,
        'job_no' => $jobNo,
        'project_type' => fcg_value($input, 'project_type', fcg_value($existing, 'project_type', 'IT Support')),
        'technician' => trim(fcg_value($input, 'technician', fcg_value($existing, 'technician', ''))),
        'site_address' => trim(fcg_value($input, 'site_address', fcg_value($existing, 'site_address', ''))),
        'materials_used' => trim(fcg_value($input, 'materials_used', fcg_value($existing, 'materials_used', ''))),
        'status' => fcg_value($input, 'status', fcg_value($existing, 'status', 'Scheduled')),
        'completion_report' => trim(fcg_value($input, 'completion_report', fcg_value($existing, 'completion_report', ''))),
        'client_signature' => trim(fcg_value($input, 'client_signature', fcg_value($existing, 'client_signature', ''))),
        'signed_at' => fcg_value($input, 'signed_at', fcg_value($existing, 'signed_at', '')),
        'before_photo_path' => fcg_value($existing, 'before_photo_path', ''),
        'after_photo_path' => fcg_value($existing, 'after_photo_path', ''),
        'updated_at' => date('c'),
        'created_at' => fcg_value($existing, 'created_at', date('c')),
    ];
}

function fcg_dashboard()
{
    $user = fcg_require_user();
    $data = fcg_load_portal_data();
    $activeUnit = fcg_active_business_unit($user);
    $brand = fcg_brand_config($activeUnit);
    $availableUnits = fcg_value($user, 'role', '') === 'admin' ? ['future_creative_group', 'fcg_cloud'] : fcg_user_business_units($user);
    $businessUnits = [];
    foreach ($availableUnits as $unit) {
        $businessUnits[$unit] = fcg_brand_config($unit);
    }
    $documents = array_map('fcg_public_document', fcg_client_records($data['documents'], $user, true, $activeUnit));
    $invoices = array_map('fcg_public_invoice', fcg_client_records($data['invoices'], $user, false, $activeUnit));
    $quotes = fcg_client_records($data['quotes'], $user, false, $activeUnit);
    $projects = fcg_client_records($data['projects'], $user, false, $activeUnit);
    $tickets = array_map('fcg_public_ticket', fcg_client_records($data['tickets'], $user, false, $activeUnit));
    $onboarding = array_map('fcg_public_onboarding', fcg_client_records($data['onboarding'], $user, false, $activeUnit));
    $subscriptions = fcg_client_records($data['subscriptions'], $user, false, $activeUnit);
    $hostingRecords = fcg_client_records($data['hosting_records'], $user, false, $activeUnit);
    $jobCards = array_map('fcg_public_job_card', fcg_client_records($data['job_cards'], $user, false, $activeUnit));
    $feedback = fcg_client_records($data['feedback'], $user, false, $activeUnit);
    $proformas = array_map('fcg_normalize_invoice', fcg_client_records($data['proformas'], $user, false, $activeUnit));
    $creditNotes = array_map('fcg_normalize_invoice', fcg_client_records($data['credit_notes'], $user, false, $activeUnit));
    $statements = array_map('fcg_normalize_invoice', fcg_client_records($data['statements'], $user, false, $activeUnit));
    $services = array_values(array_filter($data['services'], function ($service) use ($activeUnit) {
        return fcg_record_matches_business_unit($service, $activeUnit) && fcg_bool_value(fcg_value($service, 'active', true));
    }));
    $notifications = fcg_value($user, 'role', '') === 'admin'
        ? array_values(array_filter($data['notifications'], function ($item) use ($activeUnit) { return fcg_record_matches_business_unit($item, $activeUnit); }))
        : array_values(array_filter($data['notifications'], function ($item) use ($user, $activeUnit) {
            return (int) fcg_value($item, 'client_id', 0) === (int) $user['id'] && fcg_record_matches_business_unit($item, $activeUnit);
        }));

    return [
        'active_business_unit' => $activeUnit,
        'business_units' => $businessUnits,
        'brand' => $brand,
        'navigation' => fcg_dashboard_navigation($activeUnit),
        'support_categories' => fcg_support_categories($activeUnit),
        'quick_links' => fcg_value($brand, 'quick_links', []),
        'directadmin_api' => $activeUnit === 'fcg_cloud' ? fcg_directadmin_config() : null,
        'service_catalogue' => $services,
        'cloud_summary' => $activeUnit === 'fcg_cloud' ? fcg_cloud_dashboard_summary($invoices, $tickets, $subscriptions, $hostingRecords, $documents, $notifications) : null,
        'projects' => $projects,
        'quotes' => $quotes,
        'invoices' => $invoices,
        'tickets' => $tickets,
        'documents' => $documents,
        'onboarding' => $onboarding,
        'subscriptions' => $subscriptions,
        'hosting_records' => $hostingRecords,
        'job_cards' => $jobCards,
        'feedback' => $feedback,
        'proformas' => $proformas,
        'credit_notes' => $creditNotes,
        'statements' => $statements,
        'notifications' => array_slice($notifications, 0, 100),
        'stats' => [
            'active_projects' => count($projects),
            'pending_quotations' => count(array_filter($quotes, function ($quote) {
                return in_array(fcg_value($quote, 'status', 'Draft'), ['Draft', 'Sent', 'Viewed', 'Request Changes', 'Changes Requested'], true);
            })),
            'approved_quotations' => count(array_filter($quotes, function ($quote) {
                return fcg_value($quote, 'status', '') === 'Approved';
            })),
            'outstanding_invoices' => count(array_filter($invoices, function ($invoice) {
                return !in_array(fcg_value($invoice, 'payment_status', 'Unpaid'), ['Paid', 'Paid In Full'], true);
            })),
            'paid_invoices' => count(array_filter($invoices, function ($invoice) {
                return in_array(fcg_value($invoice, 'payment_status', ''), ['Paid', 'Paid In Full'], true);
            })),
            'open_tickets' => count(array_filter($tickets, function ($ticket) {
                return !in_array(fcg_value($ticket, 'status', 'Open'), ['Resolved', 'Closed'], true);
            })),
            'documents' => count($documents),
            'unread_notifications' => count(array_filter($notifications, function ($item) { return empty($item['read_at']); })),
        ],
    ];
}

function fcg_serve_static($path)
{
    $allowed = [
        '/static/logo.png' => 'static/logo.png',
        '/static/favicon-16x16.png' => 'static/favicon-16x16.png',
        '/static/favicon-36x36.png' => 'static/favicon-36x36.png',
        '/static/fcg-cloud-logo.png' => 'static/fcg-cloud-logo.png',
        '/static/fcg-cloud-favicon.png' => 'static/fcg-cloud-favicon.png',
    ];

    if (!isset($allowed[$path])) {
        return false;
    }

    $asset = fcg_first_file($allowed[$path]);
    if (!$asset) {
        return false;
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    if (fcg_value($_SERVER, 'REQUEST_METHOD', 'GET') !== 'HEAD') {
        readfile($asset);
    }
    exit;
}

if (fcg_serve_static($requestPath)) {
    exit;
}

$method = strtoupper(fcg_value($_SERVER, 'REQUEST_METHOD', 'GET'));
$csrfExemptPaths = ['/api/login', '/auth/apple/callback'];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array($requestPath, $csrfExemptPaths, true)) {
    fcg_require_csrf();
}
if (strpos($requestPath, '/api/admin/') === 0) {
    fcg_require_admin();
}

if (($requestPath === '/auth/google' || $requestPath === '/auth/google/start') && $method === 'GET') {
    fcg_oauth_start('google');
}
if (($requestPath === '/auth/apple' || $requestPath === '/auth/apple/start') && $method === 'GET') {
    fcg_oauth_start('apple');
}
if ($requestPath === '/auth/google/callback' && $method === 'GET') {
    fcg_oauth_callback_google();
}
if ($requestPath === '/auth/apple/callback' && in_array($method, ['GET', 'POST'], true)) {
    fcg_oauth_callback_apple();
}
if ($requestPath === '/api/auth/config' && $method === 'GET') {
    fcg_json(['providers' => fcg_oauth_public_config()]);
}

if ($requestPath === '/document' && $method === 'GET') {
    $token = trim(fcg_value($_GET, 'token', ''));
    $data = fcg_load_portal_data();
    $linkIndex = -1;
    foreach ($data['document_links'] as $index => $link) {
        if (hash_equals(fcg_value($link, 'token_hash', ''), hash('sha256', $token))) {
            $linkIndex = $index;
            break;
        }
    }
    if ($linkIndex < 0 || empty($data['document_links'][$linkIndex]['active'])
        || fcg_value($data['document_links'][$linkIndex], 'expires_at', '') < date('c')) {
        fcg_json(['error' => 'This secure document link is invalid or has expired'], 404);
    }
    $link = $data['document_links'][$linkIndex];
    $documentType = fcg_value($link, 'document_type', 'invoice');
    $collections = ['quote' => 'quotes', 'invoice' => 'invoices', 'receipt' => 'invoices', 'proforma' => 'proformas', 'credit_note' => 'credit_notes', 'statement' => 'statements'];
    $collection = fcg_value($collections, $documentType, 'invoices');
    $recordIndex = fcg_find_record_index($data[$collection], (int) fcg_value($link, 'document_id', 0));
    if ($recordIndex < 0) {
        fcg_json(['error' => 'Document not found'], 404);
    }
    $isQuote = $collection === 'quotes';
    $record = $isQuote ? fcg_normalize_quote($data[$collection][$recordIndex]) : fcg_normalize_invoice($data[$collection][$recordIndex]);
    $titles = ['quote' => 'QUOTATION', 'invoice' => 'INVOICE', 'receipt' => 'RECEIPT', 'proforma' => 'PRO-FORMA INVOICE', 'credit_note' => 'CREDIT NOTE', 'statement' => 'STATEMENT'];
    $title = fcg_value($titles, $documentType, 'INVOICE');
    $badge = $isQuote ? fcg_value($record, 'status', 'Draft') : ($documentType === 'receipt' ? 'Paid In Full' : fcg_value($record, 'status', fcg_value($record, 'payment_status', 'Issued')));
    $numberKeys = ['quote' => 'quote_no', 'invoice' => 'invoice_no', 'receipt' => 'receipt_no', 'proforma' => 'proforma_no', 'credit_note' => 'credit_note_no', 'statement' => 'statement_no'];
    $number = fcg_value($record, fcg_value($numberKeys, $documentType, 'invoice_no'), $documentType);
    $data['document_links'][$linkIndex]['access_count'] = (int) fcg_value($link, 'access_count', 0) + 1;
    $data['document_links'][$linkIndex]['last_accessed_at'] = date('c');
    array_unshift($data['document_access'], [
        'id' => fcg_next_record_id($data['document_access']),
        'business_unit' => fcg_record_business_unit($record),
        'type' => 'secure_' . fcg_value($link, 'document_type', 'document'),
        'record_id' => (int) fcg_value($link, 'document_id', 0),
        'user_id' => 0,
        'accessed_at' => date('c'),
    ]);
    fcg_save_portal_data($data);
    $pdfSettings = fcg_templates_for_business_unit($data['templates'], fcg_record_business_unit($record));
    $pdf = fcg_pdf_document($title, $badge, $record, $pdfSettings);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if ($requestPath === '/api/login' && $method === 'POST') {
    $data = fcg_request_data();
    $email = strtolower(trim(fcg_value($data, 'email', '')));
    $password = fcg_value($data, 'password', fcg_value($data, 'access_code', ''));
    fcg_login_rate_check($email);

    $account = fcg_find_user($email);
    if (!$account || empty($account['password_hash']) || !password_verify($password, $account['password_hash'])) {
        fcg_record_login_attempt($email, false);
        fcg_json(['error' => 'The email address or access code entered is incorrect. Please verify your details and try again.'], 401);
    }

    $portalSettings = fcg_load_portal_data();
    $twoFactorEnabled = fcg_value($account, 'role', '') === 'admin'
        && fcg_bool_value(fcg_value(fcg_value(fcg_value($portalSettings, 'templates', []), 'security', []), 'admin_2fa', false));
    if ($twoFactorEnabled) {
        $submittedCode = trim(fcg_value($data, 'two_factor_code', ''));
        $pending = fcg_value($_SESSION, 'fcg_pending_2fa', []);
        $validPending = (int) fcg_value($pending, 'user_id', 0) === (int) fcg_value($account, 'id', 0)
            && (int) fcg_value($pending, 'expires_at', 0) >= time();
        if ($submittedCode === '' || !$validPending) {
            $code = (string) random_int(100000, 999999);
            $_SESSION['fcg_pending_2fa'] = [
                'user_id' => (int) fcg_value($account, 'id', 0),
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => time() + 10 * 60,
            ];
            fcg_send_customer_email(fcg_value($account, 'email', ''), 'Future Creative Group portal verification code', [
                'Your administrator verification code is: ' . $code,
                'This code expires in 10 minutes.',
                'If you did not attempt to sign in, contact Future Creative Group immediately.',
            ]);
            fcg_json(['requires_2fa' => true, 'message' => 'Enter the six-digit verification code sent to the administrator email address.'], 202);
        }
        if (!password_verify($submittedCode, fcg_value($pending, 'code_hash', ''))) {
            fcg_json(['requires_2fa' => true, 'error' => 'The verification code is incorrect or expired.'], 401);
        }
        unset($_SESSION['fcg_pending_2fa']);
    }

    $sessionError = '';
    $user = fcg_start_portal_session($account, 'access_code', $sessionError);
    if (!$user) {
        fcg_record_login_attempt($email, false);
        fcg_json(['error' => $sessionError ?: 'We could not complete the sign-in request. Please try again.'], 403);
    }
    fcg_record_login_attempt($email, true);
    fcg_json(['ok' => true, 'user' => $user]);
}

if ($requestPath === '/api/logout' && $method === 'POST') {
    $sessionUser = fcg_user();
    if ($sessionUser) {
        $portalData = fcg_load_portal_data();
        fcg_log_activity($portalData, 'logout', (fcg_value($sessionUser, 'role', '') === 'admin' ? 'Admin' : 'Client') . ' signed out', fcg_value($sessionUser, 'id', 0));
        fcg_save_portal_data($portalData);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    fcg_json(['ok' => true]);
}

if ($requestPath === '/api/me' && $method === 'GET') {
    $user = fcg_user();
    if (!$user) {
        fcg_json(['error' => 'Authentication required'], 401);
    }
    $dashboard = fcg_dashboard();
    if (fcg_value($user, 'role', '') === 'admin') {
        $dashboard['admin'] = fcg_admin_clients_payload();
    }
    fcg_json(['user' => $user, 'dashboard' => $dashboard]);
}

if ($requestPath === '/api/business-unit' && $method === 'POST') {
    $user = fcg_require_user();
    $input = fcg_request_data();
    $unit = fcg_normalize_business_unit(fcg_value($input, 'business_unit', ''));
    if ($unit === 'both' || !fcg_user_can_access_business_unit($user, $unit)) {
        fcg_json(['error' => 'This account is not authorised for the selected business workspace'], 403);
    }
    $_SESSION['fcg_active_business_unit'] = $unit;
    $dashboard = fcg_dashboard();
    if (fcg_value($user, 'role', '') === 'admin') {
        $dashboard['admin'] = fcg_admin_clients_payload();
    }
    fcg_json(['ok' => true, 'active_business_unit' => $unit, 'brand' => fcg_brand_config($unit), 'dashboard' => $dashboard, 'user' => $user]);
}

if ($requestPath === '/api/profile' && $method === 'POST') {
    $sessionUser = fcg_require_user();
    if (fcg_value($sessionUser, 'role', '') !== 'client') {
        fcg_json(['error' => 'Client profile access required'], 403);
    }
    $input = fcg_request_data();
    $users = fcg_load_users();
    $index = fcg_find_record_index($users, (int) fcg_value($sessionUser, 'id', 0));
    if ($index < 0) fcg_json(['error' => 'Client profile not found'], 404);
    $name = trim(fcg_value($input, 'name', fcg_value($users[$index], 'name', '')));
    if (strlen($name) < 2) fcg_json(['error' => 'Enter a valid contact name'], 400);
    $email = strtolower(trim(fcg_value($input, 'email', fcg_value($users[$index], 'email', ''))));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fcg_json(['error' => 'Enter a valid email address'], 400);
    foreach ($users as $candidate) if ((int) fcg_value($candidate, 'id', 0) !== (int) $users[$index]['id'] && strtolower(fcg_value($candidate, 'email', '')) === $email) fcg_json(['error' => 'This email address is already in use'], 409);
    $users[$index]['name'] = $name;
    $users[$index]['email'] = $email;
    $users[$index]['company'] = trim(fcg_value($input, 'company', fcg_value($users[$index], 'company', '')));
    $users[$index]['phone'] = trim(fcg_value($input, 'phone', fcg_value($users[$index], 'phone', '')));
    $users[$index]['address'] = trim(fcg_value($input, 'address', fcg_value($users[$index], 'address', '')));
    $users[$index]['vat_number'] = trim(fcg_value($input, 'vat_number', fcg_value($users[$index], 'vat_number', '')));
    $users[$index]['updated_at'] = date('c');
    fcg_save_users($users);
    $user = fcg_public_user($users[$index]);
    $_SESSION['fcg_user'] = $user;
    $portalData = fcg_load_portal_data();
    fcg_log_activity($portalData, 'profile', 'Client profile details updated', $user['id']);
    fcg_save_portal_data($portalData);
    fcg_json(['ok' => true, 'user' => $user, 'dashboard' => fcg_dashboard()]);
}

if ($requestPath === '/api/dashboard' && $method === 'GET') {
    $user = fcg_require_user();
    $dashboard = fcg_dashboard();
    if (fcg_value($user, 'role', '') === 'admin') {
        $dashboard['admin'] = fcg_admin_clients_payload();
    }
    fcg_json($dashboard);
}

if ($requestPath === '/api/admin/clients') {
    fcg_require_admin();
    if ($method === 'GET') {
        fcg_json(fcg_admin_clients_payload());
    }
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }

    $data = fcg_request_data();
    $name = trim(fcg_value($data, 'name', ''));
    $email = strtolower(trim(fcg_value($data, 'email', '')));
    $company = trim(fcg_value($data, 'company', ''));
    $accessCode = trim(fcg_value($data, 'access_code', ''));
    $businessUnit = fcg_normalize_business_unit(fcg_value($data, 'business_unit', 'future_creative_group'));
    if ($accessCode === '') {
        $accessCode = fcg_generate_access_code();
    }

    if (strlen($name) < 2) {
        fcg_json(['error' => 'Client name is required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fcg_json(['error' => 'A valid client email address is required'], 400);
    }
    if (strlen($accessCode) < 12) {
        fcg_json(['error' => 'Access code must be at least 12 characters'], 400);
    }

    $users = fcg_load_users();
    foreach ($users as $user) {
        if (strtolower(fcg_value($user, 'email', '')) === $email) {
            fcg_json(['error' => 'A portal account with this email already exists'], 409);
        }
    }

    $client = [
        'id' => fcg_next_user_id($users),
        'name' => $name,
        'email' => $email,
        'company' => $company,
        'phone' => trim(fcg_value($data, 'phone', '')),
        'address' => trim(fcg_value($data, 'address', '')),
        'vat_number' => trim(fcg_value($data, 'vat_number', '')),
        'business_unit' => $businessUnit,
        'notification_preference' => fcg_value($data, 'notification_preference', 'Email'),
        'notification_consent' => fcg_bool_value(fcg_value($data, 'notification_consent', true)),
        'role' => 'client',
        'active' => true,
        'created_at' => date('c'),
        'google_id' => '',
        'apple_id' => '',
        'auth_provider' => 'access_code',
        'last_login_at' => '',
        'password_hash' => password_hash($accessCode, PASSWORD_DEFAULT),
    ];

    $users[] = $client;
    fcg_save_users($users);
    $welcomeUnit = $businessUnit === 'both' ? 'future_creative_group' : $businessUnit;
    $welcomeBrand = fcg_brand_config($welcomeUnit);
    $welcomeLines = [
        'Good day ' . $client['name'] . ',', '',
        'Your ' . $welcomeBrand['label'] . ' client portal profile has been created successfully.', '',
        'Portal Link: ' . $welcomeBrand['portal_url'],
        'Login Email: ' . $client['email'],
        'Access Code: ' . $accessCode, '',
        'You can view quotations, invoices, project updates, payments, documents, support tickets and receipts.', '',
        'For security, please keep your login details private.', '',
        'Kind regards,', $welcomeBrand['label'],
    ];
    $welcomeSent = fcg_send_customer_email($client['email'], 'Your ' . $welcomeBrand['label'] . ' client portal access', $welcomeLines, $welcomeUnit);
    $portalData = fcg_load_portal_data();
    fcg_add_notification($portalData, $client['id'], 'welcome', 'Welcome to your client portal', 'Your ' . $welcomeBrand['label'] . ' portal profile is ready.', '', 0, 'Portal', 0, $welcomeUnit);
    fcg_log_activity($portalData, 'client', 'Client profile created and welcome notification prepared', $client['id'], $welcomeUnit);
    fcg_save_portal_data($portalData);
    $welcomeWhatsapp = strlen(fcg_customer_phone($client['phone'])) >= 10 ? 'https://wa.me/' . fcg_customer_phone($client['phone']) . '?text=' . rawurlencode(implode("\n", $welcomeLines)) : '';

    fcg_json([
        'ok' => true,
        'client' => fcg_client_summary($client),
        'access_code' => $accessCode,
        'email_sent' => $welcomeSent,
        'whatsapp_url' => $welcomeWhatsapp,
        'admin' => fcg_admin_clients_payload(),
    ], 201);
}

if ($requestPath === '/api/admin/clients/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }

    $data = fcg_request_data();
    $clientId = (int) fcg_value($data, 'id', fcg_value($data, 'client_id', 0));
    $name = trim(fcg_value($data, 'name', ''));
    $email = strtolower(trim(fcg_value($data, 'email', '')));
    $company = trim(fcg_value($data, 'company', ''));
    $active = fcg_bool_value(fcg_value($data, 'active', true));
    $businessUnit = fcg_normalize_business_unit(fcg_value($data, 'business_unit', 'future_creative_group'));

    if ($clientId <= 0) {
        fcg_json(['error' => 'Client account is required'], 400);
    }
    if (strlen($name) < 2) {
        fcg_json(['error' => 'Client name is required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fcg_json(['error' => 'A valid client email address is required'], 400);
    }

    $users = fcg_load_users();
    $updated = null;
    foreach ($users as $user) {
        if ((int) fcg_value($user, 'id', 0) !== $clientId && strtolower(fcg_value($user, 'email', '')) === $email) {
            fcg_json(['error' => 'Another portal account already uses this email'], 409);
        }
    }
    foreach ($users as &$account) {
        if ((int) fcg_value($account, 'id', 0) === $clientId && fcg_value($account, 'role', '') === 'client') {
            $account['name'] = $name;
            $account['email'] = $email;
            $account['company'] = $company;
            $account['phone'] = trim(fcg_value($data, 'phone', fcg_value($account, 'phone', '')));
            $account['address'] = trim(fcg_value($data, 'address', fcg_value($account, 'address', '')));
            $account['vat_number'] = trim(fcg_value($data, 'vat_number', fcg_value($account, 'vat_number', '')));
            $account['business_unit'] = fcg_normalize_business_unit(fcg_value($data, 'business_unit', fcg_value($account, 'business_unit', 'future_creative_group')));
            $account['notification_preference'] = fcg_value($data, 'notification_preference', fcg_value($account, 'notification_preference', 'Email'));
            $account['notification_consent'] = fcg_bool_value(fcg_value($data, 'notification_consent', fcg_value($account, 'notification_consent', true)));
            $account['active'] = $active;
            $account['updated_at'] = date('c');
            $updated = fcg_client_summary($account);
            break;
        }
    }
    unset($account);
    if (!$updated) {
        fcg_json(['error' => 'Client account not found'], 404);
    }
    fcg_save_users($users);
    fcg_json(['ok' => true, 'client' => $updated, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/clients/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }

    $data = fcg_request_data();
    $clientId = (int) fcg_value($data, 'id', fcg_value($data, 'client_id', 0));
    $users = fcg_load_users();
    $updated = null;
    foreach ($users as &$account) {
        if ((int) fcg_value($account, 'id', 0) === $clientId && fcg_value($account, 'role', '') === 'client') {
            $account['active'] = false;
            $account['deleted_at'] = date('c');
            $updated = fcg_client_summary($account);
            break;
        }
    }
    unset($account);
    if (!$updated) {
        fcg_json(['error' => 'Client account not found'], 404);
    }
    fcg_save_users($users);
    fcg_json(['ok' => true, 'client' => $updated, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/clients/access-code') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $data = fcg_request_data();
    $clientId = (int) fcg_value($data, 'client_id', 0);
    $accessCode = fcg_generate_access_code();
    $users = fcg_load_users();
    $updated = null;
    foreach ($users as &$account) {
        if ((int) fcg_value($account, 'id', 0) === $clientId && fcg_value($account, 'role', '') === 'client') {
            $account['password_hash'] = password_hash($accessCode, PASSWORD_DEFAULT);
            $account['active'] = true;
            $updated = fcg_client_summary($account);
            break;
        }
    }
    unset($account);
    if (!$updated) {
        fcg_json(['error' => 'Client account not found'], 404);
    }
    fcg_save_users($users);
    fcg_json(['ok' => true, 'client' => $updated, 'access_code' => $accessCode, 'admin' => fcg_admin_clients_payload()]);
}

if (in_array($requestPath, ['/api/admin/guests', '/api/admin/guests/update', '/api/admin/guests/delete', '/api/admin/guests/convert'], true)) {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();

    if ($requestPath === '/api/admin/guests') {
        $input['customer_type'] = 'guest';
        $customer = fcg_document_customer($input, $data);
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'guest_customer' => $data['guest_customers'][0], 'admin' => fcg_admin_clients_payload()], 201);
    }

    $guestId = (int) fcg_value($input, 'id', fcg_value($input, 'guest_customer_id', 0));
    $index = fcg_find_record_index($data['guest_customers'], $guestId);
    if ($index < 0) {
        fcg_json(['error' => 'Guest customer not found'], 404);
    }

    if ($requestPath === '/api/admin/guests/delete') {
        $data['guest_customers'][$index]['status'] = 'Archived';
        $data['guest_customers'][$index]['updated_at'] = date('c');
        fcg_log_activity($data, 'guest', 'Guest customer ' . $data['guest_customers'][$index]['name'] . ' archived');
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }

    if ($requestPath === '/api/admin/guests/update') {
        $input['customer_type'] = 'guest';
        $input['guest_customer_id'] = $guestId;
        fcg_document_customer($input, $data, ['customer_type' => 'guest', 'guest_customer_id' => $guestId]);
        fcg_log_activity($data, 'guest', 'Guest customer ' . $data['guest_customers'][$index]['name'] . ' updated');
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'guest_customer' => $data['guest_customers'][$index], 'admin' => fcg_admin_clients_payload()]);
    }

    $guest = $data['guest_customers'][$index];
    $email = strtolower(trim(fcg_value($guest, 'email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fcg_json(['error' => 'Add a valid email address before converting this guest customer'], 400);
    }
    if (fcg_find_user($email)) {
        fcg_json(['error' => 'A portal account already exists for this email address'], 409);
    }
    $users = fcg_load_users();
    $accessCode = fcg_generate_access_code();
    $client = [
        'id' => fcg_next_user_id($users),
        'name' => fcg_value($guest, 'name', 'Client'),
        'email' => $email,
        'company' => fcg_value($guest, 'company', ''),
        'phone' => fcg_value($guest, 'phone', ''),
        'address' => fcg_value($guest, 'address', ''),
        'vat_number' => fcg_value($guest, 'vat_number', ''),
        'business_unit' => fcg_record_business_unit($guest),
        'role' => 'client',
        'active' => true,
        'created_at' => date('c'),
        'google_id' => '',
        'apple_id' => '',
        'auth_provider' => 'access_code',
        'last_login_at' => '',
        'password_hash' => password_hash($accessCode, PASSWORD_DEFAULT),
    ];
    $users[] = $client;
    fcg_save_users($users);
    $data['guest_customers'][$index]['status'] = 'Converted';
    $data['guest_customers'][$index]['converted_client_id'] = $client['id'];
    $data['guest_customers'][$index]['converted_at'] = date('c');
    foreach (['quotes', 'invoices', 'proformas', 'credit_notes', 'statements'] as $collection) {
        foreach ($data[$collection] as &$record) {
            if ((int) fcg_value($record, 'guest_customer_id', 0) === $guestId) {
                $record['client_id'] = $client['id'];
                $record['customer_type'] = 'registered';
            }
        }
        unset($record);
    }
    $data['guest_customers'][$index]['pipeline_stage'] = 'Converted to Client';
    fcg_add_notification($data, $client['id'], 'welcome', 'Welcome to your client portal', 'Your guest customer record has been converted into secure portal access.', '', 0, 'Portal', 0, fcg_user_business_unit($client));
    fcg_log_activity($data, 'guest', 'Guest customer ' . $guest['name'] . ' converted to registered client', $client['id'], fcg_user_business_unit($client));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'client' => fcg_client_summary($client), 'access_code' => $accessCode, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/projects') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $clientId = (int) fcg_value($input, 'client_id', 0);
    $title = trim(fcg_value($input, 'title', ''));
    $description = trim(fcg_value($input, 'description', ''));
    $status = trim(fcg_value($input, 'status', 'In progress'));
    $nextStep = trim(fcg_value($input, 'next_step', ''));
    $progress = max(0, min(100, (int) fcg_value($input, 'progress', 0)));
    $businessUnit = fcg_business_unit_from_input($input, null, fcg_user());
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid client'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    if (strlen($title) < 2) {
        fcg_json(['error' => 'Project title is required'], 400);
    }

    $data = fcg_load_portal_data();
    $project = [
        'id' => fcg_next_record_id($data['projects']),
        'client_id' => $clientId,
        'business_unit' => $businessUnit,
        'title' => $title,
        'description' => $description,
        'project_type' => trim(fcg_value($input, 'project_type', 'Technology Project')),
        'start_date' => fcg_value($input, 'start_date', ''),
        'expected_completion_date' => fcg_value($input, 'expected_completion_date', ''),
        'stage' => trim(fcg_value($input, 'stage', 'Planning')),
        'milestones' => trim(fcg_value($input, 'milestones', '')),
        'admin_notes' => trim(fcg_value($input, 'admin_notes', '')),
        'client_update' => trim(fcg_value($input, 'client_update', '')),
        'status' => $status,
        'progress' => $progress,
        'next_step' => $nextStep,
        'updated_at' => date('c'),
    ];
    array_unshift($data['projects'], $project);
    fcg_log_activity($data, 'project', 'Project ' . $project['title'] . ' created', $project['client_id'], $businessUnit);
    fcg_notify_record($data, $project, 'project', 'New project update', $project['title'] . ' is now available in your portal. Progress: ' . $project['progress'] . '%.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'project' => $project, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/projects/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $projectId = (int) fcg_value($input, 'id', 0);
    $clientId = (int) fcg_value($input, 'client_id', 0);
    $title = trim(fcg_value($input, 'title', ''));
    $description = trim(fcg_value($input, 'description', ''));
    $status = trim(fcg_value($input, 'status', 'In progress'));
    $nextStep = trim(fcg_value($input, 'next_step', ''));
    $progress = max(0, min(100, (int) fcg_value($input, 'progress', 0)));
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid active client'], 400);
    }
    if (strlen($title) < 2) {
        fcg_json(['error' => 'Project title is required'], 400);
    }

    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['projects'], $projectId);
    if ($index < 0) {
        fcg_json(['error' => 'Project update not found'], 404);
    }
    $businessUnit = fcg_business_unit_from_input($input, $data['projects'][$index], fcg_user());
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $data['projects'][$index]['client_id'] = $clientId;
    $data['projects'][$index]['business_unit'] = $businessUnit;
    $data['projects'][$index]['title'] = $title;
    $data['projects'][$index]['description'] = $description;
    $data['projects'][$index]['project_type'] = trim(fcg_value($input, 'project_type', fcg_value($data['projects'][$index], 'project_type', 'Technology Project')));
    $data['projects'][$index]['start_date'] = fcg_value($input, 'start_date', fcg_value($data['projects'][$index], 'start_date', ''));
    $data['projects'][$index]['expected_completion_date'] = fcg_value($input, 'expected_completion_date', fcg_value($data['projects'][$index], 'expected_completion_date', ''));
    $data['projects'][$index]['stage'] = trim(fcg_value($input, 'stage', fcg_value($data['projects'][$index], 'stage', 'Planning')));
    $data['projects'][$index]['milestones'] = trim(fcg_value($input, 'milestones', fcg_value($data['projects'][$index], 'milestones', '')));
    $data['projects'][$index]['admin_notes'] = trim(fcg_value($input, 'admin_notes', fcg_value($data['projects'][$index], 'admin_notes', '')));
    $data['projects'][$index]['client_update'] = trim(fcg_value($input, 'client_update', fcg_value($data['projects'][$index], 'client_update', '')));
    $data['projects'][$index]['status'] = $status;
    $data['projects'][$index]['progress'] = $progress;
    $data['projects'][$index]['next_step'] = $nextStep;
    $data['projects'][$index]['updated_at'] = date('c');
    fcg_log_activity($data, 'project', 'Project ' . $title . ' updated', $clientId, $businessUnit);
    fcg_notify_record($data, $data['projects'][$index], 'project', 'Project progress updated', $title . ' is now ' . $progress . '% complete. Next: ' . $nextStep);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'project' => $data['projects'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/projects/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $projectId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['projects'], $projectId);
    if ($index < 0) {
        fcg_json(['error' => 'Project update not found'], 404);
    }
    $removed = $data['projects'][$index];
    array_splice($data['projects'], $index, 1);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => $removed, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/quotes') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    $quote = fcg_quote_from_input($input, $data);
    array_unshift($data['quotes'], $quote);
    fcg_log_activity($data, 'quote', 'Quotation ' . $quote['quote_no'] . ' created', $quote['client_id']);
    fcg_notify_record($data, $quote, 'quote', 'New quotation created', $quote['quote_no'] . ' for ' . fcg_money_format($quote['total']) . ' is ready for review.');
    if ($quote['status'] === 'Sent') {
        $quoteBrand = fcg_brand_config(fcg_record_business_unit($quote));
        fcg_notify_business_event('New quotation from ' . $quoteBrand['label'], $quote, fcg_client_contact($quote['client_id']));
    }
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'quote' => $quote, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/quotes/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0) {
        fcg_json(['error' => 'Quote record not found'], 404);
    }
    $data['quotes'][$index] = fcg_quote_from_input($input, $data, $data['quotes'][$index]);
    fcg_log_activity($data, 'quote', 'Quotation ' . $data['quotes'][$index]['quote_no'] . ' updated', $data['quotes'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'quote' => $data['quotes'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/quotes/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0) {
        fcg_json(['error' => 'Quote record not found'], 404);
    }
    $removed = $data['quotes'][$index];
    array_splice($data['quotes'], $index, 1);
    fcg_log_activity($data, 'quote', 'Quotation ' . fcg_value($removed, 'quote_no', '') . ' deleted', fcg_value($removed, 'client_id', 0));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => $removed, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/quotes/duplicate') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0) {
        fcg_json(['error' => 'Quote record not found'], 404);
    }
    $quote = $data['quotes'][$index];
    unset($quote['id'], $quote['quote_no']);
    $quote['status'] = 'Draft';
    $quote['approval_status'] = 'Draft';
    $quote['title'] = fcg_value($quote, 'title', 'Quote') . ' Copy';
    $duplicate = fcg_quote_from_input($quote, $data);
    array_unshift($data['quotes'], $duplicate);
    fcg_log_activity($data, 'quote', 'Quotation ' . $duplicate['quote_no'] . ' duplicated', $duplicate['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'quote' => $duplicate, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/quotes/send') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0) {
        fcg_json(['error' => 'Quote record not found'], 404);
    }
    $data['quotes'][$index]['status'] = 'Sent';
    $data['quotes'][$index]['approval_status'] = 'Sent';
    $data['quotes'][$index]['sent_at'] = date('c');
    $quoteBrand = fcg_brand_config(fcg_record_business_unit($data['quotes'][$index]));
    fcg_notify_business_event('Quotation sent by ' . $quoteBrand['label'], $data['quotes'][$index], fcg_client_contact($data['quotes'][$index]['client_id']));
    fcg_notify_record($data, $data['quotes'][$index], 'quote', 'Quotation sent', $data['quotes'][$index]['quote_no'] . ' is ready to approve, decline or request changes.');
    fcg_log_activity($data, 'quote', 'Quotation ' . $data['quotes'][$index]['quote_no'] . ' sent to client', $data['quotes'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'quote' => $data['quotes'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/quotes/convert') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0) {
        fcg_json(['error' => 'Quote record not found'], 404);
    }
    $quote = fcg_normalize_quote($data['quotes'][$index]);
    $invoiceInput = $quote;
    $invoiceInput['quote_id'] = $quote['id'];
    $invoiceInput['invoice_date'] = date('Y-m-d');
    $invoiceInput['due_date'] = date('Y-m-d', strtotime('+7 days'));
    $invoiceInput['payment_status'] = 'Unpaid';
    $invoiceInput['business_unit'] = fcg_record_business_unit($quote);
    $unitTemplates = fcg_templates_for_business_unit($data['templates'], fcg_record_business_unit($quote));
    $invoiceInput['terms'] = fcg_value(fcg_value($unitTemplates, 'terms', []), 'invoice', fcg_default_templates()['terms']['invoice']);
    unset($invoiceInput['invoice_no']);
    $invoice = fcg_invoice_from_input($invoiceInput, $data);
    array_unshift($data['invoices'], $invoice);
    $data['quotes'][$index]['status'] = 'Approved';
    $data['quotes'][$index]['approval_status'] = 'Approved';
    $data['quotes'][$index]['converted_invoice_id'] = $invoice['id'];
    fcg_log_activity($data, 'invoice', 'Invoice ' . $invoice['invoice_no'] . ' created from quotation ' . $quote['quote_no'], $invoice['client_id']);
    fcg_notify_record($data, $invoice, 'invoice', 'New invoice created', $invoice['invoice_no'] . ' has a balance of ' . fcg_money_format($invoice['balance_due']) . '.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($invoice), 'quote' => $data['quotes'][$index], 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/invoices') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    $invoice = fcg_invoice_from_input($input, $data);
    array_unshift($data['invoices'], $invoice);
    fcg_log_activity($data, 'invoice', 'Invoice ' . $invoice['invoice_no'] . ' created', $invoice['client_id']);
    fcg_notify_record($data, $invoice, 'invoice', 'New invoice created', $invoice['invoice_no'] . ' is due on ' . $invoice['due_date'] . '. Balance: ' . fcg_money_format($invoice['balance_due']) . '.');
    if (fcg_value($invoice, 'payment_status', '') !== 'Draft') {
        $invoiceBrand = fcg_brand_config(fcg_record_business_unit($invoice));
        fcg_notify_business_event('New invoice from ' . $invoiceBrand['label'], $invoice, fcg_client_contact($invoice['client_id']));
    }
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($invoice), 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/invoices/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0) {
        fcg_json(['error' => 'Invoice not found'], 404);
    }
    $data['invoices'][$index] = fcg_invoice_from_input($input, $data, $data['invoices'][$index]);
    fcg_log_activity($data, 'invoice', 'Invoice ' . $data['invoices'][$index]['invoice_no'] . ' updated', $data['invoices'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($data['invoices'][$index]), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/invoices/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0) {
        fcg_json(['error' => 'Invoice not found'], 404);
    }
    $removed = $data['invoices'][$index];
    array_splice($data['invoices'], $index, 1);
    fcg_log_activity($data, 'invoice', 'Invoice ' . fcg_value($removed, 'invoice_no', '') . ' deleted', fcg_value($removed, 'client_id', 0));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => fcg_public_invoice($removed), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/invoices/duplicate') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0) {
        fcg_json(['error' => 'Invoice not found'], 404);
    }
    $invoice = $data['invoices'][$index];
    unset($invoice['id'], $invoice['invoice_no']);
    $invoice['title'] = fcg_value($invoice, 'title', 'Invoice') . ' Copy';
    $invoice['amount_paid'] = 0;
    $invoice['payment_status'] = 'Unpaid';
    $duplicate = fcg_invoice_from_input($invoice, $data);
    array_unshift($data['invoices'], $duplicate);
    fcg_log_activity($data, 'invoice', 'Invoice ' . $duplicate['invoice_no'] . ' duplicated', $duplicate['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($duplicate), 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/invoices/mark-paid') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0) {
        fcg_json(['error' => 'Invoice not found'], 404);
    }
    $previousAmountPaid = fcg_money_value(fcg_value($data['invoices'][$index], 'amount_paid', 0));
    $amountPaid = fcg_money_value(fcg_value($input, 'amount_paid', fcg_value($data['invoices'][$index], 'total', 0)));
    $recordedAmount = max(0, $amountPaid - $previousAmountPaid);
    $data['invoices'][$index]['amount_paid'] = $amountPaid;
    $data['invoices'][$index]['balance_due'] = max(0, fcg_money_value($data['invoices'][$index]['total']) - $amountPaid);
    $data['invoices'][$index]['payment_status'] = $data['invoices'][$index]['balance_due'] <= 0 ? 'Paid In Full' : ($amountPaid > 0 ? 'Partially Paid' : 'Unpaid');
    $data['invoices'][$index]['payment_date'] = fcg_value($input, 'payment_date', date('Y-m-d'));
    $data['invoices'][$index]['payment_method'] = fcg_value($input, 'payment_method', fcg_value($data['invoices'][$index], 'payment_method', 'Bank transfer'));
    if (!isset($data['invoices'][$index]['payment_history']) || !is_array($data['invoices'][$index]['payment_history'])) {
        $data['invoices'][$index]['payment_history'] = [];
    }
    $data['invoices'][$index]['payment_history'][] = [
        'id' => fcg_next_record_id($data['invoices'][$index]['payment_history']),
        'payment_id' => fcg_next_record_id($data['invoices'][$index]['payment_history']),
        'client_id' => (int) fcg_value($data['invoices'][$index], 'client_id', 0),
        'guest_customer_id' => (int) fcg_value($data['invoices'][$index], 'guest_customer_id', 0),
        'invoice_id' => (int) fcg_value($data['invoices'][$index], 'id', 0),
        'invoice_no' => fcg_value($data['invoices'][$index], 'invoice_no', ''),
        'date' => $data['invoices'][$index]['payment_date'],
        'payment_date' => $data['invoices'][$index]['payment_date'],
        'amount' => $recordedAmount,
        'verified_amount' => $recordedAmount,
        'method' => $data['invoices'][$index]['payment_method'],
        'payment_method' => $data['invoices'][$index]['payment_method'],
        'status' => $data['invoices'][$index]['payment_status'],
        'admin_review_status' => 'Approved',
        'balance_before' => max(0, fcg_money_value($data['invoices'][$index]['total']) - $previousAmountPaid),
        'projected_balance_after' => $data['invoices'][$index]['balance_due'],
        'balance_after' => $data['invoices'][$index]['balance_due'],
        'balance_remaining' => $data['invoices'][$index]['balance_due'],
        'proof_path' => '',
        'proof_file' => '',
        'file_name' => '',
        'file_type' => '',
        'reviewed_at' => date('c'),
        'created_at' => date('c'),
    ];
    $data['invoices'][$index]['updated_at'] = date('c');
    fcg_notify_business_event('Invoice payment updated', $data['invoices'][$index], fcg_client_contact($data['invoices'][$index]['client_id']));
    fcg_log_activity($data, 'payment', 'Payment updated for invoice ' . $data['invoices'][$index]['invoice_no'], $data['invoices'][$index]['client_id']);
    fcg_notify_record($data, $data['invoices'][$index], 'payment', 'Invoice payment updated', fcg_money_format($amountPaid) . ' was recorded against ' . $data['invoices'][$index]['invoice_no'] . '. Balance: ' . fcg_money_format($data['invoices'][$index]['balance_due']) . '.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($data['invoices'][$index]), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/invoices/payment') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $isMultipart = stripos(fcg_value($_SERVER, 'CONTENT_TYPE', ''), 'multipart/form-data') !== false;
    $input = $isMultipart ? $_POST : fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'id', fcg_value($input, 'invoice_id', 0));
    $amount = fcg_money_value(fcg_value($input, 'amount', 0));
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0 || $amount <= 0) {
        fcg_json(['error' => 'Select an invoice and enter a valid payment amount'], 400);
    }
    $invoice = &$data['invoices'][$index];
    $currentBalance = fcg_money_value(fcg_value(fcg_normalize_invoice($invoice), 'balance_due', fcg_value($invoice, 'total', 0)));
    if ($amount > $currentBalance + 0.01) fcg_json(['error' => 'Payment amount cannot exceed the outstanding balance'], 400);
    if (!isset($invoice['payment_history']) || !is_array($invoice['payment_history'])) {
        $invoice['payment_history'] = [];
    }
    $payment = [
        'id' => fcg_next_record_id($invoice['payment_history']),
        'payment_id' => fcg_next_record_id($invoice['payment_history']),
        'client_id' => (int) fcg_value($invoice, 'client_id', 0),
        'guest_customer_id' => (int) fcg_value($invoice, 'guest_customer_id', 0),
        'invoice_id' => (int) fcg_value($invoice, 'id', 0),
        'invoice_no' => fcg_value($invoice, 'invoice_no', ''),
        'amount' => $amount,
        'verified_amount' => $amount,
        'date' => fcg_value($input, 'date', fcg_value($input, 'payment_date', date('Y-m-d'))),
        'payment_date' => fcg_value($input, 'date', fcg_value($input, 'payment_date', date('Y-m-d'))),
        'method' => trim(fcg_value($input, 'method', fcg_value($input, 'payment_method', 'Bank transfer'))),
        'payment_method' => trim(fcg_value($input, 'method', fcg_value($input, 'payment_method', 'Bank transfer'))),
        'notes' => trim(fcg_value($input, 'notes', '')),
        'reference' => trim(fcg_value($input, 'notes', '')),
        'admin_notes' => trim(fcg_value($input, 'admin_notes', '')),
        'status' => 'Approved',
        'admin_review_status' => 'Approved',
        'proof_path' => '',
        'proof_file' => '',
        'file_name' => '',
        'file_type' => '',
        'uploaded_at' => date('c'),
        'reviewed_at' => date('c'),
        'balance_before' => $currentBalance,
        'projected_balance_after' => max(0, $currentBalance - $amount),
        'balance_after' => max(0, $currentBalance - $amount),
        'created_at' => date('c'),
    ];
    if (isset($_FILES['proof']) && (int) fcg_value($_FILES['proof'], 'error', UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $validated = fcg_validate_upload($_FILES['proof'], ['pdf', 'png', 'jpg', 'jpeg', 'webp'], 10 * 1024 * 1024);
        $uploadDir = fcg_data_dir() . '/payment-proofs';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $payment['proof_file'] = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $validated['safe_name'];
        $payment['proof_path'] = $uploadDir . '/' . $payment['proof_file'];
        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $payment['proof_path'])) {
            fcg_json(['error' => 'Could not save the payment verification file'], 500);
        }
    }
    $invoice['payment_history'][] = $payment;
    $invoice['amount_paid'] = fcg_money_value(fcg_value($invoice, 'amount_paid', 0)) + $amount;
    $invoice['payment_date'] = $payment['date'];
    $invoice['payment_method'] = $payment['method'];
    fcg_payment_status($invoice);
    $invoice['payment_history'][count($invoice['payment_history']) - 1]['balance_remaining'] = $invoice['balance_due'];
    $invoice['payment_history'][count($invoice['payment_history']) - 1]['balance_after'] = $invoice['balance_due'];
    $invoice['updated_at'] = date('c');
    fcg_log_activity($data, 'payment', 'Approved payment of ' . fcg_money_format($amount) . ' recorded for invoice ' . $invoice['invoice_no'], $invoice['client_id']);
    fcg_notify_record($data, $invoice, 'payment', 'Payment recorded', fcg_money_format($amount) . ' was recorded against ' . $invoice['invoice_no'] . '. Balance: ' . fcg_money_format($invoice['balance_due']) . '.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($invoice), 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/invoices/payment/review') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $invoiceId = (int) fcg_value($input, 'invoice_id', 0);
    $paymentId = (int) fcg_value($input, 'payment_id', 0);
    $action = strtolower(trim(fcg_value($input, 'action', fcg_value($input, 'decision', ''))));
    $data = fcg_load_portal_data();
    $invoiceIndex = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($invoiceIndex < 0 || !in_array($action, ['approve', 'reject'], true)) {
        fcg_json(['error' => 'Payment review request is invalid'], 400);
    }
    $invoice = &$data['invoices'][$invoiceIndex];
    $paymentIndex = fcg_find_record_index(fcg_value($invoice, 'payment_history', []), $paymentId);
    if ($paymentIndex < 0 || fcg_value($invoice['payment_history'][$paymentIndex], 'status', '') !== 'Pending') {
        fcg_json(['error' => 'Pending payment record not found'], 404);
    }
    $payment = &$invoice['payment_history'][$paymentIndex];
    $currentBalance = fcg_money_value(fcg_value(fcg_normalize_invoice($invoice), 'balance_due', fcg_value($invoice, 'total', 0)));
    $requestedAmount = fcg_money_value(fcg_value($payment, 'amount', 0));
    $verifiedAmount = fcg_money_value(fcg_value($input, 'verified_amount', fcg_value($input, 'amount', $requestedAmount)));
    if ($action === 'approve' && ($verifiedAmount <= 0 || $verifiedAmount > $currentBalance + 0.01)) {
        fcg_json(['error' => 'Verified payment amount must be greater than zero and cannot exceed the outstanding balance'], 400);
    }
    $reviewNotes = trim(fcg_value($input, 'admin_notes', fcg_value($input, 'reason', '')));
    $payment['status'] = $action === 'approve' ? 'Approved' : 'Rejected';
    $payment['admin_review_status'] = $payment['status'];
    $payment['admin_notes'] = $reviewNotes;
    $payment['rejection_reason'] = $action === 'reject' ? $reviewNotes : '';
    $payment['verified_amount'] = $action === 'approve' ? $verifiedAmount : 0;
    $payment['reviewed_by'] = fcg_value(fcg_require_admin(), 'name', 'Administrator');
    $payment['reviewed_at'] = date('c');
    if (!isset($payment['balance_before'])) {
        $payment['balance_before'] = $currentBalance;
    }
    if (trim(fcg_value($input, 'payment_method', '')) !== '') {
        $payment['method'] = trim(fcg_value($input, 'payment_method', ''));
        $payment['payment_method'] = $payment['method'];
    }
    if ($action === 'approve') {
        $payment['amount'] = $verifiedAmount;
        $invoice['amount_paid'] = min(fcg_money_value($invoice['total']), fcg_money_value(fcg_value($invoice, 'amount_paid', 0)) + $verifiedAmount);
        $invoice['payment_date'] = fcg_value($payment, 'date', date('Y-m-d'));
        $invoice['payment_method'] = fcg_value($payment, 'method', 'Bank transfer');
    }
    fcg_payment_status($invoice);
    $payment['balance_remaining'] = $invoice['balance_due'];
    $payment['balance_after'] = $invoice['balance_due'];
    if (!isset($payment['projected_balance_after'])) {
        $payment['projected_balance_after'] = max(0, $currentBalance - $requestedAmount);
    }
    $invoice['updated_at'] = date('c');
    $message = ucfirst($action) . 'd payment verification for invoice ' . $invoice['invoice_no'];
    fcg_log_activity($data, 'payment', $message, $invoice['client_id']);
    fcg_notify_record($data, $invoice, 'payment', $action === 'approve' ? 'Payment approved' : 'Proof of payment rejected', $action === 'approve' ? 'Your payment for ' . $invoice['invoice_no'] . ' was approved. Balance: ' . fcg_money_format($invoice['balance_due']) . '.' : 'The proof submitted for ' . $invoice['invoice_no'] . ' was rejected. ' . fcg_value($payment, 'admin_notes', 'Please upload the correct proof.'));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($invoice), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/services') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $name = trim(fcg_value($input, 'name', ''));
    if ($name === '') {
        fcg_json(['error' => 'Service name is required'], 400);
    }
    $data = fcg_load_portal_data();
    $businessUnit = fcg_business_unit_from_input($input, null, fcg_user());
    $service = [
        'id' => fcg_next_record_id($data['services']),
        'business_unit' => $businessUnit,
        'name' => $name,
        'description' => trim(fcg_value($input, 'description', '')),
        'default_price' => fcg_money_value(fcg_value($input, 'default_price', 0)),
        'category' => trim(fcg_value($input, 'category', 'General')),
        'vat' => fcg_bool_value(fcg_value($input, 'vat', false)),
        'active' => fcg_bool_value(fcg_value($input, 'active', true)),
        'created_at' => date('c'),
    ];
    array_unshift($data['services'], $service);
    fcg_log_activity($data, 'service', 'Service ' . $service['name'] . ' added', 0, $businessUnit);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'service' => $service, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/services/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $serviceId = (int) fcg_value($input, 'id', 0);
    $name = trim(fcg_value($input, 'name', ''));
    if ($name === '') {
        fcg_json(['error' => 'Service name is required'], 400);
    }
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['services'], $serviceId);
    if ($index < 0) {
        fcg_json(['error' => 'Service not found'], 404);
    }
    $businessUnit = fcg_business_unit_from_input($input, $data['services'][$index], fcg_user());
    $data['services'][$index]['business_unit'] = $businessUnit;
    $data['services'][$index]['name'] = $name;
    $data['services'][$index]['description'] = trim(fcg_value($input, 'description', ''));
    $data['services'][$index]['default_price'] = fcg_money_value(fcg_value($input, 'default_price', 0));
    $data['services'][$index]['category'] = trim(fcg_value($input, 'category', 'General'));
    $data['services'][$index]['vat'] = fcg_bool_value(fcg_value($input, 'vat', false));
    $data['services'][$index]['active'] = fcg_bool_value(fcg_value($input, 'active', true));
    $data['services'][$index]['updated_at'] = date('c');
    fcg_log_activity($data, 'service', 'Service ' . $name . ' updated', 0, $businessUnit);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'service' => $data['services'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/services/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $serviceId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['services'], $serviceId);
    if ($index < 0) {
        fcg_json(['error' => 'Service not found'], 404);
    }
    $removed = $data['services'][$index];
    array_splice($data['services'], $index, 1);
    fcg_log_activity($data, 'service', 'Service ' . fcg_value($removed, 'name', '') . ' deleted');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => $removed, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/settings/test-email' && $method === 'POST') {
    $admin = fcg_require_admin();
    $input = fcg_request_data();
    $email = strtolower(trim(fcg_value($input, 'email', fcg_value($admin, 'email', ''))));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fcg_json(['error' => 'Enter a valid test email address'], 400);
    $businessUnit = fcg_business_unit_from_input($input, null, $admin);
    $brand = fcg_brand_config($businessUnit);
    $result = fcg_send_customer_email_result($email, $brand['label'] . ' portal email test', [
        'This is a production email delivery test from the ' . $brand['label'] . ' Client Portal.',
        '', 'Portal: ' . $brand['portal_url'], '', $brand['label'],
    ], $businessUnit);
    $data = fcg_load_portal_data();
    array_unshift($data['email_history'], [
        'id' => fcg_next_record_id($data['email_history']), 'sent_at' => date('c'),
        'sent_by' => fcg_value($admin, 'name', 'Administrator'), 'sent_to' => $email,
        'business_unit' => $businessUnit,
        'document_type' => 'SMTP Test', 'document_number' => '', 'delivery_method' => fcg_value($result, 'transport', 'Email'),
        'delivery_status' => fcg_value($result, 'status', 'Failed'),
    ]);
    fcg_log_activity($data, 'email', 'Production email test ' . strtolower(fcg_value($result, 'status', 'Failed')) . ' for ' . $email, 0, $businessUnit);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'sent' => !empty($result['sent']), 'status' => fcg_value($result, 'status', 'Failed'), 'transport' => fcg_value($result, 'transport', 'Email'), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/templates') {
    fcg_require_admin();
    if ($method !== 'POST') {
        $payload = fcg_admin_clients_payload();
        fcg_json(['templates' => $payload['templates'], 'admin' => $payload]);
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    $templates = $data['templates'];
    foreach (['default_quote_template', 'default_invoice_template', 'default_theme', 'footer_notes', 'vat_rate'] as $field) {
        if (isset($input[$field])) {
            $templates[$field] = trim($input[$field]);
        }
    }
    foreach (['company', 'banking', 'terms', 'prefixes', 'notifications', 'security', 'smtp', 'whatsapp', 'email_templates', 'whatsapp_templates'] as $group) {
        if (isset($input[$group]) && is_array($input[$group])) {
            if ($group === 'smtp' && array_key_exists('password', $input[$group]) && trim((string) $input[$group]['password']) === '') {
                unset($input[$group]['password']);
            }
            $templates[$group] = array_replace(fcg_value($templates, $group, []), $input[$group]);
        }
    }
    $data['templates'] = $templates;
    fcg_log_activity($data, 'template', 'Business templates updated');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'templates' => fcg_admin_templates($templates), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/settings/logo') {
    fcg_require_admin();
    if ($method !== 'POST') fcg_json(['error' => 'Method not allowed'], 405);
    if (!isset($_FILES['logo'])) fcg_json(['error' => 'Select a company logo file'], 400);
    $validated = fcg_validate_upload($_FILES['logo'], ['png', 'jpg', 'jpeg', 'svg', 'webp'], 5 * 1024 * 1024);
    $dir = fcg_data_dir() . '/branding';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'company-logo-' . date('YmdHis') . '-' . $validated['safe_name'];
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $path)) fcg_json(['error' => 'Could not save the company logo'], 500);
    $pdfPath = '';
    if (isset($_FILES['pdf_logo']) && (int) fcg_value($_FILES['pdf_logo'], 'error', UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $pdfValidated = fcg_validate_upload($_FILES['pdf_logo'], ['jpg', 'jpeg'], 5 * 1024 * 1024);
        $pdfPath = $dir . '/company-logo-pdf-' . date('YmdHis') . '.jpg';
        if (!move_uploaded_file($_FILES['pdf_logo']['tmp_name'], $pdfPath)) fcg_json(['error' => 'Could not prepare the PDF logo'], 500);
    } elseif (in_array($validated['extension'], ['jpg', 'jpeg'], true)) {
        $pdfPath = $path;
    }
    $data = fcg_load_portal_data();
    $oldPath = fcg_value($data['templates']['company'], 'logo_path', '');
    $oldPdf = fcg_value($data['templates']['company'], 'logo_pdf_path', '');
    $data['templates']['company']['logo_path'] = $path;
    $data['templates']['company']['logo_pdf_path'] = $pdfPath;
    $data['templates']['company']['logo_url'] = fcg_portal_path('/api/settings/logo');
    fcg_log_activity($data, 'template', 'Company document logo updated');
    fcg_save_portal_data($data);
    foreach (array_unique([$oldPath, $oldPdf]) as $old) if ($old && $old !== $path && $old !== $pdfPath) fcg_delete_uploaded_file($old);
    fcg_json(['ok' => true, 'logo_url' => fcg_portal_path('/api/settings/logo'), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/settings/logo' && $method === 'GET') {
    fcg_require_user();
    $data = fcg_load_portal_data();
    $path = fcg_value($data['templates']['company'], 'logo_path', '');
    if ($path === '' || !is_file($path)) $path = fcg_first_file('static/logo.png');
    if (!$path || !is_file($path)) fcg_json(['error' => 'Logo not found'], 404);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
    header('Content-Type: ' . fcg_value($types, $extension, 'application/octet-stream'));
    header('Cache-Control: private, max-age=3600');
    readfile($path); exit;
}

if ($requestPath === '/api/admin/backup-admin' && $method === 'POST') {
    fcg_require_admin();
    $input = fcg_request_data();
    $email = strtolower(trim(fcg_value($input, 'email', '')));
    $name = trim(fcg_value($input, 'name', 'Backup Administrator'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fcg_json(['error' => 'Enter a valid backup administrator email'], 400);
    $users = fcg_load_users();
    foreach ($users as $user) if (strtolower(fcg_value($user, 'email', '')) === $email) fcg_json(['error' => 'This email already has portal access'], 409);
    $accessCode = fcg_generate_access_code();
    $account = [
        'id' => fcg_next_user_id($users),
        'name' => $name,
        'email' => $email,
        'role' => 'admin',
        'active' => true,
        'google_id' => '',
        'apple_id' => '',
        'auth_provider' => 'access_code',
        'last_login_at' => '',
        'password_hash' => password_hash($accessCode, PASSWORD_DEFAULT),
        'created_at' => date('c'),
    ];
    $users[] = $account; fcg_save_users($users);
    $data = fcg_load_portal_data(); fcg_log_activity($data, 'security', 'Backup administrator account created'); fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'account' => fcg_public_user($account), 'access_code' => $accessCode]);
}

$financialRoutes = [
    '/api/admin/proformas' => ['proformas', 'create'], '/api/admin/proformas/update' => ['proformas', 'update'], '/api/admin/proformas/delete' => ['proformas', 'delete'], '/api/admin/proformas/duplicate' => ['proformas', 'duplicate'],
    '/api/admin/credit-notes' => ['credit_notes', 'create'], '/api/admin/credit-notes/update' => ['credit_notes', 'update'], '/api/admin/credit-notes/delete' => ['credit_notes', 'delete'], '/api/admin/credit-notes/duplicate' => ['credit_notes', 'duplicate'],
];
if (isset($financialRoutes[$requestPath])) {
    fcg_require_admin();
    if ($method !== 'POST') fcg_json(['error' => 'Method not allowed'], 405);
    list($collection, $action) = $financialRoutes[$requestPath];
    $input = fcg_request_data(); $data = fcg_load_portal_data();
    $label = $collection === 'proformas' ? 'Pro-forma invoice' : 'Credit note';
    if ($action === 'create') {
        $record = fcg_financial_document_from_input($input, $data, $collection);
        array_unshift($data[$collection], $record);
    } else {
        $index = fcg_find_record_index($data[$collection], (int) fcg_value($input, 'id', 0));
        if ($index < 0) fcg_json(['error' => $label . ' not found'], 404);
        if ($action === 'delete') {
            array_splice($data[$collection], $index, 1);
            fcg_log_activity($data, 'document', $label . ' deleted'); fcg_save_portal_data($data);
            fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
        }
        if ($action === 'duplicate') {
            $input = $data[$collection][$index]; unset($input['id'], $input['proforma_no'], $input['credit_note_no']);
            $input['title'] = fcg_value($input, 'title', $label) . ' Copy'; $input['status'] = 'Draft';
            $record = fcg_financial_document_from_input($input, $data, $collection);
            array_unshift($data[$collection], $record);
        } else {
            $record = fcg_financial_document_from_input($input, $data, $collection, $data[$collection][$index]);
            $data[$collection][$index] = $record;
        }
    }
    $number = fcg_value($record, 'proforma_no', fcg_value($record, 'credit_note_no', ''));
    fcg_log_activity($data, 'document', $label . ' ' . $number . ' saved', fcg_value($record, 'client_id', 0));
    $recordBrand = fcg_brand_config(fcg_record_business_unit($record));
    fcg_notify_record($data, $record, $collection === 'proformas' ? 'proforma' : 'credit_note', $label . ' ready', $number . ' is available in your ' . $recordBrand['label'] . ' records.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'record' => $record, 'admin' => fcg_admin_clients_payload()], $action === 'create' || $action === 'duplicate' ? 201 : 200);
}

if ($requestPath === '/api/admin/statements') {
    fcg_require_admin();
    if ($method === 'GET') fcg_json(['statements' => fcg_admin_clients_payload()['statements']]);
    if ($method !== 'POST') fcg_json(['error' => 'Method not allowed'], 405);
    $input = fcg_request_data(); $data = fcg_load_portal_data();
    $record = fcg_statement_from_input($input, $data); array_unshift($data['statements'], $record);
    fcg_log_activity($data, 'statement', 'Statement ' . $record['statement_no'] . ' generated', $record['client_id']);
    fcg_notify_record($data, $record, 'statement', 'Statement of account ready', 'Your statement ' . $record['statement_no'] . ' is ready.');
    fcg_save_portal_data($data); fcg_json(['ok' => true, 'statement' => $record, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/statements/delete' && $method === 'POST') {
    fcg_require_admin(); $input = fcg_request_data(); $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['statements'], (int) fcg_value($input, 'id', 0));
    if ($index < 0) fcg_json(['error' => 'Statement not found'], 404);
    array_splice($data['statements'], $index, 1); fcg_save_portal_data($data); fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/notifications' && $method === 'GET') {
    $user = fcg_require_user(); $data = fcg_load_portal_data();
    $activeUnit = fcg_active_business_unit($user);
    $items = fcg_value($user, 'role', '') === 'admin'
        ? array_values(array_filter($data['notifications'], function ($item) use ($activeUnit) { return fcg_record_matches_business_unit($item, $activeUnit); }))
        : array_values(array_filter($data['notifications'], function ($item) use ($user, $activeUnit) {
            return (int) fcg_value($item, 'client_id', 0) === (int) $user['id'] && fcg_record_matches_business_unit($item, $activeUnit);
        }));
    fcg_json(['notifications' => array_slice($items, 0, 100), 'unread' => count(array_filter($items, function ($item) { return empty($item['read_at']); }))]);
}

if ($requestPath === '/api/notifications/read' && $method === 'POST') {
    $user = fcg_require_user(); $input = fcg_request_data(); $data = fcg_load_portal_data();
    $id = (int) fcg_value($input, 'id', 0); $markAll = fcg_bool_value(fcg_value($input, 'all', false));
    $activeUnit = fcg_active_business_unit($user);
    foreach ($data['notifications'] as &$item) {
        $allowed = fcg_record_matches_business_unit($item, $activeUnit)
            && (fcg_value($user, 'role', '') === 'admin' || (int) fcg_value($item, 'client_id', 0) === (int) $user['id']);
        if ($allowed && ($markAll || (int) fcg_value($item, 'id', 0) === $id)) $item['read_at'] = date('c');
    }
    unset($item); fcg_save_portal_data($data); fcg_json(['ok' => true, 'dashboard' => fcg_dashboard()]);
}

if ($requestPath === '/api/profile/notifications' && $method === 'POST') {
    $user = fcg_require_user(); $input = fcg_request_data(); $users = fcg_load_users();
    $index = fcg_find_record_index($users, (int) $user['id']); if ($index < 0) fcg_json(['error' => 'Profile not found'], 404);
    $preference = fcg_value($input, 'notification_preference', 'Email');
    if (!in_array($preference, ['Email', 'WhatsApp', 'Both'], true)) fcg_json(['error' => 'Select a notification preference'], 400);
    $users[$index]['notification_preference'] = $preference;
    $users[$index]['notification_consent'] = fcg_bool_value(fcg_value($input, 'notification_consent', false));
    $users[$index]['phone'] = trim(fcg_value($input, 'phone', fcg_value($users[$index], 'phone', '')));
    fcg_save_users($users); $_SESSION['fcg_user'] = fcg_public_user($users[$index]);
    fcg_json(['ok' => true, 'user' => $_SESSION['fcg_user']]);
}

if ($requestPath === '/api/profile/access-code' && $method === 'POST') {
    $user = fcg_require_user(); $input = fcg_request_data(); $users = fcg_load_users();
    $index = fcg_find_record_index($users, (int) $user['id']);
    if ($index < 0) fcg_json(['error' => 'Portal account not found'], 404);
    $current = (string) fcg_value($input, 'current_access_code', '');
    $new = (string) fcg_value($input, 'new_access_code', '');
    $confirm = (string) fcg_value($input, 'confirm_access_code', '');
    if (!password_verify($current, fcg_value($users[$index], 'password_hash', ''))) fcg_json(['error' => 'The current access code is incorrect'], 401);
    if (strlen($new) < 12 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) fcg_json(['error' => 'Use at least 12 characters with letters and numbers'], 400);
    if ($new !== $confirm) fcg_json(['error' => 'The new access codes do not match'], 400);
    if (password_verify($new, fcg_value($users[$index], 'password_hash', ''))) fcg_json(['error' => 'Choose a different access code'], 400);
    $users[$index]['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
    $users[$index]['access_code_changed_at'] = date('c');
    fcg_save_users($users);
    $data = fcg_load_portal_data();
    fcg_log_activity($data, 'security', (fcg_value($user, 'role', '') === 'admin' ? 'Administrator' : 'Client') . ' changed their portal access code', (int) $user['id']);
    fcg_save_portal_data($data);
    session_regenerate_id(true);
    fcg_json(['ok' => true, 'message' => 'Access code changed successfully.']);
}

if ($requestPath === '/api/admin/notifications/send' && $method === 'POST') {
    $admin = fcg_require_admin(); $input = fcg_request_data(); $data = fcg_load_portal_data();
    $clientId = (int) fcg_value($input, 'client_id', 0); $guestId = (int) fcg_value($input, 'guest_customer_id', 0);
    $title = trim(fcg_value($input, 'title', 'Future Creative Group notification')); $message = trim(fcg_value($input, 'message', ''));
    if ($message === '') fcg_json(['error' => 'Enter a notification message'], 400);
    $channel = fcg_value($input, 'channel', 'Portal');
    $businessUnit = fcg_business_unit_from_input($input, null, $admin);
    if ($clientId > 0 && !fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $brand = fcg_brand_config($businessUnit);
    $notification = fcg_add_notification($data, $clientId, fcg_value($input, 'type', 'general'), $title, $message, fcg_value($input, 'related_type', ''), (int) fcg_value($input, 'related_id', 0), $channel, $guestId, $businessUnit);
    $recipient = [];
    if ($clientId > 0) foreach (fcg_load_users() as $candidate) if ((int) fcg_value($candidate, 'id', 0) === $clientId) $recipient = $candidate;
    if ($guestId > 0) { $idx = fcg_find_record_index($data['guest_customers'], $guestId); if ($idx >= 0) $recipient = $data['guest_customers'][$idx]; }
    $response = ['ok' => true, 'notification' => $notification];
    $notification['sent_by'] = fcg_value($admin, 'name', 'Administrator');
    if ($channel === 'Email') {
        $sent = fcg_send_customer_email(fcg_value($recipient, 'email', ''), $title, [$message, '', 'Portal: ' . $brand['portal_url']], $businessUnit);
        $response['email_sent'] = $sent;
        $notification['status'] = $sent ? 'Sent' : 'Failed';
        $notification['sent_to'] = fcg_value($recipient, 'email', '');
    } elseif ($channel === 'WhatsApp') {
        $phone = fcg_customer_phone(fcg_value($recipient, 'phone', '')); if (strlen($phone) < 10) fcg_json(['error' => 'Add a valid WhatsApp number first'], 400);
        $response['whatsapp_url'] = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message . "\n\n" . $brand['portal_url']);
        $notification['status'] = 'Pending';
        $notification['sent_to'] = $phone;
    }
    $notificationIndex = fcg_find_record_index($data['notifications'], $notification['id']);
    if ($notificationIndex >= 0) $data['notifications'][$notificationIndex] = $notification;
    $response['notification'] = $notification;
    fcg_log_activity($data, 'notification', $title . ' sent by ' . fcg_value($admin, 'name', 'Administrator'), $clientId, $businessUnit);
    fcg_save_portal_data($data); $response['admin'] = fcg_admin_clients_payload(); fcg_json($response, 201);
}

if ($requestPath === '/api/admin/notes' && $method === 'POST') {
    $admin = fcg_require_admin(); $input = fcg_request_data(); $data = fcg_load_portal_data();
    $note = trim(fcg_value($input, 'note', '')); if ($note === '') fcg_json(['error' => 'Enter an internal note'], 400);
    $businessUnit = fcg_business_unit_from_input($input, null, $admin);
    $record = ['id' => fcg_next_record_id($data['internal_notes']), 'business_unit' => $businessUnit, 'entity_type' => fcg_value($input, 'entity_type', 'client'), 'entity_id' => (int) fcg_value($input, 'entity_id', 0), 'client_id' => (int) fcg_value($input, 'client_id', 0), 'guest_customer_id' => (int) fcg_value($input, 'guest_customer_id', 0), 'note' => $note, 'created_by' => fcg_value($admin, 'name', 'Administrator'), 'created_at' => date('c')];
    array_unshift($data['internal_notes'], $record); fcg_log_activity($data, 'note', 'Internal note added to ' . $record['entity_type'], $record['client_id'], $businessUnit); fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'note' => $record, 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/reminders/run' && $method === 'POST') {
    fcg_require_admin(); $data = fcg_load_portal_data(); $created = 0; $today = strtotime(date('Y-m-d'));
    foreach ($data['invoices'] as $invoice) {
        $invoice = fcg_normalize_invoice($invoice); if (in_array($invoice['payment_status'], ['Paid', 'Paid In Full', 'Cancelled'], true)) continue;
        $days = (int) floor((strtotime($invoice['due_date']) - $today) / 86400);
        if (in_array($days, [7, 0], true) || $days < 0) { fcg_notify_record($data, $invoice, 'invoice_reminder', $days < 0 ? 'Overdue invoice reminder' : 'Invoice payment reminder', $invoice['invoice_no'] . ' has a balance of ' . fcg_money_format($invoice['balance_due']) . '.'); $created++; }
    }
    foreach (array_merge($data['subscriptions'], $data['hosting_records']) as $record) {
        $date = fcg_value($record, 'renewal_date', ''); if ($date === '') continue; $days = (int) floor((strtotime($date) - $today) / 86400);
        if (in_array($days, [30, 14, 7, 0], true)) { fcg_notify_record($data, $record, 'renewal', 'Service renewal reminder', fcg_value($record, 'service_name', fcg_value($record, 'domain_name', 'Service')) . ' renews on ' . $date . '.'); $created++; }
    }
    array_unshift($data['reminder_history'], ['id' => fcg_next_record_id($data['reminder_history']), 'created' => $created, 'run_at' => date('c')]);
    fcg_save_portal_data($data); fcg_json(['ok' => true, 'created' => $created, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/reports/ageing' && $method === 'GET') {
    fcg_require_admin(); $data = fcg_load_portal_data(); $report = fcg_invoice_ageing($data['invoices']); $format = strtolower(fcg_value($_GET, 'format', 'json'));
    if ($format === 'csv') {
        $csv = "Bucket,Customer,Invoice,Due Date,Balance Due\n";
        foreach ($report['buckets'] as $bucket => $items) foreach ($items as $invoice) $csv .= '"' . $bucket . '","' . str_replace('"', '""', fcg_value($invoice, 'client_company', fcg_value($invoice, 'client_name', ''))) . '","' . fcg_value($invoice, 'invoice_no', '') . '","' . fcg_value($invoice, 'due_date', '') . '","' . fcg_money_value($invoice['balance_due']) . '"' . "\n";
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="invoice-ageing-' . date('Y-m-d') . '.csv"'); echo $csv; exit;
    }
    if ($format === 'pdf') {
        $items = [];
        foreach ($report['buckets'] as $bucket => $invoices) {
            foreach ($invoices as $invoice) {
                $items[] = [
                    'description' => fcg_value($invoice, 'invoice_no', '') . ' | ' . $bucket . ' | due ' . fcg_value($invoice, 'due_date', ''),
                    'quantity' => 1,
                    'unit_price' => fcg_money_value(fcg_value($invoice, 'balance_due', 0)),
                    'discount' => 0,
                ];
            }
        }
        $record = [
            'invoice_no' => 'AGE-' . date('Ymd'), 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'client_name' => 'Accounts Receivable', 'client_company' => 'Future Creative Group Pty Ltd',
            'title' => 'Invoice ageing and outstanding balances', 'items' => $items ?: [['description' => 'No outstanding invoices', 'quantity' => 1, 'unit_price' => 0, 'discount' => 0]],
            'subtotal' => $report['total_outstanding'], 'discount' => 0, 'vat' => 0, 'total' => $report['total_outstanding'],
            'amount_paid' => 0, 'balance_due' => $report['total_outstanding'], 'payment_status' => 'Outstanding',
            'terms' => 'Internal administrative report generated on ' . date('Y-m-d') . '.',
        ];
        $pdf = fcg_pdf_document('AGEING REPORT', 'Internal', $record, $data['templates']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice-ageing-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
    }
    fcg_json(['report' => $report]);
}

if ($requestPath === '/api/admin/export' && $method === 'GET') {
    fcg_require_admin(); $data = fcg_load_portal_data(); $type = fcg_value($_GET, 'type', 'clients');
    $maps = ['guests' => 'guest_customers', 'quotes' => 'quotes', 'invoices' => 'invoices', 'payments' => 'invoices', 'statements' => 'statements', 'documents' => 'documents', 'tickets' => 'tickets', 'notifications' => 'notifications', 'activity' => 'activity'];
    if ($type === 'clients') $records = fcg_client_accounts(); elseif (isset($maps[$type])) $records = $data[$maps[$type]]; else fcg_json(['error' => 'Export type not supported'], 400);
    if ($type === 'payments') { $payments = []; foreach ($records as $invoice) foreach (fcg_value($invoice, 'payment_history', []) as $payment) { $payment['invoice_no'] = fcg_value($invoice, 'invoice_no', ''); $payments[] = $payment; } $records = $payments; }
    $headers = [];
    foreach ($records as $record) foreach (array_keys($record) as $key) if (!in_array($key, $headers, true) && !is_array($record[$key])) $headers[] = $key;
    $csv = implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $headers)) . "\n";
    foreach ($records as $record) $csv .= implode(',', array_map(function ($key) use ($record) { return '"' . str_replace('"', '""', (string) fcg_value($record, $key, '')) . '"'; }, $headers)) . "\n";
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="fcg-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($type)) . '-' . date('Y-m-d') . '.csv"'); echo $csv; exit;
}

if ($requestPath === '/api/admin/documents/share') {
    $admin = fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $action = strtolower(trim(fcg_value($input, 'action', 'create')));
    $data = fcg_load_portal_data();
    if ($action === 'disable') {
        $linkIndex = fcg_find_record_index($data['document_links'], (int) fcg_value($input, 'link_id', 0));
        if ($linkIndex < 0) fcg_json(['error' => 'Secure link not found'], 404);
        $data['document_links'][$linkIndex]['active'] = false;
        $data['document_links'][$linkIndex]['disabled_at'] = date('c');
        fcg_log_activity($data, 'share', 'Secure document link disabled');
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }

    $documentType = strtolower(trim(fcg_value($input, 'document_type', fcg_value($input, 'type', ''))));
    $documentId = (int) fcg_value($input, 'document_id', fcg_value($input, 'id', 0));
    if (!in_array($documentType, ['quote', 'invoice', 'receipt', 'proforma', 'credit_note', 'statement'], true)) {
        fcg_json(['error' => 'Select a supported business document to share'], 400);
    }
    $collections = ['quote' => 'quotes', 'invoice' => 'invoices', 'receipt' => 'invoices', 'proforma' => 'proformas', 'credit_note' => 'credit_notes', 'statement' => 'statements'];
    $collection = $collections[$documentType];
    $recordIndex = fcg_find_record_index($data[$collection], $documentId);
    if ($recordIndex < 0) fcg_json(['error' => 'Document not found'], 404);
    $record = $documentType === 'quote' ? fcg_normalize_quote($data[$collection][$recordIndex]) : fcg_normalize_invoice($data[$collection][$recordIndex]);
    $businessUnit = fcg_record_business_unit($record);
    $brand = fcg_brand_config($businessUnit);
    $unitTemplates = fcg_templates_for_business_unit($data['templates'], $businessUnit);
    if ($documentType === 'receipt' && !in_array(fcg_value($record, 'payment_status', ''), ['Paid', 'Paid In Full'], true)) fcg_json(['error' => 'A receipt can be shared only after full payment'], 409);
    if ($documentType === 'receipt' && fcg_value($record, 'receipt_no', '') === '') {
        $data[$collection][$recordIndex]['receipt_no'] = fcg_business_number($data['invoices'], fcg_value(fcg_value($unitTemplates, 'prefixes', []), 'receipt', 'FCG/RCPT'), 'receipt_no');
        $record['receipt_no'] = $data[$collection][$recordIndex]['receipt_no'];
    }
    $token = bin2hex(fcg_random_bytes(24));
    $days = max(1, min(30, (int) fcg_value($input, 'expiry_days', fcg_value($input, 'expires_days', 7))));
    $link = [
        'id' => fcg_next_record_id($data['document_links']),
        'document_type' => $documentType,
        'document_id' => $documentId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('c', strtotime('+' . $days . ' days')),
        'active' => true,
        'access_count' => 0,
        'created_by' => (int) fcg_value($admin, 'id', 0),
        'business_unit' => $businessUnit,
        'created_at' => date('c'),
    ];
    array_unshift($data['document_links'], $link);
    $secureUrl = rtrim(fcg_value($brand, 'portal_url', 'https://futurecreativegroup.co.za/portal/'), '/') . '/document?token=' . rawurlencode($token);
    $name = fcg_value($record, 'client_name', 'Customer');
    $numberKeys = ['quote' => 'quote_no', 'invoice' => 'invoice_no', 'receipt' => 'receipt_no', 'proforma' => 'proforma_no', 'credit_note' => 'credit_note_no', 'statement' => 'statement_no'];
    $number = fcg_value($record, $numberKeys[$documentType], '');
    $method = strtolower(trim(fcg_value($input, 'method', fcg_value($input, 'channel', 'link'))));
    $includePaymentDetails = in_array($documentType, ['invoice', 'proforma', 'statement'], true)
        || ($documentType === 'quote' && fcg_money_value(fcg_value($record, 'deposit_required', 0)) > 0);
    $paymentLines = $includePaymentDetails ? fcg_payment_message_lines($unitTemplates, $number) : [];
    $lines = $documentType === 'quote' ? array_merge([
        'Good day ' . $name . ',', '',
        'Please find your quotation from ' . $brand['label'] . '.', '',
        'Quotation No: ' . $number,
        'Amount: ' . fcg_money_format(fcg_value($record, 'total', 0)),
        'Valid Until: ' . fcg_value($record, 'valid_until', ''),
    ], $paymentLines, [
        '',
        'You can view/download your quotation here:', $secureUrl, '',
        'Kind regards,', $brand['label'],
    ]) : ($documentType === 'invoice' ? array_merge([
        'Good day ' . $name . ',', '',
        'Please find your invoice from ' . $brand['label'] . '.', '',
        'Invoice No: ' . $number,
        'Total Amount: ' . fcg_money_format(fcg_value($record, 'total', 0)),
        'Amount Paid: ' . fcg_money_format(fcg_value($record, 'amount_paid', 0)),
        'Balance Due: ' . fcg_money_format(fcg_value($record, 'balance_due', 0)),
        'Due Date: ' . fcg_value($record, 'due_date', ''),
    ], $paymentLines, [
        '',
        'You can view/download your invoice here:', $secureUrl, '',
        'Kind regards,', $brand['label'],
    ]) : array_merge([
        'Good day ' . $name . ',', '',
        'Your ' . str_replace('_', ' ', $documentType) . ' from ' . $brand['label'] . ' is ready.', '',
        'Reference: ' . $number,
        'Amount: ' . fcg_money_format($documentType === 'receipt' ? fcg_value($record, 'amount_paid', fcg_value($record, 'total', 0)) : ($documentType === 'statement' ? fcg_value($record, 'balance_due', 0) : fcg_value($record, 'total', 0))),
        ($documentType === 'proforma' ? 'Valid Until: ' : 'Document Date: ') . ($documentType === 'proforma' ? fcg_value($record, 'due_date', '') : fcg_value($record, 'invoice_date', '')),
    ], $paymentLines, [
        '',
        'View or download the secure document here:', $secureUrl, '',
        'Kind regards,', $brand['label'],
    ]));
    $response = ['ok' => true, 'secure_url' => $secureUrl, 'link' => $link];
    if ($method === 'whatsapp') {
        $phone = fcg_customer_phone(fcg_value($record, 'client_phone', ''));
        if (strlen($phone) < 10) fcg_json(['error' => 'Add a valid customer WhatsApp number before sharing'], 400);
        $response['whatsapp_url'] = 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
        fcg_log_activity($data, 'share', ucfirst($documentType) . ' ' . $number . ' prepared for WhatsApp', fcg_value($record, 'client_id', 0));
    } elseif ($method === 'email') {
        $email = strtolower(trim(fcg_value($record, 'client_email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fcg_json(['error' => 'Add a valid customer email address before sending'], 400);
        $subject = ucwords(str_replace('_', ' ', $documentType)) . ' ' . $number . ' - ' . $brand['label'];
        $emailResult = fcg_send_customer_email_result($email, $subject, $lines, $businessUnit);
        $sent = !empty($emailResult['sent']);
        array_unshift($data['email_history'], [
            'id' => fcg_next_record_id($data['email_history']),
            'sent_at' => date('c'),
            'sent_by' => fcg_value($admin, 'name', 'Administrator'),
            'sent_to' => $email,
            'business_unit' => $businessUnit,
            'document_type' => $documentType,
            'document_number' => $number,
            'delivery_method' => 'Email',
            'delivery_status' => fcg_value($emailResult, 'status', $sent ? 'Sent' : 'Failed'),
        ]);
        $response['email_sent'] = $sent;
        $response['email_status'] = fcg_value($emailResult, 'status', $sent ? 'Sent' : 'Failed');
        fcg_log_activity($data, 'share', ucfirst($documentType) . ' ' . $number . ' sent by email to ' . $email, fcg_value($record, 'client_id', 0));
    } else {
        fcg_log_activity($data, 'share', 'Secure link created for ' . $documentType . ' ' . $number, fcg_value($record, 'client_id', 0));
    }
    if (in_array($method, ['email', 'whatsapp'], true)) {
        $deliveryStatus = $method === 'email' ? fcg_value($response, 'email_status', 'Failed') : 'Pending';
        $delivery = fcg_add_notification(
            $data,
            (int) fcg_value($record, 'client_id', 0),
            $documentType,
            ucwords(str_replace('_', ' ', $documentType)) . ' delivery update',
            $number . ' was ' . ($deliveryStatus === 'Sent' ? 'sent' : ($method === 'whatsapp' ? 'prepared for WhatsApp' : 'not delivered by email')) . '.',
            $documentType,
            $documentId,
            ucfirst($method),
            (int) fcg_value($record, 'guest_customer_id', 0),
            $businessUnit
        );
        $deliveryIndex = fcg_find_record_index($data['notifications'], $delivery['id']);
        if ($deliveryIndex >= 0) {
            $data['notifications'][$deliveryIndex]['status'] = $deliveryStatus;
            $data['notifications'][$deliveryIndex]['sent_by'] = fcg_value($admin, 'name', 'Administrator');
            $data['notifications'][$deliveryIndex]['sent_to'] = $method === 'email' ? fcg_value($record, 'client_email', '') : fcg_customer_phone(fcg_value($record, 'client_phone', ''));
        }
    }
    if ($documentType === 'quote' && in_array(fcg_value($record, 'status', 'Draft'), ['Draft', 'Viewed'], true)) {
        $data['quotes'][$recordIndex]['status'] = 'Sent';
        $data['quotes'][$recordIndex]['sent_at'] = date('c');
    }
    fcg_save_portal_data($data);
    $response['admin'] = fcg_admin_clients_payload();
    fcg_json($response, 201);
}

if ($requestPath === '/api/onboarding') {
    $user = fcg_require_user();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = $_POST;
    $clientId = fcg_value($user, 'role', '') === 'admin'
        ? (int) fcg_value($input, 'client_id', 0)
        : (int) fcg_value($user, 'id', 0);
    $businessUnit = fcg_value($user, 'role', '') === 'admin'
        ? fcg_business_unit_from_input($input, null, $user)
        : fcg_active_business_unit($user);
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'A valid client account is required'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    $company = trim(fcg_value($input, 'company_name', ''));
    $contact = trim(fcg_value($input, 'contact_person', ''));
    $email = strtolower(trim(fcg_value($input, 'email', '')));
    if ($company === '' || $contact === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fcg_json(['error' => 'Company, contact person and valid email are required'], 400);
    }
    if (!fcg_bool_value(fcg_value($input, 'popia_consent', false))) {
        fcg_json(['error' => 'POPIA consent is required to submit onboarding information'], 400);
    }
    $filePath = '';
    $fileName = '';
    if (isset($_FILES['file']) && (int) fcg_value($_FILES['file'], 'error', UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $validated = fcg_validate_upload($_FILES['file'], ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'zip'], 15 * 1024 * 1024);
        $uploadDir = fcg_data_dir() . '/onboarding';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $validated['safe_name'];
        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            fcg_json(['error' => 'Could not save the onboarding file'], 500);
        }
    }
    $data = fcg_load_portal_data();
    $record = [
        'id' => fcg_next_record_id($data['onboarding']),
        'client_id' => $clientId,
        'business_unit' => $businessUnit,
        'company_name' => $company,
        'contact_person' => $contact,
        'email' => $email,
        'phone' => trim(fcg_value($input, 'phone', '')),
        'address' => trim(fcg_value($input, 'address', '')),
        'service_required' => trim(fcg_value($input, 'service_required', '')),
        'budget' => trim(fcg_value($input, 'budget', '')),
        'deadline' => fcg_value($input, 'deadline', ''),
        'file_path' => $filePath,
        'file_name' => $fileName,
        'status' => 'Submitted',
        'admin_notes' => '',
        'popia_consent' => true,
        'consent_at' => date('c'),
        'created_at' => date('c'),
    ];
    array_unshift($data['onboarding'], $record);
    fcg_log_activity($data, 'onboarding', 'Onboarding submission received from ' . $company, $clientId, $businessUnit);
    $brand = fcg_brand_config($businessUnit);
    fcg_send_notification('New ' . $brand['label'] . ' Onboarding Submission', [
        'A secure onboarding form was submitted.',
        'Company: ' . $company,
        'Contact: ' . $contact,
        'Email: ' . $email,
        'Service: ' . $record['service_required'],
        'Portal: ' . $brand['portal_url'],
    ], $email, fcg_value(fcg_value($brand, 'contacts', []), 'general', 'admin@futurecreativegroup.co.za'));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'onboarding' => fcg_public_onboarding($record), 'dashboard' => fcg_dashboard()], 201);
}

if ($requestPath === '/api/admin/onboarding/update' || $requestPath === '/api/admin/onboarding/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['onboarding'], (int) fcg_value($input, 'id', 0));
    if ($index < 0) {
        fcg_json(['error' => 'Onboarding record not found'], 404);
    }
    if ($requestPath === '/api/admin/onboarding/delete') {
        $removed = $data['onboarding'][$index];
        if (!empty($removed['file_path'])) {
            fcg_delete_uploaded_file($removed['file_path']);
        }
        array_splice($data['onboarding'], $index, 1);
        fcg_log_activity($data, 'onboarding', 'Onboarding record deleted', fcg_value($removed, 'client_id', 0));
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }
    $data['onboarding'][$index]['status'] = fcg_value($input, 'status', fcg_value($data['onboarding'][$index], 'status', 'Submitted'));
    $data['onboarding'][$index]['admin_notes'] = trim(fcg_value($input, 'admin_notes', fcg_value($data['onboarding'][$index], 'admin_notes', '')));
    $data['onboarding'][$index]['updated_at'] = date('c');
    fcg_log_activity($data, 'onboarding', 'Onboarding status updated', $data['onboarding'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'onboarding' => fcg_public_onboarding($data['onboarding'][$index]), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/subscriptions' || $requestPath === '/api/admin/subscriptions/update' || $requestPath === '/api/admin/subscriptions/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    if ($requestPath === '/api/admin/subscriptions') {
        $record = fcg_subscription_from_input($input, $data);
        array_unshift($data['subscriptions'], $record);
        fcg_log_activity($data, 'subscription', 'Recurring service ' . $record['service_name'] . ' created', $record['client_id']);
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'subscription' => $record, 'admin' => fcg_admin_clients_payload()], 201);
    }
    $index = fcg_find_record_index($data['subscriptions'], (int) fcg_value($input, 'id', 0));
    if ($index < 0) {
        fcg_json(['error' => 'Recurring billing record not found'], 404);
    }
    if ($requestPath === '/api/admin/subscriptions/delete') {
        $removed = $data['subscriptions'][$index];
        array_splice($data['subscriptions'], $index, 1);
        fcg_log_activity($data, 'subscription', 'Recurring service deleted', fcg_value($removed, 'client_id', 0));
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }
    $data['subscriptions'][$index] = fcg_subscription_from_input($input, $data, $data['subscriptions'][$index]);
    fcg_log_activity($data, 'subscription', 'Recurring service updated', $data['subscriptions'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'subscription' => $data['subscriptions'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/subscriptions/run') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $data = fcg_load_portal_data();
    $generated = [];
    foreach ($data['subscriptions'] as &$subscription) {
        $period = substr(fcg_value($subscription, 'next_billing_date', ''), 0, 7);
        if (fcg_value($subscription, 'status', 'Active') !== 'Active'
            || empty($subscription['auto_invoice'])
            || fcg_value($subscription, 'next_billing_date', '') === ''
            || fcg_value($subscription, 'next_billing_date', '') > date('Y-m-d')
            || fcg_value($subscription, 'last_invoice_period', '') === $period) {
            continue;
        }
        $invoiceInput = [
            'client_id' => $subscription['client_id'],
            'business_unit' => fcg_record_business_unit($subscription),
            'title' => $subscription['service_name'],
            'item_description' => fcg_value($subscription, 'description', $subscription['service_name']),
            'quantity' => 1,
            'unit_price' => $subscription['amount'],
            'payment_status' => 'Unpaid',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'notes' => 'Recurring ' . strtolower(fcg_value($subscription, 'billing_cycle', 'monthly')) . ' service invoice.',
        ];
        $invoice = fcg_invoice_from_input($invoiceInput, $data);
        array_unshift($data['invoices'], $invoice);
        $generated[] = $invoice['invoice_no'];
        $subscription['last_invoice_period'] = $period;
        $cycle = fcg_value($subscription, 'billing_cycle', 'Monthly');
        $modifier = $cycle === 'Annually' ? '+1 year' : ($cycle === 'Quarterly' ? '+3 months' : '+1 month');
        $subscription['next_billing_date'] = date('Y-m-d', strtotime($modifier, strtotime($subscription['next_billing_date'])));
        $subscription['updated_at'] = date('c');
        fcg_log_activity($data, 'invoice', 'Recurring invoice ' . $invoice['invoice_no'] . ' generated', $invoice['client_id']);
        fcg_notify_record($data, $invoice, 'invoice', 'Recurring service invoice created', $invoice['invoice_no'] . ' is due on ' . $invoice['due_date'] . '.');
    }
    unset($subscription);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'generated' => $generated, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/hosting-records' || $requestPath === '/api/admin/hosting-records/update' || $requestPath === '/api/admin/hosting-records/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    if ($requestPath === '/api/admin/hosting-records') {
        $record = fcg_hosting_record_from_input($input, $data);
        array_unshift($data['hosting_records'], $record);
        fcg_log_activity($data, 'hosting', 'Hosting record added for ' . $record['domain_name'], $record['client_id']);
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'hosting_record' => $record, 'admin' => fcg_admin_clients_payload()], 201);
    }
    $index = fcg_find_record_index($data['hosting_records'], (int) fcg_value($input, 'id', 0));
    if ($index < 0) {
        fcg_json(['error' => 'Hosting record not found'], 404);
    }
    if ($requestPath === '/api/admin/hosting-records/delete') {
        $removed = $data['hosting_records'][$index];
        array_splice($data['hosting_records'], $index, 1);
        fcg_log_activity($data, 'hosting', 'Hosting record deleted', fcg_value($removed, 'client_id', 0));
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }
    $data['hosting_records'][$index] = fcg_hosting_record_from_input($input, $data, $data['hosting_records'][$index]);
    fcg_log_activity($data, 'hosting', 'Hosting record updated for ' . $data['hosting_records'][$index]['domain_name'], $data['hosting_records'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'hosting_record' => $data['hosting_records'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/job-cards' || $requestPath === '/api/admin/job-cards/update' || $requestPath === '/api/admin/job-cards/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $isMultipart = stripos(fcg_value($_SERVER, 'CONTENT_TYPE', ''), 'multipart/form-data') !== false;
    $input = $isMultipart ? $_POST : fcg_request_data();
    $data = fcg_load_portal_data();
    if ($requestPath === '/api/admin/job-cards') {
        $record = fcg_job_card_from_input($input, $data);
    } else {
        $index = fcg_find_record_index($data['job_cards'], (int) fcg_value($input, 'id', 0));
        if ($index < 0) {
            fcg_json(['error' => 'Job card not found'], 404);
        }
        if ($requestPath === '/api/admin/job-cards/delete') {
            $removed = $data['job_cards'][$index];
            if (!empty($removed['before_photo_path'])) fcg_delete_uploaded_file($removed['before_photo_path']);
            if (!empty($removed['after_photo_path'])) fcg_delete_uploaded_file($removed['after_photo_path']);
            array_splice($data['job_cards'], $index, 1);
            fcg_log_activity($data, 'job', 'Job card deleted', fcg_value($removed, 'client_id', 0));
            fcg_save_portal_data($data);
            fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
        }
        $record = fcg_job_card_from_input($input, $data, $data['job_cards'][$index]);
    }
    $uploadDir = fcg_data_dir() . '/job-photos';
    foreach (['before_photo', 'after_photo'] as $field) {
        if (!isset($_FILES[$field]) || (int) fcg_value($_FILES[$field], 'error', UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $validated = fcg_validate_upload($_FILES[$field], ['png', 'jpg', 'jpeg'], 8 * 1024 * 1024);
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $validated['safe_name'];
        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $filePath)) {
            fcg_json(['error' => 'Could not save job card photo'], 500);
        }
        $record[$field . '_path'] = $filePath;
        $record[$field . '_file'] = $fileName;
    }
    if ($requestPath === '/api/admin/job-cards') {
        array_unshift($data['job_cards'], $record);
        $statusCode = 201;
    } else {
        $data['job_cards'][$index] = $record;
        $statusCode = 200;
    }
    fcg_log_activity($data, 'job', 'Job card ' . $record['job_no'] . ' saved', $record['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'job_card' => fcg_public_job_card($record), 'admin' => fcg_admin_clients_payload()], $statusCode);
}

if ($requestPath === '/api/feedback') {
    $user = fcg_require_user();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    if (fcg_value($user, 'role', '') !== 'client') {
        fcg_json(['error' => 'Client access required'], 403);
    }
    $input = fcg_request_data();
    $rating = max(1, min(5, (int) fcg_value($input, 'rating', 0)));
    $testimonial = trim(fcg_value($input, 'testimonial', ''));
    if ($testimonial === '') {
        fcg_json(['error' => 'Please provide your feedback'], 400);
    }
    $data = fcg_load_portal_data();
    $businessUnit = fcg_active_business_unit($user);
    $record = [
        'id' => fcg_next_record_id($data['feedback']),
        'client_id' => (int) $user['id'],
        'business_unit' => $businessUnit,
        'project_id' => (int) fcg_value($input, 'project_id', 0),
        'rating' => $rating,
        'testimonial' => $testimonial,
        'recommend' => fcg_bool_value(fcg_value($input, 'recommend', false)),
        'status' => 'Pending Approval',
        'created_at' => date('c'),
    ];
    array_unshift($data['feedback'], $record);
    fcg_log_activity($data, 'feedback', 'Client feedback submitted', $record['client_id'], $businessUnit);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'feedback' => $record, 'dashboard' => fcg_dashboard()], 201);
}

if ($requestPath === '/api/admin/feedback/update' || $requestPath === '/api/admin/feedback/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['feedback'], (int) fcg_value($input, 'id', 0));
    if ($index < 0) {
        fcg_json(['error' => 'Feedback record not found'], 404);
    }
    if ($requestPath === '/api/admin/feedback/delete') {
        $removed = $data['feedback'][$index];
        array_splice($data['feedback'], $index, 1);
        fcg_log_activity($data, 'feedback', 'Feedback record deleted', fcg_value($removed, 'client_id', 0));
        fcg_save_portal_data($data);
        fcg_json(['ok' => true, 'admin' => fcg_admin_clients_payload()]);
    }
    $data['feedback'][$index]['status'] = fcg_value($input, 'status', 'Approved');
    $data['feedback'][$index]['updated_at'] = date('c');
    fcg_log_activity($data, 'feedback', 'Feedback approved for publication', $data['feedback'][$index]['client_id']);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'feedback' => $data['feedback'][$index], 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/documents') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }

    $isMultipart = stripos(fcg_value($_SERVER, 'CONTENT_TYPE', ''), 'multipart/form-data') !== false;
    $input = $isMultipart ? $_POST : fcg_request_data();
    $clientId = (int) fcg_value($input, 'client_id', 0);
    $title = trim(fcg_value($input, 'title', ''));
    $description = trim(fcg_value($input, 'description', ''));
    $status = trim(fcg_value($input, 'status', 'Available'));
    $type = trim(fcg_value($input, 'type', 'Document'));
    $url = trim(fcg_value($input, 'url', ''));
    $businessUnit = fcg_business_unit_from_input($input, null, fcg_user());
    $filePath = '';
    $fileName = '';
    $mimeType = '';

    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid client'], 400);
    }
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    if (strlen($title) < 2) {
        fcg_json(['error' => 'Document title is required'], 400);
    }

    if (isset($_FILES['file']) && is_array($_FILES['file']) && (int) $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validated = fcg_validate_upload($_FILES['file'], ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'zip'], 15 * 1024 * 1024);
        $uploadDir = fcg_data_dir() . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $safeOriginal = $validated['safe_name'];
        $fileName = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $safeOriginal;
        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            fcg_json(['error' => 'Could not save uploaded document'], 500);
        }
        $mimeType = fcg_value($_FILES['file'], 'type', 'application/octet-stream') ?: 'application/octet-stream';
        $url = '';
        $status = $status ?: 'File';
    } elseif ($url === '') {
        fcg_json(['error' => 'Add a document URL or upload a file'], 400);
    }

    $data = fcg_load_portal_data();
    $document = [
        'id' => fcg_next_record_id($data['documents']),
        'client_id' => $clientId,
        'business_unit' => $businessUnit,
        'title' => $title,
        'description' => $description,
        'type' => $type,
        'status' => $status,
        'url' => $url ?: '',
        'file_path' => $filePath,
        'file_name' => $fileName,
        'mime_type' => $mimeType,
        'created_at' => date('c'),
    ];
    array_unshift($data['documents'], $document);
    fcg_log_activity($data, 'document', 'Document ' . $document['title'] . ' uploaded', $document['client_id'], $businessUnit);
    fcg_notify_record($data, $document, 'document', 'New document uploaded', $document['title'] . ' is ready in your client documents.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'document' => fcg_public_document($document), 'admin' => fcg_admin_clients_payload()], 201);
}

if ($requestPath === '/api/admin/documents/update') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $documentId = (int) fcg_value($input, 'id', 0);
    $clientId = (int) fcg_value($input, 'client_id', 0);
    $title = trim(fcg_value($input, 'title', ''));
    $description = trim(fcg_value($input, 'description', ''));
    $status = trim(fcg_value($input, 'status', 'Available'));
    $type = trim(fcg_value($input, 'type', 'Document'));
    $url = trim(fcg_value($input, 'url', ''));
    if (!fcg_client_exists($clientId)) {
        fcg_json(['error' => 'Please select a valid active client'], 400);
    }
    if (strlen($title) < 2) {
        fcg_json(['error' => 'Document title is required'], 400);
    }

    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['documents'], $documentId);
    if ($index < 0) {
        fcg_json(['error' => 'Document not found'], 404);
    }
    $businessUnit = fcg_business_unit_from_input($input, $data['documents'][$index], fcg_user());
    if (!fcg_client_can_use_business_unit($clientId, $businessUnit)) {
        fcg_json(['error' => 'The selected client is not assigned to this business workspace'], 400);
    }
    if (empty($data['documents'][$index]['file_path']) && $url === '') {
        fcg_json(['error' => 'A document URL is required for link documents'], 400);
    }
    $data['documents'][$index]['client_id'] = $clientId;
    $data['documents'][$index]['business_unit'] = $businessUnit;
    $data['documents'][$index]['title'] = $title;
    $data['documents'][$index]['description'] = $description;
    $data['documents'][$index]['type'] = $type;
    $data['documents'][$index]['status'] = $status;
    if (empty($data['documents'][$index]['file_path'])) {
        $data['documents'][$index]['url'] = $url;
    }
    $data['documents'][$index]['updated_at'] = date('c');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'document' => fcg_public_document($data['documents'][$index]), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/documents/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $documentId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['documents'], $documentId);
    if ($index < 0) {
        fcg_json(['error' => 'Document not found'], 404);
    }
    $removed = $data['documents'][$index];
    if (!empty($removed['file_path'])) {
        fcg_delete_uploaded_file($removed['file_path']);
    }
    array_splice($data['documents'], $index, 1);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => fcg_public_document($removed), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/quotes/action') {
    $user = fcg_require_user();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $quoteId = (int) fcg_value($input, 'id', 0);
    $action = fcg_value($input, 'action', '');
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['quotes'], $quoteId);
    if ($index < 0 || (fcg_value($user, 'role', '') !== 'admin' && ((int) $data['quotes'][$index]['client_id'] !== (int) $user['id'] || !fcg_record_matches_business_unit($data['quotes'][$index], fcg_active_business_unit($user))))) {
        fcg_json(['error' => 'Quotation not found'], 404);
    }
    if ($action === 'approve') {
        $data['quotes'][$index]['status'] = 'Approved';
        $data['quotes'][$index]['approval_status'] = 'Approved';
        $data['quotes'][$index]['digital_signature'] = trim(fcg_value($input, 'signature', fcg_value($user, 'name', 'Client')));
        $data['quotes'][$index]['acceptance_date'] = date('Y-m-d');
        $data['quotes'][$index]['approval_record'] = 'Approved through secure client portal by ' . fcg_value($user, 'email', 'client');
        $message = 'Quotation ' . $data['quotes'][$index]['quote_no'] . ' approved';
    } elseif ($action === 'decline') {
        $data['quotes'][$index]['status'] = 'Declined';
        $data['quotes'][$index]['approval_status'] = 'Declined';
        $message = 'Quotation ' . $data['quotes'][$index]['quote_no'] . ' declined';
    } elseif ($action === 'changes') {
        $data['quotes'][$index]['status'] = 'Changes Requested';
        $data['quotes'][$index]['approval_status'] = 'Changes Requested';
        $data['quotes'][$index]['change_request'] = trim(fcg_value($input, 'message', ''));
        $message = 'Changes requested for quotation ' . $data['quotes'][$index]['quote_no'];
    } else {
        fcg_json(['error' => 'Invalid quotation action'], 400);
    }
    $data['quotes'][$index]['responded_at'] = date('c');
    fcg_notify_business_event($message, $data['quotes'][$index], fcg_client_contact($data['quotes'][$index]['client_id']));
    fcg_log_activity($data, 'quote', $message, $data['quotes'][$index]['client_id']);
    fcg_notify_record($data, $data['quotes'][$index], 'quote', 'Quotation response recorded', $message . '.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'quote' => $data['quotes'][$index], 'dashboard' => fcg_dashboard()]);
}

if ($requestPath === '/api/invoices/proof/download' && $method === 'GET') {
    $user = fcg_require_user();
    $invoiceId = (int) fcg_value($_GET, 'invoice_id', 0);
    $paymentId = (int) fcg_value($_GET, 'payment_id', 0);
    $data = fcg_load_portal_data();
    $invoiceIndex = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($invoiceIndex < 0 || (fcg_value($user, 'role', '') !== 'admin' && ((int) fcg_value($data['invoices'][$invoiceIndex], 'client_id', 0) !== (int) $user['id'] || !fcg_record_matches_business_unit($data['invoices'][$invoiceIndex], fcg_active_business_unit($user))))) {
        fcg_json(['error' => 'Payment verification file not found'], 404);
    }
    $paymentIndex = fcg_find_record_index(fcg_value($data['invoices'][$invoiceIndex], 'payment_history', []), $paymentId);
    $payment = $paymentIndex >= 0 ? $data['invoices'][$invoiceIndex]['payment_history'][$paymentIndex] : [];
    $path = fcg_value($payment, 'proof_path', '');
    if ($path === '' || !is_file($path) || strpos(realpath($path), realpath(fcg_data_dir())) !== 0) {
        fcg_json(['error' => 'Payment verification file not found'], 404);
    }
    $data['document_access'][] = [
        'id' => fcg_next_record_id($data['document_access']),
        'business_unit' => fcg_record_business_unit($data['invoices'][$invoiceIndex]),
        'type' => 'payment_proof',
        'record_id' => $invoiceId,
        'user_id' => (int) fcg_value($user, 'id', 0),
        'accessed_at' => date('c'),
    ];
    fcg_save_portal_data($data);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: inline; filename="' . basename(fcg_value($payment, 'proof_file', 'payment-proof')) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($requestPath === '/api/invoices/proof') {
    $user = fcg_require_user();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $invoiceId = (int) fcg_value($_POST, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['invoices'], $invoiceId);
    if ($index < 0 || (fcg_value($user, 'role', '') !== 'admin' && ((int) $data['invoices'][$index]['client_id'] !== (int) $user['id'] || !fcg_record_matches_business_unit($data['invoices'][$index], fcg_active_business_unit($user))))) {
        fcg_json(['error' => 'Invoice not found'], 404);
    }
    if (!isset($_FILES['proof'])) {
        fcg_json(['error' => 'Upload a valid proof of payment file'], 400);
    }
    $amount = fcg_money_value(fcg_value($_POST, 'amount', 0));
    if ($amount <= 0) {
        fcg_json(['error' => 'Enter the payment amount shown on the proof of payment'], 400);
    }
    $currentBalance = fcg_money_value(fcg_value(fcg_normalize_invoice($data['invoices'][$index]), 'balance_due', fcg_value($data['invoices'][$index], 'total', 0)));
    if ($amount > $currentBalance + 0.01) fcg_json(['error' => 'Payment amount cannot exceed the outstanding balance'], 400);
    $validated = fcg_validate_upload($_FILES['proof'], ['pdf', 'png', 'jpg', 'jpeg', 'webp'], 10 * 1024 * 1024);
    $uploadDir = fcg_data_dir() . '/payment-proofs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $safeOriginal = $validated['safe_name'];
    $fileName = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $safeOriginal;
    $filePath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $filePath)) {
        fcg_json(['error' => 'Could not save proof of payment'], 500);
    }
    if (!isset($data['invoices'][$index]['payment_history']) || !is_array($data['invoices'][$index]['payment_history'])) {
        $data['invoices'][$index]['payment_history'] = [];
    }
    $payment = [
        'id' => fcg_next_record_id($data['invoices'][$index]['payment_history']),
        'payment_id' => fcg_next_record_id($data['invoices'][$index]['payment_history']),
        'client_id' => (int) fcg_value($data['invoices'][$index], 'client_id', 0),
        'guest_customer_id' => (int) fcg_value($data['invoices'][$index], 'guest_customer_id', 0),
        'invoice_id' => (int) fcg_value($data['invoices'][$index], 'id', 0),
        'invoice_no' => fcg_value($data['invoices'][$index], 'invoice_no', ''),
        'amount' => $amount,
        'verified_amount' => 0,
        'date' => fcg_value($_POST, 'date', fcg_value($_POST, 'payment_date', date('Y-m-d'))),
        'payment_date' => fcg_value($_POST, 'date', fcg_value($_POST, 'payment_date', date('Y-m-d'))),
        'method' => trim(fcg_value($_POST, 'method', fcg_value($_POST, 'payment_method', 'Bank transfer'))),
        'payment_method' => trim(fcg_value($_POST, 'method', fcg_value($_POST, 'payment_method', 'Bank transfer'))),
        'notes' => trim(fcg_value($_POST, 'notes', '')),
        'reference' => trim(fcg_value($_POST, 'notes', '')),
        'admin_notes' => '',
        'rejection_reason' => '',
        'status' => fcg_value($user, 'role', '') === 'admin' ? 'Approved' : 'Pending',
        'admin_review_status' => fcg_value($user, 'role', '') === 'admin' ? 'Approved' : 'Pending',
        'proof_path' => $filePath,
        'proof_file' => $fileName,
        'file_name' => $safeOriginal,
        'file_type' => $validated['extension'],
        'uploaded_at' => date('c'),
        'balance_before' => $currentBalance,
        'projected_balance_after' => max(0, $currentBalance - $amount),
        'balance_after' => fcg_value($user, 'role', '') === 'admin' ? max(0, $currentBalance - $amount) : $currentBalance,
        'balance_remaining' => fcg_value($data['invoices'][$index], 'balance_due', fcg_value($data['invoices'][$index], 'total', 0)),
        'created_at' => date('c'),
    ];
    $data['invoices'][$index]['payment_history'][] = $payment;
    if ($payment['status'] === 'Approved') {
        $data['invoices'][$index]['amount_paid'] = fcg_money_value(fcg_value($data['invoices'][$index], 'amount_paid', 0)) + $amount;
    }
    fcg_payment_status($data['invoices'][$index]);
    $data['invoices'][$index]['payment_history'][count($data['invoices'][$index]['payment_history']) - 1]['balance_remaining'] = $data['invoices'][$index]['balance_due'];
    $data['invoices'][$index]['updated_at'] = date('c');
    fcg_notify_business_event('Payment verification document received', $data['invoices'][$index], fcg_client_contact($data['invoices'][$index]['client_id']));
    fcg_log_activity($data, 'payment', 'Payment verification of ' . fcg_money_format($amount) . ' submitted for invoice ' . $data['invoices'][$index]['invoice_no'], $data['invoices'][$index]['client_id']);
    fcg_notify_record($data, $data['invoices'][$index], 'payment', 'Proof of payment received', 'Your proof for ' . $data['invoices'][$index]['invoice_no'] . ' is pending administrative review.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'invoice' => fcg_public_invoice($data['invoices'][$index]), 'dashboard' => fcg_dashboard()]);
}

if ($requestPath === '/api/tickets/attachment' && $method === 'GET') {
    $user = fcg_require_user();
    $ticketId = (int) fcg_value($_GET, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['tickets'], $ticketId);
    if ($index < 0) {
        fcg_json(['error' => 'Support attachment not found'], 404);
    }
    $ticket = $data['tickets'][$index];
    $allowed = fcg_value($user, 'role', '') === 'admin'
        || ((int) fcg_value($ticket, 'client_id', 0) === (int) $user['id'] && fcg_record_matches_business_unit($ticket, fcg_active_business_unit($user)));
    $path = fcg_value($ticket, 'attachment_path', '');
    $dataDir = realpath(fcg_data_dir());
    $realPath = $path !== '' ? realpath($path) : false;
    if (!$allowed || !$realPath || !$dataDir || strpos($realPath, $dataDir) !== 0 || !is_file($realPath)) {
        fcg_json(['error' => 'Support attachment not found'], 404);
    }
    array_unshift($data['document_access'], [
        'id' => fcg_next_record_id($data['document_access']),
        'business_unit' => fcg_record_business_unit($ticket),
        'type' => 'ticket_attachment',
        'record_id' => $ticketId,
        'user_id' => (int) fcg_value($user, 'id', 0),
        'accessed_at' => date('c'),
    ]);
    fcg_save_portal_data($data);
    header('Content-Type: ' . (fcg_value($ticket, 'attachment_mime', '') ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes(fcg_value($ticket, 'attachment_file', 'support-attachment')) . '"');
    header('Content-Length: ' . filesize($realPath));
    readfile($realPath);
    exit;
}

if ($requestPath === '/api/admin/tickets') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(fcg_admin_clients_payload());
    }
    $input = fcg_request_data();
    $ticketId = (int) fcg_value($input, 'id', 0);
    $status = trim(fcg_value($input, 'status', ''));
    $requestType = trim(fcg_value($input, 'request_type', fcg_value($input, 'category', '')));
    $priority = trim(fcg_value($input, 'priority', ''));
    $message = trim(fcg_value($input, 'message', ''));
    $assignedTechnician = trim(fcg_value($input, 'assigned_technician', ''));
    $adminResponse = trim(fcg_value($input, 'admin_response', ''));
    $completionNotes = trim(fcg_value($input, 'completion_notes', ''));
    $validStatuses = ['Open', 'Pending', 'In Progress', 'Waiting for Client', 'Resolved', 'Closed'];
    $validTypes = array_values(array_unique(array_merge(fcg_support_categories('future_creative_group'), fcg_support_categories('fcg_cloud'), ['Networking / Wi-Fi', 'Billing / Quotation'])));
    $validPriorities = ['Low', 'Medium', 'High', 'Normal', 'Urgent', 'Critical'];
    if (!in_array($status, $validStatuses, true)) {
        fcg_json(['error' => 'Please select a valid ticket status'], 400);
    }
    if ($requestType !== '' && !in_array($requestType, $validTypes, true)) {
        fcg_json(['error' => 'Please select a valid support type'], 400);
    }
    if ($priority !== '' && !in_array($priority, $validPriorities, true)) {
        fcg_json(['error' => 'Please select a valid priority'], 400);
    }
    if ($message !== '' && strlen($message) < 8) {
        fcg_json(['error' => 'Please add a short request description'], 400);
    }

    $data = fcg_load_portal_data();
    $updated = null;
    foreach ($data['tickets'] as &$ticket) {
        if ((int) fcg_value($ticket, 'id', 0) === $ticketId) {
            $ticket['status'] = $status;
            if ($requestType !== '') {
                $ticket['request_type'] = $requestType;
                $ticket['category'] = $requestType;
            }
            if ($priority !== '') {
                $ticket['priority'] = $priority;
            }
            if ($message !== '') {
                $ticket['message'] = $message;
            }
            if ($assignedTechnician !== '') {
                $ticket['assigned_technician'] = $assignedTechnician;
            }
            if ($adminResponse !== '') {
                $ticket['admin_response'] = $adminResponse;
            }
            $clientResponse = trim(fcg_value($input, 'client_response', ''));
            if ($clientResponse !== '') {
                $ticket['client_response'] = $clientResponse;
            }
            if ($completionNotes !== '') {
                $ticket['completion_notes'] = $completionNotes;
            }
            if (in_array($status, ['Resolved', 'Closed'], true)) {
                $ticket['closed_at'] = date('c');
                $ticket['resolution_date'] = date('Y-m-d');
            }
            $ticket['updated_at'] = date('c');
            $updated = $ticket;
            break;
        }
    }
    unset($ticket);
    if (!$updated) {
        fcg_json(['error' => 'Ticket not found'], 404);
    }
    fcg_log_activity($data, 'ticket', 'Support ticket ' . fcg_value($updated, 'ticket_no', '') . ' updated to ' . $status, fcg_value($updated, 'client_id', 0));
    fcg_notify_record($data, $updated, 'ticket', 'Support ticket updated', fcg_value($updated, 'ticket_no', '') . ' is now ' . $status . ($adminResponse !== '' ? '. Team response: ' . $adminResponse : '.'));
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'ticket' => fcg_public_ticket($updated), 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/admin/tickets/delete') {
    fcg_require_admin();
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }
    $input = fcg_request_data();
    $ticketId = (int) fcg_value($input, 'id', 0);
    $data = fcg_load_portal_data();
    $index = fcg_find_record_index($data['tickets'], $ticketId);
    if ($index < 0) {
        fcg_json(['error' => 'Ticket not found'], 404);
    }
    $removed = $data['tickets'][$index];
    if (!empty($removed['attachment_path'])) {
        fcg_delete_uploaded_file($removed['attachment_path']);
    }
    array_splice($data['tickets'], $index, 1);
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'deleted' => $removed, 'admin' => fcg_admin_clients_payload()]);
}

if ($requestPath === '/api/documents/download' && $method === 'GET') {
    $user = fcg_require_user();
    $documentId = (int) fcg_value($_GET, 'id', 0);
    $data = fcg_load_portal_data();
    foreach ($data['documents'] as $document) {
        $allowed = fcg_value($user, 'role', '') === 'admin'
            || (int) fcg_value($document, 'client_id', 0) === (int) $user['id']
            || (int) fcg_value($document, 'client_id', 0) === 0;
        $allowed = $allowed && (fcg_value($user, 'role', '') === 'admin' || fcg_record_matches_business_unit($document, fcg_active_business_unit($user)));
        if ((int) fcg_value($document, 'id', 0) === $documentId && $allowed && !empty($document['file_path']) && is_file($document['file_path'])) {
            header('Content-Type: ' . (fcg_value($document, 'mime_type', '') ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . addslashes(fcg_value($document, 'file_name', '') ?: 'document') . '"');
            header('Content-Length: ' . filesize($document['file_path']));
            readfile($document['file_path']);
            exit;
        }
    }
    fcg_json(['error' => 'Document not found'], 404);
}

if (in_array($requestPath, ['/api/quotes/pdf', '/api/invoices/pdf', '/api/invoices/receipt', '/api/proformas/pdf', '/api/credit-notes/pdf', '/api/statements/pdf'], true) && $method === 'GET') {
    $user = fcg_require_user();
    $recordId = (int) fcg_value($_GET, 'id', 0);
    $data = fcg_load_portal_data();
    $isQuote = $requestPath === '/api/quotes/pdf';
    $routeMap = [
        '/api/quotes/pdf' => ['quotes', 'QUOTATION', 'quote_no'],
        '/api/invoices/pdf' => ['invoices', 'INVOICE', 'invoice_no'],
        '/api/invoices/receipt' => ['invoices', 'RECEIPT', 'receipt_no'],
        '/api/proformas/pdf' => ['proformas', 'PRO-FORMA INVOICE', 'proforma_no'],
        '/api/credit-notes/pdf' => ['credit_notes', 'CREDIT NOTE', 'credit_note_no'],
        '/api/statements/pdf' => ['statements', 'STATEMENT', 'statement_no'],
    ];
    list($recordsKey, $title, $numberKey) = $routeMap[$requestPath];
    $index = fcg_find_record_index($data[$recordsKey], $recordId);
    if ($index < 0) {
        fcg_json(['error' => 'Document not found'], 404);
    }
    $record = $isQuote ? fcg_normalize_quote($data[$recordsKey][$index]) : fcg_normalize_invoice($data[$recordsKey][$index]);
    $allowed = fcg_value($user, 'role', '') === 'admin'
        || ((int) fcg_value($record, 'client_id', 0) === (int) $user['id'] && fcg_record_matches_business_unit($record, fcg_active_business_unit($user)));
    if (!$allowed) {
        fcg_json(['error' => 'Document not found'], 404);
    }
    if ($isQuote && fcg_value($user, 'role', '') !== 'admin' && fcg_value($record, 'status', '') === 'Sent') {
        $data[$recordsKey][$index]['status'] = 'Viewed';
        $data[$recordsKey][$index]['viewed_at'] = date('c');
        fcg_log_activity($data, 'quote', 'Quotation ' . $record['quote_no'] . ' viewed by client', $record['client_id']);
        fcg_save_portal_data($data);
        $record['status'] = 'Viewed';
    }
    $badge = $isQuote ? fcg_value($record, 'status', 'Draft') : ($title === 'RECEIPT' ? 'Paid In Full' : fcg_value($record, 'status', fcg_value($record, 'payment_status', 'Issued')));
    $number = fcg_value($record, $numberKey, strtolower(str_replace(' ', '-', $title)));
    $receiptCreated = false;
    if ($title === 'RECEIPT') {
        if (!in_array(fcg_value($record, 'payment_status', ''), ['Paid', 'Paid In Full'], true)) {
            fcg_json(['error' => 'A receipt is available only after the invoice is paid in full'], 409);
        }
        if (fcg_value($data[$recordsKey][$index], 'receipt_no', '') === '') {
            $unitTemplates = fcg_templates_for_business_unit($data['templates'], fcg_record_business_unit($record));
            $data[$recordsKey][$index]['receipt_no'] = fcg_business_number($data['invoices'], fcg_value(fcg_value($unitTemplates, 'prefixes', []), 'receipt', 'FCG/RCPT'), 'receipt_no');
            $data[$recordsKey][$index]['receipt_generated_at'] = date('c');
            $receiptCreated = true;
        }
        $record['receipt_no'] = $data[$recordsKey][$index]['receipt_no'];
        if ($receiptCreated) {
            $brand = fcg_brand_config(fcg_record_business_unit($record));
            fcg_notify_record($data, $record, 'receipt', 'Receipt ready', 'Receipt ' . $record['receipt_no'] . ' is ready in your ' . $brand['label'] . ' portal.');
        }
    }
    $templateOverride = trim(fcg_value($_GET, 'template', ''));
    if ($templateOverride !== '' && fcg_value($user, 'role', '') === 'admin'
        && isset($data['templates']['available_templates'][$templateOverride])) {
        $data['templates']['_active_template'] = $templateOverride;
    }
    array_unshift($data['document_access'], [
        'id' => fcg_next_record_id($data['document_access']),
        'business_unit' => fcg_record_business_unit($record),
        'type' => strtolower($title),
        'record_id' => $recordId,
        'user_id' => (int) fcg_value($user, 'id', 0),
        'accessed_at' => date('c'),
    ]);
    fcg_log_activity($data, 'document', $title . ' ' . $number . ' downloaded', fcg_value($record, 'client_id', 0));
    fcg_save_portal_data($data);
    $pdfSettings = fcg_templates_for_business_unit($data['templates'], fcg_record_business_unit($record));
    $pdf = fcg_pdf_document($title, $badge, $record, $pdfSettings);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if ($requestPath === '/api/tickets') {
    $user = fcg_require_user();
    if ($method === 'GET') {
        $data = fcg_load_portal_data();
        fcg_json(['tickets' => array_map('fcg_public_ticket', fcg_client_records($data['tickets'], $user))]);
    }
    if ($method !== 'POST') {
        fcg_json(['error' => 'Method not allowed'], 405);
    }

    $isMultipart = stripos(fcg_value($_SERVER, 'CONTENT_TYPE', ''), 'multipart/form-data') !== false;
    $input = $isMultipart ? $_POST : fcg_request_data();
    $businessUnit = fcg_active_business_unit($user);
    $requestType = trim(fcg_value($input, 'request_type', ''));
    $priority = trim(fcg_value($input, 'priority', 'Normal'));
    $message = trim(fcg_value($input, 'message', ''));
    $popiaConsent = fcg_bool_value(fcg_value($input, 'popia_consent', false));
    $validTypes = array_values(array_unique(array_merge(fcg_support_categories($businessUnit), ['Networking / Wi-Fi', 'Billing / Quotation'])));
    $validPriorities = ['Low', 'Medium', 'High', 'Normal', 'Urgent', 'Critical'];

    if (!in_array($requestType, $validTypes, true)) {
        fcg_json(['error' => 'Please select a valid support type'], 400);
    }
    if (!in_array($priority, $validPriorities, true)) {
        fcg_json(['error' => 'Please select a valid priority'], 400);
    }
    if (strlen($message) < 8) {
        fcg_json(['error' => 'Please add a short request description'], 400);
    }
    if (!$popiaConsent) {
        fcg_json(['error' => 'POPIA consent is required to submit a support request'], 400);
    }

    $data = fcg_load_portal_data();
    $attachmentPath = '';
    $attachmentFile = '';
    $attachmentMime = '';
    if (isset($_FILES['attachment']) && (int) fcg_value($_FILES['attachment'], 'error', UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $validated = fcg_validate_upload($_FILES['attachment'], ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'], 8 * 1024 * 1024);
        $uploadDir = fcg_data_dir() . '/ticket-attachments';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $attachmentFile = date('YmdHis') . '-' . bin2hex(fcg_random_bytes(4)) . '-' . $validated['safe_name'];
        $attachmentPath = $uploadDir . '/' . $attachmentFile;
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachmentPath)) {
            fcg_json(['error' => 'Could not save the support attachment'], 500);
        }
        $attachmentMime = fcg_value($_FILES['attachment'], 'type', 'application/octet-stream') ?: 'application/octet-stream';
    }
    $ticketNo = fcg_business_number($data['tickets'], $businessUnit === 'fcg_cloud' ? 'FCC-TK' : 'FCG/TK', 'ticket_no');
    $ticket = [
        'id' => fcg_next_record_id($data['tickets']),
        'business_unit' => $businessUnit,
        'ticket_no' => $ticketNo,
        'client_id' => (int) $user['id'],
        'request_type' => $requestType,
        'category' => $requestType,
        'priority' => $priority,
        'message' => $message,
        'attachment_path' => $attachmentPath,
        'attachment_file' => $attachmentFile,
        'attachment_mime' => $attachmentMime,
        'popia_consent' => true,
        'consent_at' => date('c'),
        'status' => 'Open',
        'assigned_technician' => '',
        'admin_response' => '',
        'client_response' => '',
        'completion_notes' => '',
        'resolution_date' => '',
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
    $ticket['notification_sent'] = fcg_notify_ticket($ticket, $user);
    $ticket['notified_at'] = date('c');
    array_unshift($data['tickets'], $ticket);
    fcg_log_activity($data, 'ticket', 'Support ticket ' . $ticket['ticket_no'] . ' created', $ticket['client_id'], $businessUnit);
    fcg_notify_record($data, $ticket, 'ticket', 'Support ticket created', $ticket['ticket_no'] . ' has been received with status Open.');
    fcg_save_portal_data($data);
    fcg_json(['ok' => true, 'ticket' => fcg_public_ticket($ticket), 'dashboard' => fcg_dashboard()], 201);
}

function fcg_login_shell()
{
    $oauth = fcg_oauth_public_config();
    $accessMessage = 'Hi Future Creative Group, I need assistance with Client Portal access.';
    $supportUrl = 'https://wa.me/27115680279?text=' . rawurlencode($accessMessage);
    $googleButton = $oauth['google'] ? '<a class="oauth-btn google" href="auth/google/start"><svg class="oauth-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.6h5.4c-.2 1.2-.9 2.2-1.9 2.9v2.4h3.1c1.8-1.7 3-4.2 3-7Z"></path><path fill="#34A853" d="M12 22c2.7 0 5-0.9 6.6-2.5l-3.1-2.4c-.9.6-2 1-3.5 1-2.7 0-4.9-1.8-5.7-4.2H3.1v2.5C4.7 19.7 8.1 22 12 22Z"></path><path fill="#FBBC05" d="M6.3 13.9c-.2-.6-.3-1.2-.3-1.9s.1-1.3.3-1.9V7.6H3.1C2.4 8.9 2 10.4 2 12s.4 3.1 1.1 4.4l3.2-2.5Z"></path><path fill="#EA4335" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.8-2.8C16.9 3 14.7 2 12 2 8.1 2 4.7 4.3 3.1 7.6l3.2 2.5C7.1 7.7 9.3 5.9 12 5.9Z"></path></svg><span>Continue with Google</span></a>' : '';
    $appleButton = $oauth['apple'] ? '<a class="oauth-btn apple" href="auth/apple/start"><svg class="oauth-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 12.4c0-2 1.6-3 1.7-3.1-1-.1-2-.6-2.6-1.4-1.1-1.3-2.9-1.2-3.6-.8-.7.3-1.3.8-2.1.8-.8 0-1.6-.5-2.5-.5-1.3 0-2.5.8-3.2 2-1.4 2.5-.4 6.2 1 8.2.7 1 1.5 2.1 2.6 2 1 0 1.4-.7 2.6-.7 1.2 0 1.5.7 2.6.7 1.1 0 1.8-1 2.5-2 .8-1.1 1.1-2.2 1.1-2.3 0 0-2.1-.8-2.1-2.9ZM14.9 5.8c.6-.7 1-1.7.9-2.8-.9.1-1.9.6-2.5 1.3-.6.7-1 1.6-.9 2.6.9.1 1.9-.4 2.5-1.1Z"></path></svg><span>Continue with Apple</span></a>' : '';
    $oauthSection = ($googleButton || $appleButton)
        ? '<div class="auth-divider"><span></span><em>or continue with</em><span></span></div><div class="oauth-actions">' . $googleButton . $appleButton . '</div>'
        : '';
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Portal | Future Creative Group</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="Secure Future Creative Group client portal access.">
  <meta name="color-scheme" content="dark light">
  <link rel="icon" type="image/png" sizes="40x40" href="static/favicon-36x36.png">
  <style>
    :root{--navy:#03111f;--navy-2:#071a2d;--navy-3:#0d263a;--cyan:#28c3f4;--blue:#168fca;--aqua:#79e6ff;--white:#ffffff;--soft:#dceff8;--muted:#9bb7c8;--line:rgba(139,203,235,.26);--glass:rgba(9,27,43,.72);--glass-strong:rgba(15,38,57,.86);--danger:#ffb4b4;--radius:28px;--radius-sm:16px;--shadow:0 34px 110px rgba(0,0,0,.42)}
    *{box-sizing:border-box;margin:0;padding:0}html{min-height:100%;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--white);background:var(--navy);scroll-behavior:smooth}body{min-height:100vh;overflow-x:hidden;background:radial-gradient(circle at 72% 36%,rgba(40,195,244,.2),transparent 28%),radial-gradient(circle at 18% 78%,rgba(22,143,202,.18),transparent 30%),linear-gradient(135deg,#03101d 0%,#07192b 48%,#041321 100%)}body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(151,205,233,.052) 1px,transparent 1px),linear-gradient(90deg,rgba(151,205,233,.052) 1px,transparent 1px);background-size:72px 72px;mask-image:linear-gradient(180deg,#000,transparent 86%);z-index:0}body:after{content:"";position:fixed;inset:-20%;pointer-events:none;background:radial-gradient(circle at 70% 40%,rgba(121,230,255,.14),transparent 18%),radial-gradient(circle at 20% 70%,rgba(22,143,202,.12),transparent 22%);filter:blur(6px);animation:glowShift 16s ease-in-out infinite alternate;z-index:0}a{color:inherit}svg{max-width:100%;display:block}.page{position:relative;z-index:1;min-height:100vh;display:grid;grid-template-rows:1fr auto;padding:42px 28px 22px}.page:before{content:"";position:absolute;left:5vw;bottom:10vh;width:min(420px,48vw);height:min(420px,48vw);border:1px solid rgba(121,230,255,.09);border-radius:50%;background:linear-gradient(135deg,rgba(40,195,244,.08),transparent 48%);mask-image:linear-gradient(140deg,#000,transparent 74%);pointer-events:none}.network{position:fixed;inset:auto auto 7vh 4vw;width:min(520px,46vw);height:auto;color:rgba(121,230,255,.18);pointer-events:none;z-index:0}.shell{width:min(100%,1240px);margin:auto;display:grid;grid-template-columns:minmax(0,1fr) minmax(420px,520px);gap:clamp(34px,6vw,84px);align-items:center}.brand{display:grid;gap:26px;min-width:0}.brand-top{display:flex;align-items:center;gap:16px}.brand-logo{width:62px;height:62px;display:grid;place-items:center;border:1px solid rgba(151,205,233,.26);border-radius:18px;background:rgba(255,255,255,.065);box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}.brand-logo img{width:48px;height:48px;object-fit:contain}.brand-title strong{display:block;font-size:1.02rem;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.brand-title span{display:block;margin-top:.22rem;color:#abc4d4;font-size:.76rem;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.badge{width:fit-content;display:inline-flex;align-items:center;gap:.5rem;color:var(--aqua);background:rgba(40,195,244,.1);border:1px solid rgba(40,195,244,.3);border-radius:999px;padding:.56rem .86rem;font-size:.72rem;font-weight:950;letter-spacing:.15em;text-transform:uppercase;box-shadow:0 14px 40px rgba(40,195,244,.09)}.badge svg,.feature-icon svg,.security-card svg{width:18px;height:18px}.brand h1{max-width:650px;font-size:clamp(3rem,5.4vw,5.45rem);line-height:.96;letter-spacing:0}.brand h1 span{color:transparent;background:linear-gradient(95deg,#ffffff 0%,#7ee9ff 50%,#28c3f4 100%);-webkit-background-clip:text;background-clip:text}.brand-copy{max-width:600px;color:#c6d9e6;font-size:1.05rem;line-height:1.85}.features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;max-width:690px}.feature{min-width:0;display:grid;gap:.7rem;padding:15px;border:1px solid rgba(151,205,233,.15);border-radius:18px;background:linear-gradient(145deg,rgba(255,255,255,.085),rgba(255,255,255,.025));backdrop-filter:blur(14px);transition:transform .22s ease,border-color .22s ease,background .22s ease}.feature:hover{transform:translateY(-3px);border-color:rgba(40,195,244,.38);background:linear-gradient(145deg,rgba(40,195,244,.12),rgba(255,255,255,.035))}.feature-icon{width:38px;height:38px;display:grid;place-items:center;color:var(--aqua);border:1px solid rgba(40,195,244,.26);border-radius:14px;background:rgba(40,195,244,.1)}.feature strong{font-size:.92rem}.feature span{color:#a8c1d2;font-size:.78rem;line-height:1.55}.security-card{max-width:610px;display:flex;align-items:center;gap:14px;padding:16px;border:1px solid rgba(121,230,255,.24);border-radius:20px;background:linear-gradient(135deg,rgba(40,195,244,.1),rgba(255,255,255,.035));box-shadow:0 24px 70px rgba(0,0,0,.2)}.security-card .shield{width:44px;height:44px;display:grid;place-items:center;flex:0 0 44px;color:#061321;background:linear-gradient(135deg,#ffffff,#71e4ff);border-radius:16px}.security-card p{color:#d4e8f3;line-height:1.55;font-size:.92rem}.security-card strong{display:block;color:#fff;font-size:.95rem}.card-wrap{position:relative}.card-wrap:before{content:"";position:absolute;inset:7% -7% auto auto;width:76%;height:48%;border-radius:36px;background:rgba(40,195,244,.18);filter:blur(70px);z-index:-1}.card{animation:cardIn .7s ease both;position:relative;overflow:hidden;background:linear-gradient(155deg,rgba(255,255,255,.13),rgba(255,255,255,.045)),var(--glass);border:1px solid rgba(151,205,233,.3);border-radius:var(--radius);box-shadow:var(--shadow);backdrop-filter:blur(22px);padding:32px}.card:before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;background:linear-gradient(145deg,rgba(121,230,255,.5),rgba(255,255,255,.08),rgba(22,143,202,.36));mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);mask-composite:exclude;pointer-events:none}.card-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:start;margin-bottom:24px}.portal-label{display:inline-flex;margin-bottom:10px;color:var(--aqua);font-size:.72rem;font-weight:950;letter-spacing:.17em;text-transform:uppercase}.card h2{font-size:clamp(2rem,3vw,2.75rem);line-height:1.05;letter-spacing:0}.card p{color:#b9d0de;line-height:1.65}.secure{display:grid;gap:2px;min-width:116px;padding:10px 13px;color:#dff8ff;background:rgba(40,195,244,.11);border:1px solid rgba(40,195,244,.3);border-radius:16px;text-align:left}.secure strong{font-size:.74rem;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.secure span{color:#9fc2d3;font-size:.66rem;font-weight:850}form{display:grid;gap:15px}.field{display:grid;gap:8px}label,.option-row,.auth-divider{font-size:.76rem;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:#e6f6ff}.input-wrap{position:relative}.input-icon{position:absolute;left:15px;top:50%;width:19px;height:19px;color:#8fbbcf;transform:translateY(-50%);pointer-events:none}input{width:100%;min-height:56px;border:1px solid rgba(151,205,233,.32);border-radius:15px;background:rgba(255,255,255,.085);color:#fff;padding:0 48px 0 46px;outline:none;font-size:.95rem;transition:border-color .2s ease,box-shadow .2s ease,background .2s ease}input::placeholder{color:rgba(219,235,245,.48)}input:focus{border-color:rgba(121,230,255,.76);box-shadow:0 0 0 4px rgba(40,195,244,.14),0 0 34px rgba(40,195,244,.12);background:rgba(255,255,255,.12)}.access-toggle{position:absolute;right:10px;top:50%;width:38px;height:38px;min-height:38px;padding:0;display:grid;place-items:center;color:#b7d5e4;background:transparent;border:0;border-radius:12px;transform:translateY(-50%)}.access-toggle:hover{background:rgba(255,255,255,.08);transform:translateY(-50%)}.access-toggle svg{width:19px;height:19px}.option-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:-2px;letter-spacing:0;text-transform:none;font-size:.82rem;font-weight:850;color:#b8d1de}.remember{display:inline-flex;align-items:center;gap:8px}.remember input{appearance:none;width:17px;height:17px;min-height:17px;padding:0;border-radius:5px;background:rgba(255,255,255,.08)}.remember input:checked{background:linear-gradient(135deg,var(--blue),var(--cyan));border-color:var(--cyan);box-shadow:inset 0 0 0 3px #092235}.option-row a,.card-links a,.portal-footer a{color:#cfeeff;text-decoration:none;border-bottom:1px solid transparent}.option-row a:hover,.card-links a:hover,.portal-footer a:hover{color:var(--aqua);border-color:currentColor}.primary-btn,.mini,.oauth-btn{min-height:54px;border:0;border-radius:15px;font-weight:950;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:0 16px;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease,background .22s ease}.primary-btn{width:100%;margin-top:2px;color:#03111f;background:linear-gradient(135deg,#e8fbff,#77e5ff 48%,#28c3f4);box-shadow:0 18px 46px rgba(40,195,244,.22)}.primary-btn:hover{transform:translateY(-2px);box-shadow:0 22px 58px rgba(40,195,244,.34)}.primary-btn:disabled{cursor:wait;opacity:.72;transform:none;box-shadow:none}.primary-btn svg{width:19px;height:19px}.btn-arrow{margin-left:auto}.primary-btn.is-loading .btn-text{opacity:.78}.primary-btn.is-loading .btn-lock{animation:pulseLock 1s ease-in-out infinite}.auth-divider{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;margin:20px 0 14px;color:#9fb9c9;font-size:.68rem;letter-spacing:.14em}.auth-divider span{height:1px;background:rgba(151,205,233,.22)}.auth-divider em{font-style:normal;white-space:nowrap}.oauth-actions{display:grid;gap:10px}.oauth-btn{width:100%;border:1px solid rgba(151,205,233,.25)}.oauth-btn:hover{transform:translateY(-2px);box-shadow:0 16px 38px rgba(0,0,0,.18)}.oauth-btn.google{color:#1d2938;background:#fff}.oauth-btn.apple{color:#fff;background:#05080c;border-color:rgba(255,255,255,.18)}.oauth-icon{width:20px;height:20px;flex:0 0 20px}.assist{margin-top:18px;display:grid;gap:12px;padding:16px;border:1px solid rgba(40,195,244,.24);background:rgba(40,195,244,.08);border-radius:18px}.assist strong{font-size:1rem}.assist p{font-size:.88rem;line-height:1.6}.assist-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.mini{min-height:42px;color:#edfaff;background:rgba(255,255,255,.075);border:1px solid rgba(151,205,233,.24);border-radius:13px;font-size:.83rem}.mini:hover{border-color:rgba(40,195,244,.44);background:rgba(40,195,244,.12);transform:translateY(-1px)}.error{display:none;margin-top:14px;padding:13px 14px;border-radius:15px;background:rgba(244,113,113,.13);border:1px solid rgba(244,113,113,.32);color:#ffdede;font-size:.88rem;line-height:1.5;animation:errorIn .2s ease both}.hidden{display:none!important}.card-links{margin-top:16px;display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;color:#b8d1de;font-size:.82rem;font-weight:850}.portal-footer{position:relative;z-index:1;width:min(100%,1240px);margin:34px auto 0;padding-top:18px;border-top:1px solid rgba(151,205,233,.14);display:flex;align-items:center;justify-content:space-between;gap:18px;color:#9cb9ca;font-size:.78rem}.footer-contact,.footer-links{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.footer-contact strong{color:#dceff8}.back-to-top{position:fixed;right:18px;bottom:18px;width:44px;height:44px;min-width:44px;min-height:44px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--cyan));color:#03111f;box-shadow:0 14px 34px rgba(0,0,0,.35);opacity:0;pointer-events:none;transform:translateY(10px);transition:opacity .22s ease,transform .22s ease;z-index:5}.back-to-top.is-visible{opacity:1;pointer-events:auto;transform:translateY(0)}.back-to-top svg{width:18px;height:18px}@keyframes cardIn{from{opacity:0;transform:translateY(18px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes glowShift{from{transform:translate3d(-1%,1%,0) scale(1)}to{transform:translate3d(1.5%,-1%,0) scale(1.035)}}@keyframes errorIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}@keyframes pulseLock{50%{transform:scale(.9);opacity:.72}}
    @media(max-width:1080px){.page{padding:28px 20px 18px}.shell{grid-template-columns:1fr;max-width:720px}.brand{gap:20px}.brand h1{font-size:clamp(2.65rem,8vw,4.6rem)}.features{grid-template-columns:1fr}.security-card{max-width:100%}.card-wrap:before{inset:auto 4% 18% auto}.portal-footer{display:grid;justify-items:center;text-align:center}.footer-contact,.footer-links{justify-content:center}.network{width:58vw;opacity:.65}}
    @media(max-width:620px){.page{padding:18px 14px 16px}.shell{gap:22px}.brand{gap:16px}.brand-top{gap:12px}.brand-logo{width:52px;height:52px;border-radius:15px}.brand-logo img{width:40px;height:40px}.brand-title strong{font-size:.88rem}.brand-title span{font-size:.66rem}.badge{font-size:.64rem;padding:.48rem .68rem}.brand h1{font-size:clamp(2.25rem,12vw,3.3rem);line-height:1.02}.brand-copy{font-size:.94rem;line-height:1.65}.features,.security-card{display:none}.card{padding:22px;border-radius:22px}.card-head{grid-template-columns:1fr}.secure{width:fit-content}.card h2{font-size:2rem}input{min-height:54px}.option-row{align-items:flex-start;flex-direction:column}.assist-actions{grid-template-columns:1fr}.card-links{justify-content:flex-start}.portal-footer{margin-top:26px;font-size:.74rem}.network{display:none}}
    html[data-theme="light"]{--navy:#edf6fb;--white:#0d263a;--soft:#143249;--muted:#617a8c;--line:rgba(22,143,202,.22);--glass:rgba(255,255,255,.78);--glass-strong:rgba(255,255,255,.9);--shadow:0 28px 82px rgba(24,71,103,.14)}html[data-theme="light"] body{background:radial-gradient(circle at 72% 36%,rgba(40,195,244,.18),transparent 28%),linear-gradient(135deg,#f7fbfe 0%,#eaf4fa 58%,#dfeef6 100%)}html[data-theme="light"] body:before{background-image:linear-gradient(rgba(17,105,155,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(17,105,155,.06) 1px,transparent 1px)}html[data-theme="light"] .brand-copy,html[data-theme="light"] .card p,html[data-theme="light"] .feature span,html[data-theme="light"] .security-card p,html[data-theme="light"] .option-row,html[data-theme="light"] .card-links,html[data-theme="light"] .portal-footer{color:#496a7f}html[data-theme="light"] .feature,html[data-theme="light"] .card,html[data-theme="light"] .security-card{background:linear-gradient(145deg,rgba(255,255,255,.86),rgba(255,255,255,.56));border-color:rgba(22,143,202,.18)}html[data-theme="light"] .card{background:linear-gradient(155deg,rgba(255,255,255,.92),rgba(255,255,255,.7)),var(--glass)}html[data-theme="light"] input{color:#123047;background:rgba(255,255,255,.75);border-color:rgba(22,143,202,.24)}html[data-theme="light"] input::placeholder{color:#7a8f9d}html[data-theme="light"] .input-icon{color:#668598}html[data-theme="light"] .oauth-btn.apple{background:#101820;color:#fff}html[data-theme="light"] .oauth-btn.google{background:#fff;color:#1d2938}html[data-theme="light"] .mini{color:#11314a;background:rgba(255,255,255,.68)}html[data-theme="light"] .secure{color:#0d344e;background:rgba(40,195,244,.12)}html[data-theme="light"] .secure span{color:#557386}html[data-theme="light"] .footer-contact strong{color:#123047}
    @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
  </style>
</head>
<body>
  <div id="top"></div>
  <svg class="network" viewBox="0 0 520 420" fill="none" aria-hidden="true">
    <path d="M34 331C91 230 166 253 229 166C292 78 380 96 486 36" stroke="currentColor" stroke-width="1"></path>
    <path d="M64 371C142 311 170 279 228 278C322 277 343 183 470 142" stroke="currentColor" stroke-width="1"></path>
    <circle cx="34" cy="331" r="4" fill="currentColor"></circle><circle cx="229" cy="166" r="4" fill="currentColor"></circle><circle cx="486" cy="36" r="4" fill="currentColor"></circle><circle cx="228" cy="278" r="4" fill="currentColor"></circle><circle cx="470" cy="142" r="4" fill="currentColor"></circle>
  </svg>
  <div class="page">
  <main class="shell">
    <section class="brand" aria-label="Future Creative Group secure portal">
      <div class="brand-top"><span class="brand-logo"><img src="static/logo.png" alt="Future Creative Group"></span><div class="brand-title"><strong>Future Creative Group</strong><span>IT • Security • Digital Solutions</span></div></div>
      <span class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-5"></path></svg>Secure Client Access</span>
      <h1>Your secure client <span>workspace.</span></h1>
      <p class="brand-copy">Access your quotations, invoices, project updates, support tickets, payment records and business documents in one protected portal.</p>
      <div class="features" aria-label="Portal highlights">
        <article class="feature"><span class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5"></path><path d="M4 19h16"></path><path d="m7 15 4-4 3 3 5-7"></path></svg></span><strong>Project Updates</strong><span>Real-time progress and milestone tracking</span></article>
        <article class="feature"><span class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v5h5"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg></span><strong>Quotes &amp; Invoices</strong><span>View, download and manage all your documents</span></article>
        <article class="feature"><span class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v2"></path></svg></span><strong>Secure Documents</strong><span>Encrypted, private and accessible anytime</span></article>
      </div>
      <div class="security-card"><span class="shield"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"></path><path d="m9 12 2 2 4-5"></path></svg></span><p><strong>Secure access for approved Future Creative Group clients only.</strong>Protected. Private. Professional.</p></div>
    </section>
    <div class="card-wrap">
    <section class="card" aria-labelledby="login-title">
      <div class="card-head"><div><span class="portal-label">Client Portal</span><h2 id="login-title">Welcome Back</h2><p>Sign in to continue to your secure client workspace.</p></div><span class="secure"><strong>Secure</strong><span>Encrypted Access</span></span></div>
      <form id="login-form">
        <div class="field"><label for="email">Email Address</label><span class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"></path><rect x="2" y="4" width="20" height="16" rx="2"></rect></svg><input id="email" type="email" autocomplete="email" placeholder="client@example.com" required></span></div>
        <div class="field"><label for="access-code">Access Code</label><span class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg><input id="access-code" type="password" autocomplete="current-password" placeholder="Enter your access code" required><button class="access-toggle" id="access-toggle" type="button" aria-label="Show access code" title="Show access code"><svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span></div>
        <div class="field hidden" id="two-factor-field"><label for="two-factor-code">Administrator verification code</label><span class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg><input id="two-factor-code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></span></div>
        <div class="option-row"><label class="remember" for="remember-email"><input id="remember-email" type="checkbox">Remember me</label><a href="{{FCG_SUPPORT_URL}}" target="_blank" rel="noopener">Forgot access code?</a></div>
        <button class="primary-btn" id="sign-in-button" type="submit"><svg class="btn-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg><span class="btn-text">Sign In</span><svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg></button>
      </form>
      {{FCG_OAUTH_SECTION}}
      <div class="assist"><strong>Need portal access?</strong><p>Portal access is available to active Future Creative Group clients. Request assistance if you need login details, account activation or access code support.</p><div class="assist-actions"><a class="mini" href="{{FCG_SUPPORT_URL}}" target="_blank" rel="noopener">Request Portal Access</a><a class="mini" href="{{FCG_SUPPORT_URL}}" target="_blank" rel="noopener">WhatsApp Support</a></div></div>
      <div class="error" id="login-error"></div>
      <div class="card-links"><a href="{{FCG_SUPPORT_URL}}" target="_blank" rel="noopener">Forgot access code?</a><a href="/">Back to Website</a></div>
    </section>
    </div>
  </main>
  <footer class="portal-footer">
    <div class="footer-contact"><strong>Future Creative Group</strong><a href="tel:+27115680279">011 568 0279</a><a href="mailto:info@futurecreativegroup.co.za">info@futurecreativegroup.co.za</a></div>
    <nav class="footer-links" aria-label="Legal links"><a href="/privacy/">Privacy &amp; POPIA</a><a href="/paia/">PAIA Manual</a><a href="/website-terms/">Website Terms</a><a href="/service-terms/">Service Terms</a><a href="/warranty-returns/">Warranty &amp; Returns</a><a href="/cookie-policy/">Cookie Policy</a></nav>
  </footer>
  </div>
  <a class="back-to-top" href="#top" aria-label="Back to top" title="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg>
  </a>
  <script>
    (function(){
      const params = new URLSearchParams(window.location.search);
      const isCloud = window.location.hostname.includes('fcgcloud.co.za') || ['fcgcloud','fcg_cloud','cloud'].includes((params.get('brand') || '').toLowerCase());
      if (!isCloud) return;
      document.title = 'FCG Cloud Client Portal';
      const icon = document.querySelector('link[rel="icon"]');
      if (icon) icon.href = 'static/fcg-cloud-favicon.png';
      const logo = document.querySelector('.brand-logo img');
      if (logo) { logo.src = 'static/fcg-cloud-logo.png'; logo.alt = 'FCG Cloud'; }
      const name = document.querySelector('.brand-title strong');
      const type = document.querySelector('.brand-title span');
      const badge = document.querySelector('.badge');
      const heading = document.querySelector('h1');
      const intro = document.querySelector('.brand-copy');
      const cardIntro = document.querySelector('.card-head p');
      if (name) name.textContent = 'FCG Cloud';
      if (type) type.textContent = 'Hosting • Domains • Business Email';
      if (badge) badge.textContent = 'Secure cloud access';
      if (heading) heading.innerHTML = 'Hosting, billing and support in one secure <span>workspace.</span>';
      if (intro) intro.textContent = 'Manage your FCG Cloud hosting, business email, domains, invoices, renewals and support requests.';
      if (cardIntro) cardIntro.textContent = 'Enter the secure access details issued for your FCG Cloud account.';
      const portalLabel = document.querySelector('.portal-label');
      if (portalLabel) portalLabel.textContent = 'Cloud Portal';
      document.querySelectorAll('.department-links a[href^="mailto:"]').forEach((link) => {
        if (link.textContent.includes('Sales')) { link.href = 'mailto:sales@fcgcloud.co.za'; link.textContent = 'Sales: sales@fcgcloud.co.za'; }
        if (link.textContent.includes('Support')) { link.href = 'mailto:support@fcgcloud.co.za'; link.textContent = 'Support: support@fcgcloud.co.za'; }
        if (link.textContent.includes('General')) { link.href = 'mailto:info@fcgcloud.co.za'; link.textContent = 'General: info@fcgcloud.co.za'; }
      });
    })();
    const form = document.getElementById('login-form');
    const errorBox = document.getElementById('login-error');
    const twoFactorField = document.getElementById('two-factor-field');
    const twoFactorCode = document.getElementById('two-factor-code');
    const emailField = document.getElementById('email');
    const accessCode = document.getElementById('access-code');
    const accessToggle = document.getElementById('access-toggle');
    const rememberEmail = document.getElementById('remember-email');
    const signInButton = document.getElementById('sign-in-button');
    try {
      const savedEmail = localStorage.getItem('fcg_portal_email');
      if (savedEmail && emailField && rememberEmail) {
        emailField.value = savedEmail;
        rememberEmail.checked = true;
      }
    } catch (error) {}
    if (accessToggle && accessCode) {
      accessToggle.addEventListener('click', () => {
        const showing = accessCode.type === 'text';
        accessCode.type = showing ? 'password' : 'text';
        accessToggle.setAttribute('aria-label', showing ? 'Show access code' : 'Hide access code');
        accessToggle.title = showing ? 'Show access code' : 'Hide access code';
      });
    }
    const authError = new URLSearchParams(window.location.search).get('auth_error');
    if (authError) {
      errorBox.textContent = authError;
      errorBox.style.display = 'block';
      history.replaceState(null, document.title, window.location.pathname);
    }
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      errorBox.style.display = 'none';
      if (signInButton) {
        signInButton.disabled = true;
        signInButton.classList.add('is-loading');
      }
      const body = {
        email: emailField.value.trim().toLowerCase(),
        password: accessCode.value,
        two_factor_code: twoFactorCode.value.trim()
      };
      try {
        if (rememberEmail && rememberEmail.checked) localStorage.setItem('fcg_portal_email', body.email);
        if (rememberEmail && !rememberEmail.checked) localStorage.removeItem('fcg_portal_email');
      } catch (error) {}
      try {
        const response = await fetch('api/login', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Accept': 'application/json','Content-Type': 'application/json'},
          body: JSON.stringify(body)
        });
        const data = await response.json().catch(() => ({}));
        if (response.status === 202 && data.requires_2fa) {
          twoFactorField.classList.remove('hidden');
          twoFactorCode.required = true;
          twoFactorCode.focus();
          errorBox.textContent = data.message || 'Enter the administrator verification code.';
          errorBox.style.display = 'block';
          if (signInButton) {
            signInButton.disabled = false;
            signInButton.classList.remove('is-loading');
          }
          return;
        }
        if (!response.ok) throw new Error(data.error || 'Invalid portal login details.');
        window.location.href = new URL('.', window.location.href).toString();
      } catch (error) {
        errorBox.textContent = error.message || 'Invalid portal login details. Please try again.';
        errorBox.style.display = 'block';
        if (signInButton) {
          signInButton.disabled = false;
          signInButton.classList.remove('is-loading');
        }
      }
    });
    (function(){
      const button = document.querySelector('.back-to-top');
      if (!button) return;
      const sync = () => button.classList.toggle('is-visible', window.scrollY > 600);
      window.addEventListener('scroll', sync, {passive:true});
      button.addEventListener('click', (event) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        event.preventDefault();
        window.scrollTo({top:0, behavior:'smooth'});
      });
      sync();
    })();
  </script>
</body>
</html>
HTML;
    return str_replace(
        ['{{FCG_OAUTH_SECTION}}', '{{FCG_SUPPORT_URL}}'],
        [$oauthSection, htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8')],
        $html
    );
}

if ($requestPath !== '/' && $requestPath !== '/index.php' && $requestPath !== '/index.html') {
    fcg_json(['error' => 'Not found'], 404);
}

$template = fcg_first_file('templates/portal.html');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$sessionUser = fcg_user();
if (!$sessionUser) {
    echo fcg_login_shell();
    exit;
}
if ($template) {
    $html = file_get_contents($template);
    if (!$sessionUser || fcg_value($sessionUser, 'role', '') !== 'admin') {
        $html = preg_replace('/<!-- FCG_ADMIN_NAV_START -->.*?<!-- FCG_ADMIN_NAV_END -->/s', '', $html);
        $html = preg_replace('/<!-- FCG_ADMIN_VIEW_START -->.*?<!-- FCG_ADMIN_VIEW_END -->/s', '', $html);
        $html = preg_replace('/\/\* FCG_ADMIN_CONST_START \*\/.*?\/\* FCG_ADMIN_CONST_END \*\//s', "const adminTab = null;\n    const adminClientForm = null;", $html);
        $html = preg_replace('/\/\* FCG_ADMIN_VIEW_COPY_START \*\/.*?\/\* FCG_ADMIN_VIEW_COPY_END \*\//s', '', $html);
        $html = preg_replace('/\/\* FCG_ADMIN_INIT_START \*\/.*?\/\* FCG_ADMIN_INIT_END \*\//s', '', $html);
        $html = preg_replace('/\/\* FCG_ADMIN_EARLY_HANDLERS_START \*\/.*?\/\* FCG_ADMIN_EARLY_HANDLERS_END \*\//s', '', $html);
        $html = preg_replace('/\/\* FCG_ADMIN_HANDLERS_START \*\/.*?\/\* FCG_ADMIN_HANDLERS_END \*\//s', '', $html);
        $html = preg_replace('/\/\* FCG_ADMIN_FUNCTIONS_START \*\/.*?\/\* FCG_ADMIN_FUNCTIONS_END \*\//s', 'async function refreshAdmin() {}', $html);
    }
    echo $html;
    exit;
}

http_response_code(500);
echo '<!doctype html><title>Portal unavailable</title><h1>Portal template missing</h1>';
