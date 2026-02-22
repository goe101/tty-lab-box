@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <style>
        .markdown-body h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .markdown-body h2 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .markdown-body p {
            margin-bottom: 1rem;
        }

        .markdown-body pre {
            background: #1e293b;
            padding: 1rem;
            border-radius: 0.50rem;
            overflow-x: auto;
            margin-bottom: 1rem;
        }

        .markdown-body code {
            background: #1e293b;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }

        .markdown-body ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
@endpush

@section('content')
    @if(!$activeAttempt)
        <div class="flex-grow flex items-center justify-center p-6">
            <div class="bg-slate-800 p-8 rounded-xl shadow-lg text-center max-w-md w-full">
                <h2 class="text-2xl font-bold mb-4">No Active Attempt</h2>
                <p class="text-slate-400 mb-6">You need to start this lab from the dashboard.</p>
                <a href="{{ route('home') }}"
                    class="inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-slate-900 bg-emerald-400 hover:bg-emerald-500">Go
                    to Dashboard</a>
            </div>
        </div>
    @else
        <div class="flex-grow flex flex-col md:flex-row h-[calc(100vh-73px)] overflow-hidden">

            <!-- Left: Instructions -->
            <div class="w-full md:w-1/4 bg-slate-800 border-r border-slate-700 flex flex-col shrink-0">
                <div class="p-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/80 sticky top-0">
                    <h2 class="font-bold text-lg">Instructions</h2>
                    <div class="text-xs text-slate-400">Step <span id="currentStepNum">1</span> of {{ $lab->steps->count() }}
                    </div>
                </div>
                <div class="flex-grow overflow-y-auto p-4 markdown-body text-slate-300 text-sm" id="markdownContainer">
                    <!-- Content injected via JS -->
                </div>
                <div class="p-4 border-t border-slate-700 bg-slate-800/80 shrink-0 space-y-3">
                    <div class="flex space-x-2">
                        <button id="btnPrev" onclick="changeStep(-1)"
                            class="flex-1 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded disabled:opacity-50 transition-colors">Previous</button>
                        <button id="btnNext" onclick="changeStep(1)"
                            class="flex-1 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded disabled:opacity-50 transition-colors">Next</button>
                    </div>
                    <button onclick="submitLab()" id="btnSubmit"
                        class="w-full py-2 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-slate-900 font-bold rounded transition-colors shadow-lg">Submit
                        Lab</button>
                </div>
            </div>

            <!-- Center: Terminal -->
            <div class="w-full md:w-1/2 flex flex-col bg-[#000000] relative shrink-0">
                <div
                    class="flex justify-between items-center px-4 py-2 bg-slate-900 border-b border-slate-700 text-sm shrink-0 shadow-md z-10">
                    <div class="font-mono text-emerald-400 flex items-center">
                        <span
                            class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-pulse shadow-[0_0_5px_theme(colors.emerald.400)]"></span>
                        Terminal - <span id="activeNodeLabel" class="ml-1 font-bold">srv1</span>
                    </div>
                    <div class="flex space-x-2" id="nodeSwitcher">
                        <!-- Nodes injected via JS, fallback for visual symmetry if needed -->
                    </div>
                </div>
                <div id="terminal" class="flex-grow p-4 overflow-hidden relative"></div>
                <!-- Overlay for loading -->
                <div id="terminalOverlay"
                    class="absolute inset-0 bg-black/80 flex items-center justify-center z-10 hidden backdrop-blur-sm">
                    <div class="flex flex-col items-center">
                        <svg class="animate-spin h-8 w-8 text-emerald-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <div class="text-emerald-400 font-mono text-sm tracking-widest">CONNECTING_WS...</div>
                    </div>
                </div>
            </div>

            <!-- Right: Status / Nodes / Timer -->
            <div class="w-full md:w-1/4 bg-slate-800 border-l border-slate-700 p-4 flex flex-col shrink-0 overflow-y-auto">
                <h2 class="font-bold text-lg mb-4 text-slate-200">Session Monitor</h2>

                <div
                    class="bg-slate-900 rounded-xl p-6 mb-6 shadow-inner border border-slate-700 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-blue-500/5 blur-xl"></div>
                    <div class="relative">
                        <div class="text-slate-400 text-xs uppercase font-bold tracking-wider mb-2">Time Remaining</div>
                        <div id="timerDisplay" class="text-4xl font-mono text-emerald-400 font-bold drop-shadow-md">--:--:--
                        </div>
                    </div>
                </div>

                <div class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-widest flex items-center"><svg
                        class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01">
                        </path>
                    </svg> Nodes</div>
                <div class="space-y-3">
                    @foreach($lab->nodes as $node)
                        <div class="p-3 bg-slate-700 border border-slate-600 rounded-lg text-sm flex justify-between items-center cursor-pointer hover:bg-slate-600 hover:border-blue-500 transition-all shadow-sm group"
                            onclick="switchNode('{{ $node->node_name }}')">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-slate-400 mr-2 group-hover:text-blue-400 transition-colors" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z">
                                    </path>
                                </svg>
                                <span class="font-mono font-bold text-slate-200">{{ $node->node_name }}</span>
                            </div>
                            <span
                                class="text-emerald-400 text-xs flex items-center font-medium bg-emerald-400/10 px-2 py-0.5 rounded-full">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 shadow-[0_0_5px_theme(colors.emerald.400)] block"></span>
                                UP
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-auto pt-8">
                    <button onclick="stopLab()" id="btnStop"
                        class="w-full text-sm font-medium text-red-400 hover:text-white hover:bg-red-500 border border-red-500/50 py-2.5 rounded-lg transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"></path>
                        </svg>
                        Force Stop Attempt
                    </button>
                </div>
            </div>

        </div>
    @endif

    <!-- Submission Result Modal -->
    <div id="resultModal"
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 hidden backdrop-blur-sm transition-opacity">
        <div
            class="bg-slate-800 p-8 rounded-2xl max-w-md w-full border border-slate-700 shadow-2xl transform scale-100 transition-transform">
            <div class="flex justify-center mb-6">
                <div class="w-16 h-16 bg-emerald-400/10 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4L19 7"></path>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold mb-2 text-center text-white" id="modalTitle">Evaluation Complete</h2>
            <div class="text-5xl font-extrabold my-6 text-center bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400"
                id="scoreDisplay"></div>
            <div id="modalError"
                class="text-red-400 text-sm mt-2 hidden text-center bg-red-400/10 border border-red-500/20 p-3 rounded-lg flex items-start">
                <svg class="w-5 h-5 mr-2 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span id="modalErrorText" class="text-left font-mono"></span>
            </div>
            <p class="text-slate-400 mt-6 text-center text-sm font-medium">Virtual machines have been cleanly destroyed.</p>
            <button onclick="window.location.href='/';"
                class="mt-6 w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 transition-all focus:ring-4 focus:ring-blue-500/50">Return
                to Dashboard</button>
        </div>
    </div>
@endsection

@push('scripts')
    @if($activeAttempt)
        <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
        <script>
            // Lab Data
            const labSteps = {!! json_encode($lab->steps) !!};
            const attemptId = {{ $activeAttempt->id }};
            const nodes = {!! json_encode($lab->nodes->pluck('node_name')) !!};
            let currentStep = 0;

            // Timer
            const endsAt = new Date("{{ $activeAttempt->ends_at->toISOString() }}").getTime();
            setInterval(() => {
                const now = new Date().getTime();
                const dist = endsAt - now;
                if (dist < 0) {
                    document.getElementById('timerDisplay').innerText = "EXPIRED";
                    document.getElementById('timerDisplay').classList.replace('text-emerald-400', 'text-red-500');
                    return;
                }
                const h = Math.floor((dist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const m = Math.floor((dist % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((dist % (1000 * 60)) / 1000);
                document.getElementById('timerDisplay').innerText =
                    String(h).padStart(2, '0') + ":" + String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
            }, 1000);

            // Markdown Steps
            function renderStep() {
                if (labSteps.length === 0) {
                    document.getElementById('markdownContainer').innerHTML = "<p>No steps defined.</p>";
                    document.getElementById('btnNext').disabled = true;
                    document.getElementById('btnPrev').disabled = true;
                    return;
                }
                const step = labSteps[currentStep];
                document.getElementById('currentStepNum').innerText = currentStep + 1;
                document.getElementById('markdownContainer').innerHTML = marked.parse(step.markdown);
                document.getElementById('btnNext').disabled = (currentStep === labSteps.length - 1);
                document.getElementById('btnPrev').disabled = (currentStep === 0);
            }

            window.changeStep = function (delta) {
                currentStep += delta;
                renderStep();
            }

            renderStep();

            // Terminal
            let term = new Terminal({
                theme: { background: '#000000', foreground: '#f8fafc', cursor: '#10b981', selectionBackground: '#334155' },
                fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                fontSize: 14,
                cursorBlink: true,
                padding: 10
            });
            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(document.getElementById('terminal'));

            // Slight delay to ensure DOM is ready for fit
            setTimeout(() => { fitAddon.fit(); }, 50);
            window.addEventListener('resize', () => fitAddon.fit());

            let ws = null;
            let activeNode = nodes[0] || 'srv1';

            async function switchNode(nodeName) {
                if (activeNode === nodeName && ws && ws.readyState === WebSocket.OPEN) return;
                activeNode = nodeName;
                document.getElementById('activeNodeLabel').innerText = nodeName;
                document.getElementById('terminalOverlay').classList.remove('hidden');

                if (ws) {
                    ws.close();
                }

                term.clear();

                try {
                    const res = await fetch('/api/terminal/token', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ attempt_id: attemptId, node_name: activeNode })
                    });

                    if (!res.ok) {
                        term.writeln('\\r\\n\\x1b[31mFailed to get terminal token.\\x1b[0m');
                        document.getElementById('terminalOverlay').classList.add('hidden');
                        return;
                    }

                    const data = await res.json();
                    const wsUrl = `${data.wsUrl}?token=${data.token}&attemptId=${attemptId}&node=${activeNode}`;

                    ws = new WebSocket(wsUrl);
                    ws.onopen = () => {
                        document.getElementById('terminalOverlay').classList.add('hidden');
                        term.writeln(`\\r\\n\\x1b[32m[TTYLabBox] Connected to ${activeNode} successfully.\\x1b[0m\\r\\n`);
                        // Send initial size
                        ws.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
                    };
                    ws.onmessage = (e) => {
                        if (e.data instanceof Blob) {
                            const reader = new FileReader();
                            reader.onload = () => { term.write(reader.result); };
                            reader.readAsText(e.data);
                        } else {
                            term.write(e.data);
                        }
                    };
                    ws.onerror = () => term.writeln('\\r\\n\\x1b[31m[TTYLabBox] WebSocket error.\\x1b[0m');
                    ws.onclose = () => {
                        term.writeln('\\r\\n\\x1b[33m[TTYLabBox] Connection closed.\\x1b[0m');
                        document.getElementById('terminalOverlay').classList.add('hidden');
                    };

                    term.onData(data => {
                        if (ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({ type: 'input', data: data }));
                        }
                    });

                    term.onResize(size => {
                        if (ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({ type: 'resize', cols: size.cols, rows: size.rows }));
                        }
                    });

                } catch (e) {
                    term.writeln('\\r\\n\\x1b[31m[TTYLabBox] Could not connect to terminal gateway.\\x1b[0m');
                    document.getElementById('terminalOverlay').classList.add('hidden');
                }
            }

            // Attempt to connect immediately to first node
            setTimeout(() => switchNode(activeNode), 200);

            // Submission / Control
            window.submitLab = async function () {
                const btn = document.getElementById('btnSubmit');
                btn.disabled = true;
                btn.innerText = 'Evaluating Sandbox...';
                btn.classList.add('animate-pulse');

                try {
                    const res = await fetch('/api/attempts/submit', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ attempt_id: attemptId })
                    });
                    const data = await res.json();

                    document.getElementById('resultModal').classList.remove('hidden');

                    if (data.score !== undefined && data.score !== null) {
                        document.getElementById('scoreDisplay').innerText = `${data.score} / 100`;
                    } else {
                        document.getElementById('scoreDisplay').innerText = `Error`;
                        document.getElementById('scoreDisplay').classList.replace('from-emerald-400', 'from-red-400');
                        document.getElementById('scoreDisplay').classList.replace('to-teal-400', 'to-red-600');
                    }

                    if (data.error) {
                        const errDiv = document.getElementById('modalError');
                        errDiv.classList.remove('hidden');
                        const errTextDiv = document.getElementById('modalErrorText');
                        errTextDiv.innerText = typeof data.error === 'string' ? data.error : JSON.stringify(data.error, null, 2);
                    }
                } catch (e) {
                    alert('Failed to submit lab. Check console for details.');
                    btn.disabled = false;
                    btn.innerText = 'Submit Lab';
                    btn.classList.remove('animate-pulse');
                }
            }

            window.stopLab = async function () {
                if (!confirm('Are you sure you want to completely stop this attempt? All VMs will be deleted instantly.')) return;
                const btn = document.getElementById('btnStop');
                btn.disabled = true;
                btn.innerText = 'Stopping Virtual Environment...';

                await fetch('/api/attempts/stop', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ attempt_id: attemptId })
                });
                window.location.href = '/';
            }
        </script>
    @endif
@endpush