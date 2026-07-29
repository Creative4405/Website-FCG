import base64
import hashlib
import hmac
import json
import mimetypes
import os
import secrets
import sqlite3
import smtplib
from datetime import datetime, timedelta, timezone
from email.message import EmailMessage
from http import cookies
from pathlib import Path
from urllib.parse import parse_qs
from wsgiref.simple_server import make_server


APP_DIR = Path(__file__).resolve().parent
DATA_DIR = APP_DIR / "data"
STATIC_DIR = APP_DIR / "static"
DB_PATH = Path(os.environ.get("FCG_PORTAL_DB", DATA_DIR / "portal.sqlite3"))
TEMPLATE_PATH = APP_DIR / "templates" / "portal.html"
SESSION_COOKIE = "fcg_session"
SESSION_HOURS = int(os.environ.get("FCG_SESSION_HOURS", "8"))
MAX_BODY_BYTES = 128 * 1024


def utcnow():
    return datetime.now(timezone.utc)


def iso_now():
    return utcnow().isoformat(timespec="seconds")


def json_response(start_response, payload, status="200 OK", headers=None):
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    response_headers = [
        ("Content-Type", "application/json; charset=utf-8"),
        ("Content-Length", str(len(body))),
        ("Cache-Control", "no-store"),
    ]
    if headers:
        response_headers.extend(headers)
    start_response(status, response_headers)
    return [body]


def html_response(start_response, html, status="200 OK"):
    body = html.encode("utf-8")
    start_response(
        status,
        [
            ("Content-Type", "text/html; charset=utf-8"),
            ("Content-Length", str(len(body))),
            ("Cache-Control", "no-store"),
        ],
    )
    return [body]


def not_found(start_response):
    return json_response(start_response, {"error": "Not found"}, "404 Not Found")


def static_response(environ, start_response, relative_path):
    safe_name = Path(relative_path).name
    allowed = {"logo.png", "favicon-16x16.png", "favicon-36x36.png"}
    if safe_name not in allowed:
        return not_found(start_response)
    asset = STATIC_DIR / safe_name
    if not asset.exists():
        return not_found(start_response)
    body = asset.read_bytes()
    content_type = mimetypes.guess_type(str(asset))[0] or "application/octet-stream"
    start_response(
        "200 OK",
        [
            ("Content-Type", content_type),
            ("Content-Length", str(len(body))),
            ("Cache-Control", "public, max-age=86400"),
        ],
    )
    if environ.get("REQUEST_METHOD", "GET").upper() == "HEAD":
        return [b""]
    return [body]


def read_body(environ):
    try:
        length = int(environ.get("CONTENT_LENGTH") or 0)
    except ValueError:
        length = 0
    if length > MAX_BODY_BYTES:
        raise ValueError("Request body is too large")
    return environ["wsgi.input"].read(length) if length else b""


def parse_request_data(environ):
    raw = read_body(environ)
    content_type = environ.get("CONTENT_TYPE", "")
    if "application/json" in content_type:
        return json.loads(raw.decode("utf-8") or "{}")
    parsed = parse_qs(raw.decode("utf-8"))
    return {key: values[-1] if values else "" for key, values in parsed.items()}


def normalize_path(environ):
    path = environ.get("PATH_INFO") or "/"
    if path == "/portal":
        return "/"
    if path.startswith("/portal/"):
        return path[len("/portal") :]
    return path


def db():
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def hash_password(password, salt=None, iterations=240000):
    if not salt:
        salt = secrets.token_bytes(16)
    elif isinstance(salt, str):
        salt = base64.urlsafe_b64decode(salt.encode("ascii"))
    digest = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt, iterations)
    return "pbkdf2_sha256${}${}${}".format(
        iterations,
        base64.urlsafe_b64encode(salt).decode("ascii"),
        base64.urlsafe_b64encode(digest).decode("ascii"),
    )


