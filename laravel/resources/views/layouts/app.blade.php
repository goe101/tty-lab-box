<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTYLabBox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    @stack('styles')
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col font-sans">
    <header class="bg-slate-800 border-b border-slate-700 p-4 shrink-0">
        <div class="container mx-auto flex justify-between items-center">
            <a href="{{ route('home') }}"
                class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">
                TTYLabBox
            </a>
            <div class="space-x-4 text-sm text-slate-400">
                <span>Student Dashboard</span>
            </div>
        </div>
    </header>

    <main class="flex-grow flex flex-col relative">
        @yield('content')
    </main>

    @stack('scripts')
</body>

</html>