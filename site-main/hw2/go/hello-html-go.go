package main

import (
	"fmt"
	"os"
	"time"
)

func main() {
	ip := os.Getenv("REMOTE_ADDR")
	now := time.Now().Format(time.RFC3339)

	fmt.Print("Content-Type: text/html; charset=UTF-8\r\n")
	fmt.Print("Cache-Control: no-cache\r\n\r\n")

	fmt.Printf(`<!doctype html>
<html><head><meta charset="utf-8"><title>Hello HTML - Go</title></head>
<body>
  <h1>Hi there!</h1>
  <p>This page was written by Alessio in Go for CSE 135, homework 2.</p>
  <p>Generated at: %s</p>
  <p>Your IP: %s</p>
</body></html>`, now, ip)
}
