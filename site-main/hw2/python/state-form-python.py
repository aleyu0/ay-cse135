#!/usr/bin/python3
import os
import json
import secrets
import urllib.parse
from http import cookies
from datetime import datetime, timezone

SESSION_DIR = "/tmp/cse135_sessions"
COOKIE_NAME = "PYSESSID"

def ensure_session_dir():
    os.makedirs(SESSION_DIR, exist_ok=True)

def parse_cookie():
    c = cookies.SimpleCookie()
    raw = os.environ.get("HTTP_COOKIE", "")
    c.load(raw)
    return c

def get_or_create_session_id():
    c = parse_cookie()
    sid = c.get(COOKIE_NAME)
    if sid and sid.value:
        return sid.value, False
    return secrets.token_urlsafe(24), True

def session_path(sid: str) -> str:
    return os.path.join(SESSION_DIR, f"{sid}.json")

def load_session(sid: str) -> dict:
    try:
        with open(session_path(sid), "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}

def save_session(sid: str, data: dict):
    ensure_session_dir()
    with open(session_path(sid), "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2)

def set_cookie_header(sid: str):
    c = cookies.SimpleCookie()
    c[COOKIE_NAME] = sid
    c[COOKIE_NAME]["path"] = "/"
    # Session cookie (no Expires/Max-Age)
    return c.output(header="").strip()

def h(s):
    import html
    return html.escape(str(s), quote=True)

method = os.environ.get("REQUEST_METHOD", "GET").upper()
sid, is_new = get_or_create_session_id()
sess = load_session(sid)

if method == "POST":
    length = int(os.environ.get("CONTENT_LENGTH", "0") or "0")
    body = os.sys.stdin.read(length) if length > 0 else ""
    form = urllib.parse.parse_qs(body)
    topping = (form.get("topping", [""])[0] or "").strip()

    if topping:
        sess["favorite_topping"] = topping
        sess["updated_at"] = datetime.now(timezone.utc).isoformat()
        save_session(sid, sess)

    # redirect to view
    print("Status: 303 See Other")
    print("Location: /cgi-bin/hw2-python/state-view-python.py")
    print("Cache-Control: no-cache")
    if is_new:
        print(set_cookie_header(sid))
    print("Content-Type: text/html; charset=UTF-8\n")
    print("<!doctype html><html><body>Redirecting...</body></html>")
    raise SystemExit

# GET: show form
print("Cache-Control: no-cache")
if is_new:
    print(set_cookie_header(sid))
print("Content-Type: text/html; charset=UTF-8\n")

current = sess.get("favorite_topping", "")

print(f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Python State - Pick Topping</title>
  <style>
    html {{ font-family: -apple-system,BlinkMacSystemFont,"SF Pro Text",system-ui,sans-serif; }}
    body {{ margin:0; max-width:960px; margin:0 auto; padding:5rem 1.5rem 6rem; }}
    .row {{ display:flex; gap:12px; flex-wrap:wrap; }}
    button {{ padding:10px 14px; border:1px solid #ccc; background:#fff; border-radius:10px; cursor:pointer; }}
    .card {{ border:3px solid black; border-radius:16px; padding:16px; }}
    small {{ opacity:.8; }}
  </style>
</head>
<body>
  <h1>Python State Demo</h1>
  <p>Choose your favorite pizza topping. It will be remembered server-side.</p>

  <div class="card">
    <form method="POST" action="/cgi-bin/hw2-python/state-form-python.py">
      <div class="row">
        <button name="topping" value="Pepperoni" type="submit">Pepperoni</button>
        <button name="topping" value="Mushroom" type="submit">Mushroom</button>
        <button name="topping" value="Olives" type="submit">Olives</button>
        <button name="topping" value="Pineapple" type="submit">Pineapple</button>
        <button name="topping" value="Sausage" type="submit">Sausage</button>
      </div>
    </form>
  </div>

  <p><small>Current saved topping: <b>{h(current) if current else "None yet"}</b></small></p>

  <p>
    <a href="/cgi-bin/hw2-python/state-view-python.py">Go to view page</a>
  </p>
</body>
</html>
""")
