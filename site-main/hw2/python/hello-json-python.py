import os
import json
import datetime

print("Cache-Control: no-cache")
print("Content-Type: application/json; charset=UTF-8\n")

response = {
    "message": "Hello World!",
    "author": "Alessio",
    "language": "Python",
    "generated_at": datetime.datetime.now().isoformat(),
    "ip": os.environ.get("REMOTE_ADDR", "unknown")
}

print(json.dumps(response, indent=2))