def verify_password(password, stored):
    try:
        algo, iterations, salt, expected = stored.split("$", 3)
    except ValueError:
        return False
    if algo != "pbkdf2_sha256":
        return False
    candidate = hash_password(password, salt, int(iterations)).rsplit("$", 1)[-1]
    return hmac.compare_digest(candidate, expected)


def hash_token(token):
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def init_db():
    with db() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS users (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              email TEXT NOT NULL UNIQUE,
              role TEXT NOT NULL CHECK(role IN ('client','admin')),
              password_hash TEXT NOT NULL,
              active INTEGER NOT NULL DEFAULT 1,
              created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS sessions (
              token_hash TEXT PRIMARY KEY,
              user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              expires_at TEXT NOT NULL,
              created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS projects (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              title TEXT NOT NULL,
              description TEXT NOT NULL,
              status TEXT NOT NULL,
              progress INTEGER NOT NULL DEFAULT 0,
              updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS quotes (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              quote_no TEXT NOT NULL,
              title TEXT NOT NULL,
              amount TEXT NOT NULL,
              status TEXT NOT NULL,
              updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS tickets (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              request_type TEXT NOT NULL,
              priority TEXT NOT NULL,
              message TEXT NOT NULL,
              status TEXT NOT NULL DEFAULT 'Open',
              created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS documents (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              title TEXT NOT NULL,
              description TEXT NOT NULL,
              url TEXT NOT NULL,
              status TEXT NOT NULL DEFAULT 'Available'
            );
            """
        )
        seed_data(conn)
        conn.execute("DELETE FROM sessions WHERE expires_at < ?", (iso_now(),))


def upsert_user(conn, name, email, role, password):
    existing = conn.execute("SELECT id FROM users WHERE email = ?", (email,)).fetchone()
    if existing:
        return existing["id"]
    cur = conn.execute(
        """
        INSERT INTO users (name, email, role, password_hash, active, created_at)
        VALUES (?, ?, ?, ?, 1, ?)
        """,
        (name, email, role, hash_password(password), iso_now()),
    )
    return cur.lastrowid


def upsert_user_hash(conn, name, email, role, password_hash):
    existing = conn.execute("SELECT id FROM users WHERE email = ?", (email,)).fetchone()
    if existing:
        conn.execute(
            """
            UPDATE users
            SET name = ?, role = ?, password_hash = ?, active = 1
            WHERE id = ?
            """,
            (name, role, password_hash, existing["id"]),
        )
        return existing["id"]

    cur = conn.execute(
        """
        INSERT INTO users (name, email, role, password_hash, active, created_at)
        VALUES (?, ?, ?, ?, 1, ?)
        """,
        (name, email, role, password_hash, iso_now()),
    )
    return cur.lastrowid


def seed_configured_users(conn):
    users_file = DATA_DIR / "portal-users.json"
    if not users_file.exists():
        return None

    try:
        payload = json.loads(users_file.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return None

    client_id = None
    for user in payload.get("users", []):
        if not user.get("active", True):
            continue
        email = (user.get("email") or "").strip().lower()
        password_hash = user.get("password_hash")
        role = user.get("role") if user.get("role") in {"client", "admin"} else "client"
        name = (user.get("name") or "Client Workspace").strip()
        if not email or not password_hash:
            continue
        user_id = upsert_user_hash(conn, name, email, role, password_hash)
        if role == "client" and client_id is None:
            client_id = user_id

    return client_id


def seed_data(conn):
    client_id = seed_configured_users(conn)
    if client_id is None:
        admin_email = os.environ.get("FCG_ADMIN_EMAIL", "admin@futurecreativegroup.co.za")
        admin_password = os.environ.get("FCG_ADMIN_PASSWORD", secrets.token_urlsafe(24))
        client_email = os.environ.get("FCG_CLIENT_EMAIL", "client@futurecreativegroup.co.za")
        client_password = os.environ.get("FCG_CLIENT_PASSWORD", secrets.token_urlsafe(24))

        upsert_user(conn, "FCG Admin", admin_email, "admin", admin_password)
        client_id = upsert_user(conn, "Client Workspace", client_email, "client", client_password)

    project_count = conn.execute("SELECT COUNT(*) AS c FROM projects").fetchone()["c"]
    if project_count == 0:
      conn.executemany(
          """
          INSERT INTO projects (client_id, title, description, status, progress, updated_at)
          VALUES (?, ?, ?, ?, ?, ?)
          """,
          [
              (
                  client_id,
                  "CCTV & Network Upgrade",
                  "Site assessment, structured network points, CCTV installation and handover documentation.",
                  "On schedule",
                  68,
                  iso_now(),
              ),
              (
                  client_id,
                  "Business Website Refresh",
                  "Content review, mobile polish, SEO and final publishing checks.",
                  "In progress",
                  42,
                  iso_now(),
              ),
          ],
      )

    quote_count = conn.execute("SELECT COUNT(*) AS c FROM quotes").fetchone()["c"]
    if quote_count == 0:
        conn.executemany(
            """
            INSERT INTO quotes (client_id, quote_no, title, amount, status, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            [
                (client_id, "FCG-Q-1042", "Website care plan and hosting support", "R2,850", "Sent", iso_now()),
                (client_id, "FCG-Q-1038", "CCTV maintenance and WiFi audit", "R7,420", "Review", iso_now()),
                (client_id, "FCG-Q-1027", "Access control and network installation", "R18,900", "Approved", iso_now()),
            ],
        )

    ticket_count = conn.execute("SELECT COUNT(*) AS c FROM tickets").fetchone()["c"]
    if ticket_count == 0:
        conn.executemany(
            """
            INSERT INTO tickets (client_id, request_type, priority, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            [
                (
                    client_id,
                    "Networking / WiFi",
                    "Normal",
                    "Client requested a review of signal strength near office extension.",
                    "Open",
                    iso_now(),
                ),
                (
                    client_id,
                    "CCTV / Security",
                    "Urgent",
                    "Assistance requested with exported footage and user access.",
                    "Pending",
                    iso_now(),
                ),
            ],
        )

    doc_count = conn.execute("SELECT COUNT(*) AS c FROM documents").fetchone()["c"]
    if doc_count == 0:
        conn.executemany(
            """
            INSERT INTO documents (title, description, url, status)
            VALUES (?, ?, ?, ?)
            """,
            [
                (
                    "Future Creative Group Company Profile",
                    "PDF company profile for clients and procurement teams.",
                    "/Future_Creative_Group_Company_Profile.pdf",
                    "PDF",
                ),
                (
                    "Project Onboarding Checklist",
                    "Items needed before security, networking or website project kickoff.",
                    "#",
                    "Planned",
                ),
                (
                    "Service Handover Notes",
                    "Post-installation support and handover documentation.",
                    "#",
                    "Planned",
                ),
            ],
        )


def get_cookie_token(environ):
    header = environ.get("HTTP_COOKIE", "")
    jar = cookies.SimpleCookie()
    jar.load(header)
    item = jar.get(SESSION_COOKIE)
    return item.value if item else None


def current_user(environ):
    token = get_cookie_token(environ)
    if not token:
        return None
    with db() as conn:
        row = conn.execute(
            """
            SELECT u.id, u.name, u.email, u.role
            FROM sessions s
            JOIN users u ON u.id = s.user_id
            WHERE s.token_hash = ? AND s.expires_at > ? AND u.active = 1
            """,
            (hash_token(token), iso_now()),
        ).fetchone()
    return dict(row) if row else None


def cookie_path(environ):
    return os.environ.get("FCG_COOKIE_PATH", "/portal")


def is_https(environ):
    return environ.get("HTTPS") == "on" or environ.get("HTTP_X_FORWARDED_PROTO") == "https"


def session_cookie(environ, token, max_age):
    secure = "; Secure" if is_https(environ) else ""
    return (
        f"{SESSION_COOKIE}={token}; Path={cookie_path(environ)}; Max-Age={max_age}; "
        f"HttpOnly; SameSite=Lax{secure}"
    )


def require_user(environ, start_response):
    user = current_user(environ)
    if not user:
        return None, json_response(start_response, {"error": "Authentication required"}, "401 Unauthorized")
    return user, None


def require_admin(environ, start_response):
    user, response = require_user(environ, start_response)
    if response:
        return None, response
    if user["role"] != "admin":
        return None, json_response(start_response, {"error": "Admin access required"}, "403 Forbidden")
    return user, None


def public_user(user):
    return {key: user[key] for key in ("id", "name", "email", "role")}


def login(environ, start_response):
    try:
        data = parse_request_data(environ)
    except Exception:
        return json_response(start_response, {"error": "Invalid request body"}, "400 Bad Request")

    email = (data.get("email") or "").strip().lower()
    password = data.get("password") or data.get("access_code") or ""
    if not email or not password:
        return json_response(start_response, {"error": "Email and access code are required"}, "400 Bad Request")

    with db() as conn:
        user = conn.execute(
            "SELECT id, name, email, role, password_hash FROM users WHERE lower(email) = ? AND active = 1",
            (email,),
        ).fetchone()
        if not user or not verify_password(password, user["password_hash"]):
            return json_response(start_response, {"error": "Invalid portal login details"}, "401 Unauthorized")

        token = secrets.token_urlsafe(32)
        expires = utcnow() + timedelta(hours=SESSION_HOURS)
        conn.execute(
            "INSERT INTO sessions (token_hash, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)",
            (hash_token(token), user["id"], expires.isoformat(timespec="seconds"), iso_now()),
        )

    headers = [("Set-Cookie", session_cookie(environ, token, SESSION_HOURS * 3600))]
    return json_response(
        start_response,
        {"ok": True, "user": public_user(user)},
        headers=headers,
    )


def logout(environ, start_response):
    token = get_cookie_token(environ)
    if token:
        with db() as conn:
            conn.execute("DELETE FROM sessions WHERE token_hash = ?", (hash_token(token),))
    headers = [("Set-Cookie", session_cookie(environ, "", 0))]
    return json_response(start_response, {"ok": True}, headers=headers)


def me(environ, start_response):
    user, response = require_user(environ, start_response)
    if response:
        return response
    return json_response(start_response, {"user": public_user(user), "dashboard": dashboard_payload(user)})


def admin_clients_payload():
    with db() as conn:
        rows = conn.execute(
            """
            SELECT id, name, email, role, active, created_at
            FROM users
            WHERE role = 'client'
            ORDER BY created_at DESC, id DESC
            """
        ).fetchall()
        open_tickets = conn.execute("SELECT COUNT(*) AS c FROM tickets").fetchone()["c"]

    clients = [
        {
            "id": row["id"],
            "name": row["name"],
            "email": row["email"],
            "company": "",
            "role": row["role"],
            "active": bool(row["active"]),
            "created_at": row["created_at"],
        }
        for row in rows
    ]

    return {
        "clients": clients,
        "stats": {
            "total_clients": len(clients),
            "active_clients": sum(1 for client in clients if client["active"]),
            "open_tickets": open_tickets,
        },
    }


def dashboard_payload(user):
    with db() as conn:
        if user["role"] == "admin":
            projects = conn.execute("SELECT * FROM projects ORDER BY updated_at DESC").fetchall()
            quotes = conn.execute("SELECT * FROM quotes ORDER BY updated_at DESC").fetchall()
            tickets = conn.execute("SELECT * FROM tickets ORDER BY created_at DESC").fetchall()
        else:
            projects = conn.execute(
                "SELECT * FROM projects WHERE client_id = ? ORDER BY updated_at DESC",
                (user["id"],),
            ).fetchall()
            quotes = conn.execute(
                "SELECT * FROM quotes WHERE client_id = ? ORDER BY updated_at DESC",
                (user["id"],),
            ).fetchall()
            tickets = conn.execute(
                "SELECT * FROM tickets WHERE client_id = ? ORDER BY created_at DESC",
                (user["id"],),
            ).fetchall()
        documents = conn.execute("SELECT * FROM documents ORDER BY id").fetchall()

    payload = {
        "projects": [dict(row) for row in projects],
        "quotes": [dict(row) for row in quotes],
        "tickets": [dict(row) for row in tickets],
        "documents": [dict(row) for row in documents],
    }
    if user["role"] == "admin":
        payload["admin"] = admin_clients_payload()
    return payload


def dashboard(environ, start_response):
    user, response = require_user(environ, start_response)
    if response:
        return response
    return json_response(start_response, dashboard_payload(user))


def admin_clients(environ, start_response):
    user, response = require_admin(environ, start_response)
    if response:
        return response

    method = environ.get("REQUEST_METHOD", "GET").upper()
    if method == "GET":
        return json_response(start_response, admin_clients_payload())
    if method != "POST":
        return json_response(start_response, {"error": "Method not allowed"}, "405 Method Not Allowed")

    try:
        data = parse_request_data(environ)
    except Exception:
        return json_response(start_response, {"error": "Invalid request body"}, "400 Bad Request")

    name = (data.get("name") or "").strip()
    email = (data.get("email") or "").strip().lower()
    access_code = (data.get("access_code") or "").strip() or secrets.token_urlsafe(18)

    if len(name) < 2:
        return json_response(start_response, {"error": "Client name is required"}, "400 Bad Request")
    if "@" not in email or "." not in email:
        return json_response(start_response, {"error": "A valid client email address is required"}, "400 Bad Request")
    if len(access_code) < 12:
        return json_response(start_response, {"error": "Access code must be at least 12 characters"}, "400 Bad Request")

    with db() as conn:
        existing = conn.execute("SELECT id FROM users WHERE lower(email) = ?", (email,)).fetchone()
        if existing:
            return json_response(start_response, {"error": "A portal account with this email already exists"}, "409 Conflict")
        cur = conn.execute(
            """
            INSERT INTO users (name, email, role, password_hash, active, created_at)
            VALUES (?, ?, 'client', ?, 1, ?)
            """,
            (name, email, hash_password(access_code), iso_now()),
        )
        client = conn.execute(
            "SELECT id, name, email, role, active, created_at FROM users WHERE id = ?",
            (cur.lastrowid,),
        ).fetchone()

    return json_response(
        start_response,
        {
            "ok": True,
            "client": {
                "id": client["id"],
                "name": client["name"],
                "email": client["email"],
                "company": "",
                "role": client["role"],
                "active": bool(client["active"]),
                "created_at": client["created_at"],
            },
            "access_code": access_code,
            "admin": admin_clients_payload(),
        },
        "201 Created",
    )


def tickets(environ, start_response):
    user, response = require_user(environ, start_response)
    if response:
        return response

    method = environ.get("REQUEST_METHOD", "GET").upper()
    if method == "GET":
        return json_response(start_response, {"tickets": dashboard_payload(user)["tickets"]})

    if method != "POST":
        return json_response(start_response, {"error": "Method not allowed"}, "405 Method Not Allowed")

    try:
        data = parse_request_data(environ)
    except Exception:
        return json_response(start_response, {"error": "Invalid request body"}, "400 Bad Request")

    request_type = (data.get("request_type") or "").strip()
    priority = (data.get("priority") or "Normal").strip()
    message = (data.get("message") or "").strip()
    if request_type not in {"CCTV / Security", "IT Support", "Networking / WiFi", "VoIP / Landline", "Website / Hosting", "Billing / Quote"}:
        return json_response(start_response, {"error": "Please select a valid support type"}, "400 Bad Request")
    if priority not in {"Normal", "Urgent", "Critical"}:
        return json_response(start_response, {"error": "Please select a valid priority"}, "400 Bad Request")
    if len(message) < 8:
        return json_response(start_response, {"error": "Please add a short request description"}, "400 Bad Request")

    with db() as conn:
        cur = conn.execute(
            """
            INSERT INTO tickets (client_id, request_type, priority, message, status, created_at)
            VALUES (?, ?, ?, ?, 'Open', ?)
            """,
            (user["id"], request_type, priority, message, iso_now()),
        )
        ticket = conn.execute("SELECT * FROM tickets WHERE id = ?", (cur.lastrowid,)).fetchone()

    send_ticket_email(user, dict(ticket))
    return json_response(start_response, {"ok": True, "ticket": dict(ticket)}, "201 Created")


def send_ticket_email(user, ticket):
    smtp_host = os.environ.get("FCG_SMTP_HOST")
    notify_to = os.environ.get("FCG_NOTIFY_TO", "info@futurecreativegroup.co.za")
    notify_from = os.environ.get("FCG_NOTIFY_FROM", os.environ.get("FCG_SMTP_USER", notify_to))
    if not smtp_host:
        log_event("SMTP not configured; ticket notification skipped")
        return

    msg = EmailMessage()
    msg["Subject"] = f"Client Portal Support Request: {ticket['request_type']}"
    msg["From"] = notify_from
    msg["To"] = notify_to
    msg.set_content(
        "\n".join(
            [
                "New client portal support request",
                "",
                f"Client: {user['name']} <{user['email']}>",
                f"Type: {ticket['request_type']}",
                f"Priority: {ticket['priority']}",
                f"Status: {ticket['status']}",
                f"Created: {ticket['created_at']}",
                "",
                ticket["message"],
            ]
        )
    )

    try:
        port = int(os.environ.get("FCG_SMTP_PORT", "587"))
        with smtplib.SMTP(smtp_host, port, timeout=10) as smtp:
            if os.environ.get("FCG_SMTP_TLS", "1") == "1":
                smtp.starttls()
            smtp_user = os.environ.get("FCG_SMTP_USER")
            smtp_password = os.environ.get("FCG_SMTP_PASSWORD")
            if smtp_user and smtp_password:
                smtp.login(smtp_user, smtp_password)
            smtp.send_message(msg)
    except Exception as exc:
        log_event(f"SMTP notification failed: {exc}")


def log_event(message):
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    with (DATA_DIR / "portal.log").open("a", encoding="utf-8") as handle:
        handle.write(f"{iso_now()} {message}\n")


def render_portal(environ, start_response):
    if not TEMPLATE_PATH.exists():
        return html_response(start_response, "<h1>Client Portal</h1><p>Template missing.</p>", "500 Internal Server Error")
    html = TEMPLATE_PATH.read_text(encoding="utf-8")
    return html_response(start_response, html)


def application(environ, start_response):
    init_db()
    path = normalize_path(environ)
    method = environ.get("REQUEST_METHOD", "GET").upper()

    if path in {"/", "/index.html"} and method == "GET":
        return render_portal(environ, start_response)
    if path.startswith("/static/") and method in {"GET", "HEAD"}:
        return static_response(environ, start_response, path.removeprefix("/static/"))
    if path == "/api/login" and method == "POST":
        return login(environ, start_response)
    if path == "/api/logout" and method == "POST":
        return logout(environ, start_response)
    if path == "/api/me" and method == "GET":
        return me(environ, start_response)
    if path == "/api/dashboard" and method == "GET":
        return dashboard(environ, start_response)
    if path == "/api/admin/clients":
        return admin_clients(environ, start_response)
    if path == "/api/tickets":
        return tickets(environ, start_response)
    return not_found(start_response)


if __name__ == "__main__":
    init_db()
    port = int(os.environ.get("PORT", "8090"))
    with make_server("127.0.0.1", port, application) as httpd:
        print(f"Future Creative Group portal backend running on http://127.0.0.1:{port}/portal/")
        httpd.serve_forever()
