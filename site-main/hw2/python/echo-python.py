#!/usr/bin/python3
import os
import sys
import html
import datetime
from urllib.parse import parse_qs

def h(v):
    return html.escape(str(v), quote=True)

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=UTF-8\n")

method = os.environ.get("REQUEST_METHOD", "UNKNOWN")
ip = os.environ.get("REMOTE_ADDR", "unknown")
user_agent = os.environ.get("HTTP_USER_AGENT", "")
content_type = os.environ.get("CONTENT_TYPE", "")
hostname = os.environ.get("SERVER_NAME", "")
timestamp = datetime.datetime.now().isoformat()

query_string = os.environ.get("QUERY_STRING", "")
query = parse_qs(query_string)

length = int(os.environ.get("CONTENT_LENGTH", "0") or "0")
raw = sys.stdin.read(length) if length > 0 else ""

print(f"""
<!doctype html>
<html>
<head>
  <title>GET Python</title>
  <style>
    html {{
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text",
        system-ui, sans-serif;
    }}
    body {{
      margin: 0;
      max-width: 960px;
      margin: 0 auto;
      padding: 5rem 1.5rem 6rem;
    }}
    table {{ border-collapse: collapse; }}
    td {{ border: 1px solid #ccc; padding: 6px 10px; }}
  </style>
</head>
<body>
  <p>Here are the details of your request.</p>
  <table>
    <tr><td>Method</td><td>{h(method)}</td></tr>
    <tr><td>Timestamp</td><td>{h(timestamp)}</td></tr>
    <tr><td>IP</td><td>{h(ip)}</td></tr>
    <tr><td>User Agent</td><td>{h(user_agent)}</td></tr>
    <tr><td>Content-Type</td><td>{h(content_type)}</td></tr>
    <tr><td>Hostname</td><td>{h(hostname)}</td></tr>
    <tr><td>Get Query</td><td>{h(query)}</td></tr>
    <tr><td>Raw content</td><td>{h(raw)}</td></tr>
  </table>
  <br>
  <a href="javascript:history.back()">Go Back</a>
</body>
</html>
""")
