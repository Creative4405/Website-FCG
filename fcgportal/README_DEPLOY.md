# Future Creative Group Client Portal Backend

This folder is the production backend for the Client Portal.

It uses:

- Python WSGI for CloudLinux Passenger
- SQLite for users, sessions, projects, quotes, documents and support tickets
- PBKDF2 password hashing
- HttpOnly session cookies
- Optional SMTP email notifications for support tickets

## Upload Location

Upload this full `fcgportal` folder to:

```text
/home/futurec2/fcgportal
```

The public `/portal/.htaccess` already points to this app root:

```text
PassengerAppRoot "/home/futurec2/fcgportal"
PassengerBaseURI "/portal"
```

## Production Environment Variables

In cPanel Python App settings, add the values from `config.example.txt`.

Important: change these before public launch:

```text
FCG_ADMIN_PASSWORD
FCG_CLIENT_PASSWORD
FCG_SMTP_PASSWORD
```

If SMTP is not configured, support tickets are still saved to the SQLite database, but email alerts will be skipped.

## Optional Google and Apple Login

The portal keeps the existing email/access-code login and can add OAuth buttons when provider credentials are present.

Google callback URL:

```text
https://futurecreativegroup.co.za/portal/auth/google/callback
```

Apple callback URL:

```text
https://futurecreativegroup.co.za/portal/auth/apple/callback
```

Add these environment variables only on the server. Do not place secrets in frontend code:

```text
GOOGLE_CLIENT_ID
GOOGLE_CLIENT_SECRET
GOOGLE_REDIRECT_URI
APPLE_CLIENT_ID
APPLE_TEAM_ID
APPLE_KEY_ID
APPLE_PRIVATE_KEY
APPLE_REDIRECT_URI
```

Google and Apple users are never auto-created. The verified OAuth email or existing provider ID must match an approved active account in `fcgportal/data/portal-users.json`.

## Portal Accounts

Live portal accounts should be managed in:

```text
fcgportal/data/portal-users.json
```

The file stores account emails, roles and `password_hash` values only. Generate new hashes with PHP:

```bash
php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;' 'NEW-ACCESS-CODE'
```

Do not store plain access codes in this project folder.

## Local Test

From this folder:

```bash
python3 app.py
```

Then open:

```text
http://127.0.0.1:8090/portal/
```

## Data

The live database is created automatically here:

```text
fcgportal/data/portal.sqlite3
```

Do not delete this file after clients start using the portal.
