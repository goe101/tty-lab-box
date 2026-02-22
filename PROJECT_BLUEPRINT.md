# TTYLabBox — Canonical Project Blueprint (v1)

> Goal: A **simple-to-run** RHCSA-style lab platform on **Rocky Linux 10** inside **VMware**.
> - **Backend:** Laravel (PHP)
> - **Admin panel:** Filament (local, no external SaaS)
> - **Terminal:** xterm.js in browser + Node.js WebSocket Terminal Gateway
> - **Lab runtime:** LXD **VMs** (systemd works normally)
> - **Autograding:** per-lab `grade.sh` → JSON result
> - **Lifecycle:** Start → Run → Submit/Timeout → Grade → Store Result → Destroy LXD VMs

---

## 1) System Overview

### Components (all on the Rocky 10 server VM)
1. **Laravel App (PHP)**
   - Student UI (2 pages)
   - REST API (attempts, labs, results, terminal tokens)
   - Admin panel via Filament
   - Scheduler jobs for timeout + cleanup

2. **Terminal Gateway (Node.js)**
   - WebSocket server for terminal sessions only
   - Spawns PTY sessions into LXD VMs and pipes I/O to/from the browser

3. **LXD**
   - Runs labs as **VMs** (not containers) so `systemd` and RHCSA tasks behave naturally
   - Each attempt creates 1..N VMs based on lab topology

---

## 2) User Experience

### Student Flow
1. Open **Home** page: see list of labs + previous results.
2. Click a lab → **Lab page**:
   - **Left:** Instructions (tabs/steps + Next/Previous + Submit)
   - **Center:** Terminal (xterm.js)
   - **Right:** Timer + node list (srv1/srv2/...) + status
3. On **Submit** (or when timer ends):
   - Run grader script
   - Store result and show it to the student
   - Destroy all LXD VMs for the attempt

### Admin Flow (Owner)
- Use Filament admin panel to:
  - Create labs
  - Define steps/instructions (Markdown)
  - Define topology (single/multi server)
  - Configure duration
  - Add provision script (optional) and grading script
  - Publish/unpublish labs

---

## 3) Ports & URLs

- **Laravel (HTTP):** `http://<rocky>:8080`
- **Terminal Gateway (WebSocket):** `ws://<rocky>:8081/ws`

> Optional later: put Nginx in front for HTTPS/WSS.

---

## 4) Repository Layout (single repo)

```
ttylabbox/
  laravel/          # Laravel app
  terminal-gw/      # Node WS terminal gateway
  scripts/
    lxd/
      profiles/     # LXD profiles (optional)
      cloud-init/   # cloud-init templates (optional)
  PROJECT_BLUEPRINT.md
```

---

## 5) Database Schema (Minimal v1)

### Tables
- `users`
- `labs`
  - `id, title, slug, description, duration_minutes, published, created_at, updated_at`
- `lab_steps`
  - `id, lab_id, step_order, title, content_markdown`
- `lab_nodes`
  - `id, lab_id, node_name (srv1/srv2...), image, cpu, mem_mb, disk_gb, created_at`
- `attempts`
  - `id, user_id, lab_id, status (running/submitted/expired/stopped), started_at, ends_at, submitted_at`
- `attempt_nodes`
  - `id, attempt_id, node_name, lxd_name, ip, status`
- `results`
  - `id, attempt_id, score, result_json, graded_at`

---

## 6) LXD Naming Conventions (for easy cleanup)

- Attempt ID (UUID): `att_<uuid>`
- LXD VM names:
  - `att_<uuid>__srv1`
  - `att_<uuid>__srv2`
  - ...

**Rule:** Anything that starts with `att_<uuid>` belongs to one attempt and must be deleted on cleanup.

---

## 7) API Contract (Laravel)

### 7.1 Start Attempt
**POST** `/api/attempts/start`

**Body**
```json
{ "labId": "linux-basics-1" }
```

**Response**
```json
{
  "attemptId": "att_2c9b9d2e-7a8d-4b2d-9a1c-2c6b4c1b7f7a",
  "endsAt": "2026-02-22T14:00:00Z",
  "nodes": [{ "name": "srv1" }, { "name": "srv2" }],
  "wsToken": "<short-lived-token>"
}
```

### 7.2 Submit Attempt
**POST** `/api/attempts/submit`

**Body**
```json
{ "attemptId": "att_..." }
```

**Response**
```json
{ "result": { "score": 80, "checks": [], "summary": "..." } }
```

### 7.3 Stop Attempt (manual cancel)
**POST** `/api/attempts/stop`

**Body**
```json
{ "attemptId": "att_..." }
```

**Response**
```json
{ "ok": true }
```

### 7.4 Terminal Token (per node)
**POST** `/api/terminal/token`

**Body**
```json
{ "attemptId": "att_...", "nodeName": "srv1" }
```

**Response**
```json
{
  "wsUrl": "ws://<rocky>:8081/ws",
  "token": "<short-lived-node-token>"
}
```

---

## 8) WebSocket Protocol (Browser ↔ Node Gateway)

### Connect URL
`ws://<rocky>:8081/ws?token=...&attemptId=...&node=srv1`

