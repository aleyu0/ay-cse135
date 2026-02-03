#!/usr/bin/python3
import os
import json
from http import cookies
import html

SESSION_DIR = "/tmp/cse135_sessions"
COOKIE_NAME = "PYSESSID"

def session_path(sid: str) -> str:
    return os.path.join(SESSION_DIR, f"{sid}.json")

def parse_cookie():
    c = cookies.SimpleCookie()
    c.load(os.environ.get("HTTP_COOKIE", ""))
    return c

def load_session(sid: str) -> dict:
    try:
        with open(session_path(sid), "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}

def h(s):
    return html.escape(str(s), quote=True)

c = parse_cookie()
sid = c.get(COOKIE_NAME)
sid = sid.value if sid else ""

sess = load_session(sid) if sid else {}
topping = sess.get("favorite_topping", "")
updated = sess.get("updated_at", "")

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=UTF-8\n")

print(f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Python State - View</title>
  <style>
    html {{ font-family: -apple-system,BlinkMacSystemFont,"SF Pro Text",system-ui,sans-serif; }}
    body {{ margin:0; max-width:960px; margin:0 auto; padding:5rem 1.5rem 6rem; }}
    .card {{ border:3px solid black; border-radius:16px; padding:16px; }}
    a.button {{
      display:inline-block; padding:10px 14px; border:1px solid #ccc;
      border-radius:10px; text-decoration:none; margin-right:10px;
    }}
    small {{ opacity:.8; }}
  </style>
</head>
<body>
  <h1>Python State Demo</h1>

  <div class="card">
    <p>Your favorite pizza topping is:</p>
    <h2>{h(topping) if topping else "Not set yet"}</h2>
    <p><small>Last updated: {h(updated) if updated else "-"}</small></p>
  </div>

  <p>
    <a class="button" href="/cgi-bin/hw2-python/state-form-python.py">Change topping</a>
    <a class="button" href="/cgi-bin/hw2-python/state-clear-python.py">Clear</a>
  </p>
</body>
</html>
""")
