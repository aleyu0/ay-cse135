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

def clear_cookie_header():
    c = cookies.SimpleCookie()
    c[COOKIE_NAME] = "deleted"
    c[COOKIE_NAME]["path"] = "/"
    c[COOKIE_NAME]["expires"] = "Thu, 01 Jan 1970 00:00:00 GMT"
    c[COOKIE_NAME]["max-age"] = 0
    return c.output(header="Set-Cookie:").strip()

c = parse_cookie()
sid_cookie = c.get(COOKIE_NAME)
sid = sid_cookie.value if sid_cookie else ""

# delete server-side session file
if sid:
    try:
        os.remove(session_path(sid))
    except FileNotFoundError:
        pass
    except Exception:
        pass

print("Status: 303 See Other")
print("Location: /cgi-bin/hw2-python/state-view-python.py")
print("Cache-Control: no-cache")
print(clear_cookie_header())
print("Content-Type: text/html; charset=UTF-8\n")
print("<!doctype html><html><body>Cleared. Redirecting...</body></html>")
