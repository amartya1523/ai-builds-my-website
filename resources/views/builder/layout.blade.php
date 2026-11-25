<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AI Builds My Website')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v=3">
</head>
<body>
<header class="topbar">
    <a href="{{ route('sites.index') }}" class="brand">
        <span class="brand-mark">AI</span>
        <span>Builds My Website</span>
    </a>
    <nav class="topnav">
        <span class="pill pill-muted">AI: {{ app(\App\Services\Ai\AiClient::class)->configured() ? app(\App\Services\Ai\AiClient::class)->name().' · '.app(\App\Services\Ai\AiClient::class)->model() : 'off (template mode)' }}</span>
    </nav>
</header>

<main class="container">
    @if (session('status'))
        <div class="flash flash-ok">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="flash flash-err">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash flash-err">{{ $errors->first() }}</div>
    @endif

    @yield('content')
</main>

<div id="busy" class="busy" hidden>
    <div class="busy-card"><div class="spinner"></div><div id="busy-text">Reading your sources…</div></div>
</div>
<script>
document.querySelectorAll('form[data-busy]').forEach(f => f.addEventListener('submit', () => {
    document.getElementById('busy-text').textContent = f.dataset.busy;
    document.getElementById('busy').hidden = false;
}));
</script>
</body>
</html>
