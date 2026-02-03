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

function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
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

function helloHtml(req, res, languageName) {
    const ip = getClientIp(req);
    const date = nowISO();

    const html = `<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Hello HTML (Node)</title>
            <style>
                html{
                    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text",
                        system-ui, sans-serif;
                    font-style: normal;
                }
                body{
                    margin: 0;
                    max-width: 960px;
                    margin: 0 auto;
                    padding: 5rem 1.5rem 6rem;
                }        
            </style>
        </head>
        <body>
            <h1>Hi there!</h1>
            <p>This page was written by Alessio in NodeJS (no Express) for CSE 135, homework 2.</p>
            <p>Generated at: ${escapeHtml(date)}</p>
            <p>Your IP: ${escapeHtml(ip)}</p>
            <a href="javascript:history.back()">Go Back</a>
        </body>
        </html>`;

    sendHtml(res, html);
}

function helloJson(req, res, languageName) {
    sendJson(res, {
        "message": "Hello world!",
        "author": "Alessio",
        "language": "NodeJS (no Express)",
        "generated_at": nowISO(),
        "your ip": getClientIp(req)
    });
}

function environment(req, res, languageName) {
    const skip = new Set(['AUTH_TYPE', 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'HTTP_AUTHORIZATION']);
    const envMap = {
        ...process.env,
        REQUEST_METHOD: req.method,
        REQUEST_URI: req.url,
        REMOTE_ADDR: getClientIp(req),
        HTTP_USER_AGENT: req.headers['user-agent'] || '',
    };

    for (const [k, v] of Object.entries(req.headers)) {
        const key = 'HTTP_' + k.toUpperCase().replace(/-/g, '_');
        if (!skip.has(key)) envMap[key] = Array.isArray(v) ? v.join(', ') : String(v ?? '');
    }

    const keys = Object.keys(envMap).sort();

    let body = `<!doctype html><html><head><meta charset="utf-8"><title>Environment (Node)</title></head><body>`;
    body += `<h1>Environment Variables (Node)</h1><hr/>`;
    body += `<h2>ENV</h2><pre>`;

    for (const k of keys) {
        if (skip.has(k)) continue;
        body += `${escapeHtml(k)} = ${escapeHtml(envMap[k])}\n`;
    }

    body += `</pre></body></html>`;
    sendHtml(res, body);
}

async function echo(req, res, languageName) {
    const method = req.method || 'UNKNOWN';
    const ip = getClientIp(req);
    const userAgent = req.headers['user-agent'] || '';
    const contentType = req.headers['content-type'] || '';
    const timestamp = nowISO();
    const hostname = os.hostname();
    const parsedUrl = url.parse(req.url, true);
    const query = parsedUrl.query || {};
    const raw = await readBody(req);

    let post = {};
    const ctype = contentType.split(';')[0].trim().toLowerCase();

    if (raw.length > 0 && ctype === 'application/x-www-form-urlencoded') {
        post = querystring.parse(raw);
    } else if (raw.length > 0 && ctype === 'application/json') {
        post = {};
    }

    const html = `<!doctype html>
        <html>
        <head>
        <meta charset="utf-8" />
        <title>${escapeHtml(method)} Node</title>
        <style>
            html{
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text",
                system-ui, sans-serif;
            font-style: normal;
            }
            body{
            margin: 0;
            max-width: 960px;
            margin: 0 auto;
            padding: 5rem 1.5rem 6rem;
            }        
            table { border-collapse: collapse; }
            td { border: 1px solid #ddd; padding: 8px 10px; vertical-align: top; }
        </style>
        </head>
        <body>
        <p>Here are the details of your request.</p>
        <table>
            <tr><td>Method</td><td>${escapeHtml(method)}</td></tr>
            <tr><td>Timestamp</td><td>${escapeHtml(timestamp)}</td></tr>
            <tr><td>IP</td><td>${escapeHtml(ip)}</td></tr>
            <tr><td>User Agent</td><td>${escapeHtml(userAgent)}</td></tr>
            <tr><td>Content-Type</td><td>${escapeHtml(contentType)}</td></tr>
            <tr><td>Hostname</td><td>${escapeHtml(hostname)}</td></tr>
            <tr><td>Get Query</td><td>${escapeHtml(JSON.stringify(query))}</td></tr>
            <tr><td>Post</td><td>${escapeHtml(JSON.stringify(post))}</td></tr>
            <tr><td>Raw content</td><td>${escapeHtml(raw)}</td></tr>
        </table>
        <a href="javascript:history.back()">Go Back</a>
        </body>
        </html>`;
    sendHtml(res, html);
}

