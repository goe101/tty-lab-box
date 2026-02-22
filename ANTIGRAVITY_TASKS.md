# antigravity — Implementation Task File (TTYLabBox v1)

This document tells **antigravity** exactly what to build **and only what to build** for the TTYLabBox v1 codebase.

## 0) Core rule: do ONLY these tasks
✅ You will:
- Create/modify source code files in this repository to implement the required features.
- Add minimal README snippets needed to run the code **after** the owner installs prerequisites.
- Add small helper scripts (inside repo) if they are purely for the app (not OS provisioning).

❌ You will NOT:
- Provide or run OS-level setup steps (no VMware, no Rocky setup, no package installation, no `lxd init`, no firewall/SELinux work).
- Create complex infrastructure (Kubernetes, multi-node clusters, Terraform, Ansible).
- Add extra product features not listed here (billing, teams, analytics, email, etc.).
- Change the architecture: Laravel + Filament + Node WS Gateway + xterm.js + LXD VMs.

If anything is unclear, **assume the simplest implementation** that matches the blueprint without adding new scope.

---

## 1) Repository target structure

Create this structure (if it doesn't exist yet):

```
ttylabbox/
  laravel/
  terminal-gw/
  scripts/
  PROJECT_BLUEPRINT.md
  ANTIGRAVITY_TASKS.md
```

---

## 2) Required deliverables (what “done” means)

### A) Laravel app (PHP) — Student UI + APIs + Scheduler
Implement inside `laravel/`:

#### A1) Student UI (2 pages)
1) **Home page** `/`
- List published labs (cards)
- For logged-in user: show last result (score + timestamp) per lab if available
- Start button per lab:
  - If there is a running attempt → Resume (link to lab page)
  - Else → Start (calls API then redirects to lab page)

2) **Lab page** `/labs/{lab:slug}`
- 3-column layout:
  - Left: instructions steps with Next/Previous + Submit
  - Center: xterm.js terminal view
  - Right: timer + nodes list (srv1, srv2…) + status indicators
- Terminal should allow switching node (srv1/srv2). Switching should request a new terminal token and reconnect WS.

UI requirements:
- TailwindCSS, modern clean layout
- Mobile not required to be perfect, but should not break.

#### A2) Database schema + models + migrations
Create migrations/models for:
- users
- labs
- lab_steps
- lab_nodes
- attempts
- attempt_nodes
- results

Keep schema minimal and aligned with `PROJECT_BLUEPRINT.md`.

#### A3) REST APIs
Create controllers/routes for:

- `POST /api/attempts/start`
  - Creates attempt record (status=running, started_at, ends_at)
  - Creates LXD VMs based on lab topology via LxdManager service (see A4)
  - Creates attempt_nodes
  - Returns `{ attemptId, endsAt, nodes, wsToken }`

- `POST /api/attempts/submit`
  - Runs grading (see A5)
  - Stores result
  - Destroys VMs
  - Marks attempt submitted

- `POST /api/attempts/stop`
  - Destroys VMs
  - Marks attempt stopped

- `POST /api/terminal/token`
  - Validates user owns attempt and attempt is running
  - Returns `{ wsUrl, token }` where token is short-lived and bound to (userId, attemptId, nodeName)

Also implement an internal endpoint for gateway validation (choose one):
- Option 1: `POST /api/terminal/validate` (used by Node gateway)
- Option 2: JWT verification with shared secret (still keep a Laravel helper to mint tokens)

✅ Prefer simple JWT with `HS256` using `APP_KEY` or dedicated `TERMINAL_JWT_SECRET`.

#### A4) LXD Manager service (PHP)
Create `app/Services/LxdManager.php` using Symfony Process:
- `createVm(string $name, array $opts): void`
- `startVm(string $name): void`
- `exec(string $name, array $cmd, int $timeoutSec=60): ProcessResult`
- `deleteVm(string $name, bool $force=true): void`
- `getVmIp(string $name): ?string`
- `exists(string $name): bool`

Notes:
- Use LXD **VMs**, not containers (use `lxc launch ... --vm`).
- Keep images configurable: default something like `images:rockylinux/9` or similar placeholder.
  - Do NOT attempt to guarantee the image exists; owner will adjust.
- Use naming convention: `att_<uuid>__<node>`.

#### A5) Grading pipeline
For a given attempt:
- Determine grader source:
  - simplest v1: store grader script content in DB (`labs.grader_script`) OR file path in `scripts/`.
