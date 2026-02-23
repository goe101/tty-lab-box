require('dotenv').config();
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const jwt = require('jsonwebtoken');
const pty = require('node-pty');
const cors = require('cors');

const app = express();
app.use(cors());
const server = http.createServer(app);
const wss = new WebSocket.Server({ server, path: '/ws' });

const JWT_SECRET = process.env.TERMINAL_JWT_SECRET || 'change-me';
const LXD_BIN = process.env.LXD_BIN || 'lxc';

const activeSessions = new Map();

const { execFile } = require("child_process");

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function waitForExecReady(instanceName, tries = 60, delayMs = 500) {
    const execFileP = (args) => new Promise((res, rej) => {
        execFile(LXD_BIN, args, (err, stdout, stderr) => {
            if (err) return rej(stderr || err.message);
            res(stdout);
        });
    });

    for (let i = 0; i < tries; i++) {
        try {
            await execFileP(["exec", instanceName, "--", "bash", "-lc", "echo READY"]);
            return true;
        } catch (e) {
            await sleep(delayMs);
        }
    }
    return false;
}

wss.on('connection', (ws, req) => {
    try {
        const url = new URL(req.url, `http://${req.headers.host}`);
        const token = url.searchParams.get('token');
        const attemptId = url.searchParams.get('attemptId');
        const nodeName = url.searchParams.get('node');

        if (!token || !attemptId || !nodeName) {
            ws.send('\r\n\x1b[31m[Gateway] Missing Auth Parameters.\x1b[0m\r\n');
            ws.close();
            return;
        }

        let decoded;
        try {
            decoded = jwt.verify(token, JWT_SECRET);
        } catch (err) {
            ws.send('\r\n\x1b[31m[Gateway] Invalid or expired token.\x1b[0m\r\n');
            ws.close();
            return;
        }

        if (decoded.attemptId.toString() !== attemptId || decoded.nodeName !== nodeName) {
            ws.send('\r\n\x1b[31m[Gateway] Token mismatch with node request.\x1b[0m\r\n');
            ws.close();
            return;
        }

        const instanceName = decoded.instanceName;
        const sessionKey = `${attemptId}:${nodeName}`;

        if (activeSessions.has(sessionKey)) {
            ws.send('\r\n\x1b[33m[Gateway] Terminating previous connection...\x1b[0m\r\n');
            const oldWs = activeSessions.get(sessionKey).ws;
            const oldPty = activeSessions.get(sessionKey).pty;
            if (oldWs.readyState === WebSocket.OPEN) oldWs.close();
            try { oldPty.kill(); } catch (e) { }
        }
        // Show ONE clean line (no ANSI)
        if (ws.readyState === WebSocket.OPEN) {
            ws.send("Please wait until VM gets ready...\r\n");
        }

        // Wait until LXD exec becomes available (agent ready)
        const ready = await waitForExecReady(instanceName);
        if (!ready) {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send("VM is taking too long to get ready. Please try again.\r\n");
            }
            ws.close();
            return;
        }
        const ptyProcess = pty.spawn(LXD_BIN, ['exec', instanceName, '--', 'bash', '-lc', 'exec bash'], {
            name: 'xterm-color',
            cols: 80,
            rows: 24,
            cwd: process.env.HOME,
            env: process.env
        });

        if (ws.readyState === WebSocket.OPEN) {
            ws.send("[TTYLabBox] Connected.\r\n");
        }

        activeSessions.set(sessionKey, { ws, pty: ptyProcess });

        ptyProcess.onData((data) => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(data);
            }
        });

        ptyProcess.onExit(({ exitCode }) => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send("\r\n[TTYLabBox] Connection closed.\r\n");
                ws.close();
            }
            activeSessions.delete(sessionKey);
        });

        ws.on('message', (message) => {
            const text = Buffer.isBuffer(message) ? message.toString('utf8') : String(message);

            try {
                const msg = JSON.parse(text);

                if (msg.type === 'resize' && msg.cols && msg.rows) {
                    try { ptyProcess.resize(msg.cols, msg.rows); } catch (e) { }
                    return;
                }

                if (msg.type === 'input' && typeof msg.data === 'string') {
                    ptyProcess.write(msg.data);
                    return;
                }
                return;

            } catch (e) {
                ptyProcess.write(text);
            }
        });

        ws.on('close', () => {
            try { ptyProcess.kill(); } catch (e) { }
            if (activeSessions.get(sessionKey)?.ws === ws) {
                activeSessions.delete(sessionKey);
            }
        });

    } catch (err) {
        ws.close();
    }
});

const PORT = process.env.PORT || 8081;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`[TTYLabBox] Terminal Gateway listening on WS port ${PORT}`);
});
