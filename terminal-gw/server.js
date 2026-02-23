require('dotenv').config();
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const jwt = require('jsonwebtoken');
const pty = require('node-pty');
const cors = require('cors');
const { execFile } = require('child_process');

const app = express();
app.use(cors());

const server = http.createServer(app);
const wss = new WebSocket.Server({ server, path: '/ws' });

const JWT_SECRET = process.env.TERMINAL_JWT_SECRET || 'change-me';
const LXD_BIN = process.env.LXD_BIN || 'lxc';

const activeSessions = new Map();

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

// Send clean lines (no ANSI)
function sendLine(ws, line = '') {
  if (ws.readyState !== WebSocket.OPEN) return;
  ws.send(`${line}\r\n`);
}

// Remove the annoying first line (both "real ANSI" and "escaped ANSI" versions)
function sanitizeOutgoing(data) {
  let s = typeof data === 'string' ? data : data.toString('utf8');

  // 1) If it arrives as literal escaped text (with backslashes shown in xterm)
  // Example: \r\n\x1b[32m[TTYLabBox] Connected to onevm successfully.\x1b[0m\r\n
  s = s.replace(/\\r\\n\\x1b\[[0-9;]*m\[TTYLabBox\][^\n]*?\\x1b\[0m\\r\\n/g, '');
  s = s.replace(/\\r\\n\\x1b\[[0-9;]*m\[TTYLabBox\][^\n]*?\\x1b\[0m/g, '');

  // 2) If it arrives as actual ANSI escape sequences
  // Example: \r\n\x1b[32m[TTYLabBox] ... \x1b[0m\r\n
  s = s.replace(/\r?\n\x1b\[[0-9;]*m\[TTYLabBox\][^\r\n]*?\x1b\[0m\r?\n/g, '');
  s = s.replace(/\r?\n\x1b\[[0-9;]*m\[TTYLabBox\][^\r\n]*?\x1b\[0m/g, '');

  return s;
}

async function waitForExecReady(instanceName, tries = 80, delayMs = 500) {
  const execFileP = (args) =>
    new Promise((res, rej) => {
      execFile(LXD_BIN, args, (err, stdout, stderr) => {
        if (err) return rej(stderr || err.message);
        res(stdout);
      });
    });

  for (let i = 0; i < tries; i++) {
    try {
      // If this works, the VM agent is ready for exec
      await execFileP(['exec', instanceName, '--', 'bash', '-lc', 'echo READY']);
      return true;
    } catch (e) {
      await sleep(delayMs);
    }
  }
  return false;
}

// IMPORTANT: must be async because we use await inside
wss.on('connection', async (ws, req) => {
  let ptyProcess = null;

  try {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const token = url.searchParams.get('token');
    const attemptId = url.searchParams.get('attemptId');
    const nodeName = url.searchParams.get('node');

    if (!token || !attemptId || !nodeName) {
      sendLine(ws, '[Gateway] Missing auth parameters.');
      ws.close();
      return;
    }

    let decoded;
    try {
      decoded = jwt.verify(token, JWT_SECRET);
    } catch (err) {
      sendLine(ws, '[Gateway] Invalid or expired token.');
      ws.close();
      return;
    }

    if (decoded.attemptId.toString() !== attemptId || decoded.nodeName !== nodeName) {
      sendLine(ws, '[Gateway] Token mismatch with node request.');
      ws.close();
      return;
    }

    const instanceName = decoded.instanceName;
    const sessionKey = `${attemptId}:${nodeName}`;

    // terminate older session for same attempt/node
    if (activeSessions.has(sessionKey)) {
      const old = activeSessions.get(sessionKey);
      try { old.ws?.close(); } catch (e) {}
      try { old.pty?.kill(); } catch (e) {}
      activeSessions.delete(sessionKey);
    }

    // UX: show a single clean wait line
    sendLine(ws, 'Please wait until the VM is ready...');

    // Wait until lxc exec works (VM agent ready)
    const ready = await waitForExecReady(instanceName);
    if (!ready) {
      sendLine(ws, 'VM is taking too long to get ready. Please try again.');
      ws.close();
      return;
    }

    // Spawn PTY -> lxc exec -> bash
    ptyProcess = pty.spawn(
      LXD_BIN,
      ['exec', instanceName, '--', 'bash', '-lc', 'exec bash'],
      {
        name: 'xterm-color',
        cols: 80,
        rows: 24,
        cwd: process.env.HOME,
        env: process.env,
      }
    );

    activeSessions.set(sessionKey, { ws, pty: ptyProcess });

    // optional: short clean connected line (no ANSI)
    sendLine(ws, '[TTYLabBox] Connected.');

    ptyProcess.onData((chunk) => {
      if (ws.readyState !== WebSocket.OPEN) return;
      const out = sanitizeOutgoing(chunk);
      if (out) ws.send(out);
    });

    ptyProcess.onExit(({ exitCode }) => {
      if (ws.readyState === WebSocket.OPEN) {
        sendLine(ws, '[TTYLabBox] Connection closed.');
        ws.close();
      }
      activeSessions.delete(sessionKey);
    });

    ws.on('message', (message) => {
      if (!ptyProcess) return;

      const text = Buffer.isBuffer(message) ? message.toString('utf8') : String(message);

      try {
        const msg = JSON.parse(text);

        // support both cols/rows and cols/rows naming
        if (msg.type === 'resize') {
          const cols = msg.cols ?? msg.columns;
          const rows = msg.rows ?? msg.rowsCount;
          if (cols && rows) {
            try { ptyProcess.resize(cols, rows); } catch (e) {}
          }
          return;
        }

        if (msg.type === 'input' && typeof msg.data === 'string') {
          ptyProcess.write(msg.data);
          return;
        }

        // ignore anything else
        return;
      } catch (e) {
        // raw passthrough (fallback)
        ptyProcess.write(text);
      }
    });

    ws.on('close', () => {
      try { ptyProcess?.kill(); } catch (e) {}
      const cur = activeSessions.get(sessionKey);
      if (cur?.ws === ws) activeSessions.delete(sessionKey);
    });

  } catch (err) {
    try { ws.close(); } catch (e) {}
  }
});

const PORT = process.env.PORT || 8081;
server.listen(PORT, '0.0.0.0', () => {
  console.log(`[TTYLabBox] Terminal Gateway listening on WS port ${PORT}`);
});
