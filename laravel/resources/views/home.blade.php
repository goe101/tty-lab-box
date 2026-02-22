@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-6 md:p-12">
        <div class="mb-10 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight mb-4">Available Labs</h1>
            <p class="text-slate-400">Select a lab below to start a practical training scenario.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($labs as $lab)
                @php
                    $attempt = $latestAttempts[$lab->id] ?? null;
                    $isRunning = $attempt && $attempt->status === 'running';
                    $score = $attempt && $attempt->result ? $attempt->result->score : null;
                @endphp
                <div
                    class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden hover:border-blue-500 transition-colors duration-300 shadow-lg flex flex-col">
                    <div class="p-6 flex-grow">
                        <h2 class="text-2xl font-bold mb-2">{{ $lab->title }}</h2>
                        <p class="text-slate-400 text-sm mb-4 line-clamp-3">
                            {{ $lab->description ?? 'No description provided.' }}</p>
                        <div class="flex items-center space-x-4 text-xs font-semibold text-slate-500">
                            <span class="flex items-center"><svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg> {{ $lab->duration }} min</span>
                            @if($score !== null)
                                <span class="text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded">Score: {{ $score }}/100</span>
                            @endif
                        </div>
                    </div>
                    <div class="bg-slate-800/50 p-4 border-t border-slate-700 flex justify-end">
                        @if($isRunning)
                            <a href="{{ route('lab.show', $lab->slug) }}"
                                class="inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 shadow-md transition-all">
                                Resume
                            </a>
                        @else
                            <button onclick="startLab(this, {{ $lab->id }}, '{{ route('lab.show', $lab->slug) }}')"
                                class="inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-slate-900 bg-emerald-400 hover:bg-emerald-500 shadow-md transition-all">
                                Start Lab
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        async function startLab(btn, labId, redirectUrl) {
            btn.disabled = true;
            btn.innerText = 'Starting...';
            try {
                const res = await fetch('/api/attempts/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ lab_id: labId })
                });

                if (res.ok) {
                    window.location.href = redirectUrl;
                } else {
                    alert('Failed to start lab. Check permissions.');
                    btn.disabled = false;
                    btn.innerText = 'Start Lab';
                }
            } catch (e) {
                console.error(e);
                alert('Server error.');
                btn.disabled = false;
                btn.innerText = 'Start Lab';
            }
        }
    </script>
@endpush