- Execute inside `srv1` (or lab-defined grader node):
  - `lxc exec <vm> -- /usr/local/bin/grade.sh`
- Expect JSON output only; parse and store into results.

Provide:
- A sample `grade.sh` template in repo (not lab-specific) to show contract.
- Robust error handling: if grading fails, store score=0 and error JSON.

#### A6) Scheduler (timeout enforcement)
Implement Laravel scheduler (Console Kernel):
- Every minute:
  - Find running attempts with ends_at < now
  - Grade + store + destroy VMs
  - Mark attempt expired
- Ensure idempotency: multiple runs should not crash.

#### A7) Filament admin panel (local)
Install/configure Filament inside Laravel project and create resources:
- Labs (title, slug, duration, published, description)
- Steps (lab relation, order, title, markdown)
- Nodes (lab relation, node_name, image, cpu, mem_mb, disk_gb)
- Script fields (grader, optional provision)

Keep it simple:
- Basic CRUD pages
- Only admin users can access (use a boolean `users.is_admin` for v1).

---

### B) Terminal Gateway (Node.js) — WebSocket ↔ PTY ↔ LXD
Implement inside `terminal-gw/`:

#### B1) WebSocket server
- Listens on `0.0.0.0:8081`
- WS endpoint: `/ws?token=...&attemptId=...&node=srv1`
- Validates token (JWT or Laravel validate endpoint)
- On success:
  - Determine LXD VM name (from token claims OR call Laravel to fetch mapping)
  - Spawn PTY session using node-pty:
    - `lxc exec <vm> -- bash -lc "exec bash"`
  - Pipe I/O:
    - client `input` -> pty.write
    - pty `data` -> client `output`

#### B2) Resize support
- Handle `resize` messages and resize PTY accordingly.

#### B3) Security basics
- Reject if:
  - token invalid/expired
  - node not allowed
- Hard limit concurrent sessions per user (simple: 1 per attempt node).

#### B4) Minimal config
- `.env.example` with:
  - `LARAVEL_BASE_URL=http://127.0.0.1:8080`
  - `TERMINAL_JWT_SECRET=...` (if used)
- `npm scripts`:
  - `start`
  - `dev` (optional)

---

### C) Frontend Terminal Integration (xterm.js)
Inside Laravel UI:

- Add xterm.js and xterm-addon-fit.
- Implement a small client module:
  - connect WS with token
  - render terminal
  - handle input/resize/output protocol
  - reconnect on node switch
- Use FitAddon to auto-fit on container resize.

---

## 3) Constraints (must follow)
- Keep code minimal and readable.
- Prefer built-in Laravel conventions.
- No unnecessary dependencies.
- No Docker requirement (owner runs on Rocky directly).
- No external services.

---

## 4) Configuration keys (must exist)
Laravel `.env.example` additions:
- `TERMINAL_WS_URL=ws://127.0.0.1:8081/ws`
- `TERMINAL_JWT_SECRET=change-me`
- `LXD_BIN=lxc` (optional override)
- `LAB_DEFAULT_IMAGE=images:rockylinux/9` (placeholder)

Node `.env.example`:
- `PORT=8081`
- `TERMINAL_JWT_SECRET=change-me`
- `LXD_BIN=lxc`
- `ALLOWED_ORIGINS=http://127.0.0.1:8080`

---

## 5) Acceptance checklist
Consider the task complete when:

1) `php artisan migrate` works with fresh DB.
2) Admin can create/publish a lab + steps + nodes via Filament.
3) Student home lists labs and can start an attempt.
4) Starting attempt creates LXD VMs (calls `lxc ... --vm`) and stores attempt_nodes.
5) Lab page shows instructions, timer, node list, and an xterm terminal connected via WS gateway.
6) Switching node reconnects the terminal to the selected node.
7) Submit triggers grading, stores JSON result, shows it, and destroys VMs.
8) Timeout job grades + cleans up automatically.

---

## 6) What NOT to build
- Payments, subscriptions, roles beyond is_admin
- Multi-host orchestration
- Fancy UI animation systems
- Complex RBAC
- Lab content authoring beyond CRUD fields
- Anything that requires external accounts/services

---

## 7) Notes for simplest implementation
- If unsure about lab images or cloud-init: keep it configurable and provide sensible defaults.
- Use placeholders where environment varies; do not over-engineer.

**Build exactly this, nothing more.**