### Client → Server messages
- Input:
```json
{ "type": "input", "data": "ls -la\n" }
```
- Resize:
```json
{ "type": "resize", "cols": 120, "rows": 34 }
```
- Ping:
```json
{ "type": "ping" }
```

### Server → Client messages
- Output:
```json
{ "type": "output", "data": "..." }
```
- Exit:
```json
{ "type": "exit", "code": 0 }
```
- Error:
```json
{ "type": "error", "message": "..." }
```
- Pong:
```json
{ "type": "pong" }
```

---

## 9) Terminal Gateway Implementation Notes (Node)

### Responsibilities
- Accept WS connection
- Validate `token` (recommended: call Laravel internal endpoint OR verify JWT with shared secret)
- Map `(attemptId, nodeName)` → `lxd_vm_name` from Laravel or shared cache
- Spawn interactive shell inside VM:
  - Preferred for v1:
    - `lxc exec <vm> -- bash -lc "exec bash"`
- Pipe:
  - WS `input` → PTY stdin
  - PTY stdout/stderr → WS `output`
- Handle resize:
  - PTY resize to cols/rows

### Token validation (recommended)
- Laravel issues short-lived token (e.g., 2 minutes) bound to:
  - userId, attemptId, nodeName, expiresAt
- Node rejects connections if:
  - attempt is not `running`
  - token invalid/expired
  - node not in attempt

---

## 10) Autograding Contract

### When grading runs
- On **Submit**
- On **Timeout** (scheduled job)

### How grading runs
- Execute the lab’s grader script inside a designated node (usually `srv1`):
  - `lxc exec <vm> -- /usr/local/bin/grade.sh`

### Required output format
`grade.sh` must output **JSON only** to stdout, e.g.:

```json
{
  "score": 80,
  "checks": [
    { "name": "service_enabled", "pass": true, "points": 20 },
    { "name": "user_created", "pass": false, "points": 0, "hint": "Create user 'bob'" }
  ],
  "summary": "Good progress"
}
```

### Storage
- Laravel stores JSON into `results.result_json`
- `score` into `results.score`

---

## 11) Cleanup Rules (Always)

Cleanup triggers:
- After grading (Submit/Timeout)
- After manual Stop

Cleanup must:
- Destroy all LXD VMs for that attempt
- Mark attempt `submitted/expired/stopped`
- Ensure no orphan VMs remain

---

## 12) Scheduler (Timeout Enforcement)

Laravel scheduler runs every minute:
- Find `attempts` where:
  - `status = running`
  - `ends_at < now()`
- For each:
  1. grade attempt
  2. store result
  3. destroy VMs
  4. set status = `expired`

---

## 13) Filament Admin Panel (Local Only)

Filament is a **Laravel package** (Composer dependency) and runs **locally** on the same server:
- No external SaaS required
- Provides CRUD UIs for:
  - Labs
  - Steps (Markdown)
  - Nodes/Topology
  - Scripts (provision + grade)
  - Duration + publish flag

---

## 14) Install & Run (Developer-friendly)

### Prerequisites on Rocky 10
- PHP + Composer
- Node.js + npm
- LXD installed and initialized

### One-time LXD init
```bash
lxd init
```

### Run Laravel
```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host 0.0.0.0 --port 8080
```

### Run Terminal Gateway
```bash
cd terminal-gw
npm install
npm run start  # listens on 0.0.0.0:8081
```

> Later: create systemd services for both so they start automatically.

---

## 15) Milestones (v1)

### Milestone 0 — UI Skeleton
- Home page (labs + results)
- Lab page layout (3 columns)
- Auth (simple)

### Milestone 1 — Terminal (xterm.js + WS)
- Node terminal gateway working
- One VM (`srv1`) per attempt
- Terminal connects via WS token

### Milestone 2 — LXD Topology (Multi-server)
- Lab topology supports 2–3 nodes
- UI can switch terminal between nodes

### Milestone 3 — Timer + Timeout
- UI timer
- Backend timeout enforcement + cleanup

### Milestone 4 — Autograding + Results
- `grade.sh` per lab
- Result JSON stored & displayed
- Cleanup after grading

### Milestone 5 — Admin Panel
- Filament resources for labs/steps/nodes/scripts
- Publish workflow

---

## 16) antigravity Task Breakdown (Execution Plan)

1. Laravel project bootstrap + pages + Tailwind UI
2. DB migrations + models for labs/attempts/results
3. LXD manager service in PHP (create/start/exec/destroy/getIP)
4. Node terminal gateway (WS ↔ PTY ↔ LXD)
5. xterm.js integration + resize/input/output protocol
6. Submit/Timeout → grading pipeline
7. Filament admin panel (CRUD everything required)

---

## 17) Non-goals (v1)

- Multi-user tenancy across multiple Rocky servers
- Kubernetes / distributed scheduling
- Complex networking topologies beyond basic LXD bridge
- Full-blown RBAC beyond admin/user

---

## 18) Future Enhancements (v2+)

- HTTPS/WSS + reverse proxy
- Per-attempt snapshots / resume attempts
- Pluggable graders + detailed analytics
- Templates for RHCSA “Exam Simulation Mode”
- Export/import labs as YAML

---
**This file is the single source of truth.**
If implementation differs from this blueprint, update this file first.
