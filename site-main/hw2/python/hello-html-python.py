import os
import datetime

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=UTF-8\n")

ip = os.environ.get("REMOTE_ADDR", "unknown")
date = datetime.datetime.now().isoformat()

print(f"""
<!doctype html>
<html>
<head>
  <title>Hello HTML (Python)</title>
  <style>
    html {{
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text",
        system-ui, sans-serif;
    }}
    body {{
      max-width: 960px;
      margin: 0 auto;
      padding: 5rem 1.5rem 6rem;
    }}
  </style>
</head>
<body>
  <h1>Hi there!</h1>
  <p>This page was written by Alessio in Python for CSE 135, homework 2.</p>
  <p>Generated at: {date}</p>
  <p>Your IP: {ip}</p>
  <a href="javascript:history.back()">Go Back</a>
</body>
</html>
""")