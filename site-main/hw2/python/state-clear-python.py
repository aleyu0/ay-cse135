#!/usr/bin/python3
import os
from http import cookies

SESSION_DIR = "/tmp/cse135_sessions"
COOKIE_NAME = "PYSESSID"

def parse_cookie():
    c = cookies.SimpleCookie()
    c.load(os.environ.get("HTTP_COOKIE", ""))
    return c

def session_path(sid: str) -> str:
    return os.path.join(SESSION_DIR, f"{sid}.json")

c = parse_cookie()
sid = c.get(COOKIE_NAME)
sid = sid.value if sid else ""

# delete server-side session file
if sid:
    try:
        os.remove(session_path(sid))
    except Exception:
        pass

# cookie expire
out = cookies.SimpleCookie()
out[COOKIE_NAME] = ""
out[COOKIE_NAME]["path"] = "/"
out[COOKIE_NAME]["expires"] = "Thu, 01 Jan 1970 00:00:00 GMT"

print("Status: 303 See Other")
print("Location: /cgi-bin/hw2-python/state-view-python.py")
print("Cache-Control: no-cache")
print(out.output(header="").strip())
print("Content-Type: text/html; charset=UTF-8\n")
print("<!doctype html><html><body>Cleared. Redirecting...</body></html>")
