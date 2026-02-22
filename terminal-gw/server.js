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

        const ptyProcess = pty.spawn(LXD_BIN, ['exec', instanceName, '--', 'bash', '-lc', 'exec bash'], {
            name: 'xterm-color',
            cols: 80,
            rows: 24,
            cwd: process.env.HOME,
            env: process.env
        });

        activeSessions.set(sessionKey, { ws, pty: ptyProcess });

        ptyProcess.onData((data) => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(data);
            }
        });

        ptyProcess.onExit(({ exitCode }) => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(`\r\n\x1b[33m[Gateway] Process exited (code ${exitCode})\x1b[0m\r\n`);
                ws.close();
            }
            activeSessions.delete(sessionKey);
        });

        ws.on('message', (message) => {
            if (typeof message === 'string') {
                try {
                    const msg = JSON.parse(message);
                    if (msg.type === 'resize' && msg.cols && msg.rows) {
                        try { ptyProcess.resize(msg.cols, msg.rows); } catch (e) { }
                    } else if (msg.type === 'input' && msg.data) {
                        ptyProcess.write(msg.data);
                    }
                } catch (e) {
                    ptyProcess.write(message);
                }
            } else {
                ptyProcess.write(message.toString());
            }
        });

        ws.on('close', () => {
            try { ptyProcess.kill(); } catch (e) { }
            if (activeSessions.get(sessionKey)?.ws === ws) {
                activeSessions.delete(sessionKey);
            }
        });

    } catch (err) {
        ws.send('\r\n\x1b[31m[Gateway] Internal Server Error.\x1b[0m\r\n');
        ws.close();
    }
});

const PORT = process.env.PORT || 8081;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`[TTYLabBox] Terminal Gateway listening on WS port ${PORT}`);
});
