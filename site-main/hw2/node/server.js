#!/usr/bin/env node
'use strict';

const http = require('http');
const url = require('url');
const os = require('os');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const querystring = require('querystring');

const PORT = 3010;
const SESSION_DIR = '/tmp/cse135_sessions_node';
const COOKIE_NAME = 'NODESESSID';

fs.mkdirSync(SESSION_DIR, { recursive: true });

function nowISO() {
  return new Date().toISOString();
}

function getClientIp(req) {
  // Apache proxy will usually pass X-Forwarded-For
  const xff = req.headers['x-forwarded-for'];
  if (xff) return String(xff).split(',')[0].trim();
  return req.socket?.remoteAddress || '';
}

function parseCookies(req) {
  const header = req.headers.cookie || '';
  const out = {};
  header.split(';').forEach(part => {
    const idx = part.indexOf('=');
    if (idx === -1) return;
    const k = part.slice(0, idx).trim();
    const v = part.slice(idx + 1).trim();
    if (k) out[k] = decodeURIComponent(v);
  });
  return out;
}

function newSessionId() {
  return crypto.randomBytes(18).toString('base64url');
}

function sessionPath(sid) {
  return path.join(SESSION_DIR, `${sid}.json`);
}

function loadSession(sid) {
  try {
    return JSON.parse(fs.readFileSync(sessionPath(sid), 'utf8'));
  } catch {
    return {};
  }
}

function saveSession(sid, data) {
  fs.writeFileSync(sessionPath(sid), JSON.stringify(data), 'utf8');
}

function ensureSession(req, res) {
  const cookies = parseCookies(req);
  let sid = cookies[COOKIE_NAME];
  let isNew = false;

  if (!sid) {
    sid = newSessionId();
    isNew = true;
  }

  if (isNew) {
    // Path=/ so it works for all endpoints
    res.setHeader('Set-Cookie', `${COOKIE_NAME}=${encodeURIComponent(sid)}; Path=/; HttpOnly; SameSite=Lax`);
  }

  return sid;
}

function readBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', c => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
  });
}

function sendJson(res, obj, status = 200) {
  const body = JSON.stringify(obj, null, 2);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
    'Cache-Control': 'no-store'
  });
  res.end(body);
}

function sendHtml(res, html, status = 200) {
  res.writeHead(status, {
    'Content-Type': 'text/html; charset=utf-8',
    'Content-Length': Buffer.byteLength(html),
    'Cache-Control': 'no-store'
  });
  res.end(html);
}

function notFound(res) {
  sendJson(res, { error: 'not found' }, 404);
}

function routePath(reqUrl) {
  // With ProxyPass "/hw2/node/" -> "http://127.0.0.1:3010/"
  // the node server will see paths like "/hello-html-node"
  const parsed = url.parse(reqUrl, true);
  return { pathname: parsed.pathname || '/', query: parsed.query || {} };
}

/** -------- handlers -------- */

function helloHtml(req, res, languageName) {
  const ip = getClientIp(req);
  const html = `<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>hello-html-${languageName}</title></head>
<body>
  <h1>Hello from Alessio Yu</h1>
  <ul>
    <li>Language: ${languageName}</li>
    <li>Time: ${nowISO()}</li>
    <li>IP: ${ip}</li>
  </ul>
</body>
</html>`;
  sendHtml(res, html);
}

function helloJson(req, res, languageName) {
  sendJson(res, {
    message: 'Hello from Alessio Yu',
    language: languageName,
    time: nowISO(),
    ip: getClientIp(req)
  });
}

function environment(req, res, languageName) {
  // In Node, "environment" in the CGI sense is roughly:
  // process.env + request headers + request metadata
  sendJson(res, {
    language: languageName,
    time: nowISO(),
    hostname: os.hostname(),
    ip: getClientIp(req),
    method: req.method,
    url: req.url,
    headers: req.headers,
    env: process.env
  });
}

async function echo(req, res, languageName) {
  const { pathname, query } = routePath(req.url);
  const ctype = (req.headers['content-type'] || '').split(';')[0].trim().toLowerCase();
  const raw = await readBody(req);

  let parsedBody = null;
  let parseError = null;

  if (req.method === 'GET') {
    parsedBody = query; // echo query params for GET
  } else if (raw.length === 0) {
    parsedBody = {};
  } else if (ctype === 'application/json') {
    try {
      parsedBody = JSON.parse(raw);
    } catch (e) {
      parseError = String(e);
      parsedBody = null;
    }
  } else {
    // default: x-www-form-urlencoded (or anything else treat as qs)
    parsedBody = querystring.parse(raw);
  }

  sendJson(res, {
    language: languageName,
    time: nowISO(),
    hostname: os.hostname(),
    ip: getClientIp(req),
    method: req.method,
    path: pathname,
    contentType: ctype || null,
    userAgent: req.headers['user-agent'] || null,
    query,
    rawBody: raw,
    parsedBody,
    parseError
  }, parseError ? 400 : 200);
}

async function state(req, res, languageName) {
  const sid = ensureSession(req, res);
  const sess = loadSession(sid);

  const { pathname, query } = routePath(req.url);
  const ctype = (req.headers['content-type'] || '').split(';')[0].trim().toLowerCase();
  const raw = await readBody(req);

  // Actions:
  // - GET /state-node -> show state
  // - POST/PUT /state-node -> save state fields
  // - POST /state-node/clear -> clear state
  if (pathname === '/state-node/clear') {
    saveSession(sid, {});
    return sendJson(res, {
      message: 'cleared',
      language: languageName,
      time: nowISO(),
      ip: getClientIp(req),
      sessionId: sid,
      state: {}
    });
  }

  if (req.method === 'GET') {
    return sendJson(res, {
      message: 'state',
      language: languageName,
      time: nowISO(),
      ip: getClientIp(req),
      sessionId: sid,
      state: sess
    });
  }

  // Save state from body
  let incoming = {};
  if (raw.length === 0) {
    incoming = {};
  } else if (ctype === 'application/json') {
    try { incoming = JSON.parse(raw) || {}; } catch { incoming = {}; }
  } else {
    incoming = querystring.parse(raw);
  }

  const next = { ...sess, ...incoming, _lastUpdated: nowISO() };
  saveSession(sid, next);

  return sendJson(res, {
    message: 'saved',
    language: languageName,
    time: nowISO(),
    ip: getClientIp(req),
    sessionId: sid,
    saved: incoming,
    state: next
  });
}

/** -------- server -------- */

const server = http.createServer(async (req, res) => {
  try {
    const { pathname } = routePath(req.url);

    // Basic routing
    if (pathname === '/' || pathname === '/health') {
      return sendJson(res, { ok: true, time: nowISO() });
    }

    // HW2 endpoints (match your naming)
    if (pathname === '/hello-html-node') return helloHtml(req, res, 'node');
    if (pathname === '/hello-json-node') return helloJson(req, res, 'node');
    if (pathname === '/environment-node') return environment(req, res, 'node');
    if (pathname === '/echo-node') return echo(req, res, 'node');

    // state endpoints
    if (pathname === '/state-node' || pathname === '/state-node/clear') {
      return state(req, res, 'node');
    }

    return notFound(res);
  } catch (e) {
    sendJson(res, { error: 'server error', details: String(e) }, 500);
  }
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`HW2 Node server listening on http://127.0.0.1:${PORT}`);
});
