# OAuth Production Setup

Use this checklist when enabling Google or Apple login on the live Future Creative Group portal.

## Google

Create an OAuth client in Google Cloud Console:

- Application type: Web application
- Authorized JavaScript origins: `https://futurecreativegroup.co.za`
- Authorized redirect URI: `https://futurecreativegroup.co.za/portal/auth/google/callback`

Add these server environment variables:

```text
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://futurecreativegroup.co.za/portal/auth/google/callback
```

## Apple

Configure Sign in with Apple in the Apple Developer portal:

- Service ID / Client ID: your portal service identifier
- Return URL: `https://futurecreativegroup.co.za/portal/auth/apple/callback`
- Domain: `futurecreativegroup.co.za`

Add these server environment variables:

```text
APPLE_CLIENT_ID=
APPLE_TEAM_ID=
APPLE_KEY_ID=
APPLE_PRIVATE_KEY=
APPLE_REDIRECT_URI=https://futurecreativegroup.co.za/portal/auth/apple/callback
```

`APPLE_PRIVATE_KEY` may be stored with escaped line breaks (`\n`) if the hosting panel requires a single-line value.

## Access Rules

Google and Apple login never creates portal users. The provider email must be verified and must match an approved active account in:

```text
fcgportal/data/portal-users.json
```

On first successful provider login, the portal links `google_id` or `apple_id` to that existing account and stores `auth_provider` plus `last_login_at`.

## Live Smoke Test

After adding credentials:

1. Open `https://futurecreativegroup.co.za/portal/api/auth/config` and confirm the configured provider returns `true`.
2. Open `https://futurecreativegroup.co.za/portal/` and confirm the matching button appears.
3. Sign in with a Google or Apple account whose verified email already exists in `portal-users.json` and is active.
4. Confirm unknown provider emails are blocked.
5. Confirm inactive portal users are blocked.
6. Confirm logout works after provider login.

Do not commit OAuth secrets to this repository.
