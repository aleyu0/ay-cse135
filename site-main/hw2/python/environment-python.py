#!/usr/bin/python3
import os

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=UTF-8\n")

print("<!doctype html><html><head><title>Environment (Python)</title></head><body>")
print("<h1>Environment Variables (Python)</h1><hr/>")
print("<pre>")
for k in sorted(os.environ.keys()):
    v = os.environ.get(k, "")
    v = (v.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;"))
    print(f"{k} = {v}")
print("</pre></body></html>")