function renderStateFormNode(current, msg) {
    const choices = ["Vanilla", "Chocolate", "Strawberry", "Pistachio", "Mint Chip", "Cookies & Cream"];
    const options = choices.map(c => {
        const selected = current === c ? ' selected' : '';
        return `<option value="${escapeHtml(c)}"${selected}>${escapeHtml(c)}</option>`;
    }).join('\n');

    const msgHtml = msg ? `<div class="msg">${escapeHtml(msg)}</div>` : '';
    return `<!doctype html>
        <html>
        <head>
        <meta charset="utf-8" />
        <title>Node State - Set Favorite Ice Cream</title>
            <style>
                html { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif; }
                body { margin: 0; max-width: 960px; margin: 0 auto; padding: 5rem 1.5rem 6rem; }
                .card { border: 3px solid black; border-radius: 12px; padding: 16px; }
                label { display:block; margin-top: 12px; font-weight: 600; }
                select, button { width: 100%; padding: 10px; margin-top: 6px; }
                .row { display:flex; gap: 12px; margin-top: 12px; }
                .row button { width: 100%; }
                .muted { opacity: 0.75; }
                .msg { margin: 12px 0 0; font-weight: 600; }
                a { display:inline-block; margin-top: 16px; }
            </style>
        </head>
        <body>
            <h1>Node State Demo</h1>
            <p class="muted">I'll remember your favorite ice cream by storing ur cookie.</p>

            <div class="card">
                <form method="POST" action="/hw2/node/state-form-node">
                    <label for="favorite">Favorite ice cream</label>
                    <select id="favorite" name="favorite">
                        <option value="">-- choose one --</option>
                        ${options}
                    </select>

                    <div class="row">
                        <button type="submit" name="save" value="1">Save</button>
                        <button type="submit" name="clear" value="1">Clear</button>
                    </div>
                    ${msgHtml}
                </form>
                <a href="/hw2/node/state-view-node">Go to view page</a>
            </div>
        </body>
        </html>`;
}

function renderStateViewNode(current) {
    const has = current !== '';
    const main = has
        ? `<p class="big">Your favorite ice cream is: ${escapeHtml(current)}</p>`
        : `<p class="big">No favorite saved yet.</p>
            <p class="muted">Go set one on the other page.</p>`;

    return `<!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title>Node State - View Favorite Ice Cream</title>
            <style>
                html { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif; }
                body { margin: 0; max-width: 960px; margin: 0 auto; padding: 5rem 1.5rem 6rem; }
                .card { border: 3px solid black; border-radius: 12px; padding: 16px; }
                .big { font-size: 1.1rem; font-weight: 700; }
                .muted { opacity: 0.75; }
                button { width: 100%; padding: 10px; margin-top: 12px; }
                a { display:inline-block; margin-top: 16px; }
            </style>
        </head>
        <body>
        <h1>Node State Demo</h1>
        <p class="muted">Hmm, I remember ur favorite ice cream.</p>
        <div class="card">
            ${main}

            <form method="POST" action="/hw2/node/state-view-node">
            <button type="submit" name="clear" value="1">Clear</button>
            </form>

            <a href="/hw2/node/state-form-node">Back to set page</a>
        </div>
        </body>
        </html>`;
}

async function stateFormNode(req, res) {
  const sid = ensureSession(req, res);
  const sess = loadSession(sid);
  let msg = '';

  if (req.method === 'POST') {
    const raw = await readBody(req);
    const form = querystring.parse(raw);

    if (form.clear) {
      delete sess.favorite_ice_cream;
      msg = 'Cleared.';
    } else {
      const fav = (form.favorite || '').trim();
      if (fav) {
        sess.favorite_ice_cream = fav;
        msg = 'Saved.';
      } else {
        msg = 'Pick one first.';
      }
    }

    saveSession(sid, sess);
  }

  const current = sess.favorite_ice_cream || '';
  sendHtml(res, renderStateFormNode(current, msg));
}

async function stateViewNode(req, res) {
    const sid = ensureSession(req, res);
    const sess = loadSession(sid);
    if (req.method === 'POST') {
        const raw = await readBody(req);
        const form = querystring.parse(raw);
        if (form.clear) {
            delete sess.favorite_ice_cream;
            saveSession(sid, sess);
        }
    }
    const current = sess.favorite_ice_cream || '';
    sendHtml(res, renderStateViewNode(current));
}

/** -------- server -------- */

const server = http.createServer(async (req, res) => {
    try {
        const { pathname } = routePath(req.url);

        if (pathname === '/' || pathname === '/health') {
            return sendJson(res, { ok: true, time: nowISO() });
        }
        if (pathname === '/hello-html-node') return helloHtml(req, res, 'node');
        if (pathname === '/hello-json-node') return helloJson(req, res, 'node');
        if (pathname === '/environment-node') return environment(req, res, 'node');
        if (pathname === '/echo-node') return echo(req, res, 'node');
        if (pathname === '/state-form-node') return stateFormNode(req, res);
        if (pathname === '/state-view-node') return stateViewNode(req, res);

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
