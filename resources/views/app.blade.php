<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar { width: 240px; min-height: 100vh; background: #1f2937; }
        .sidebar .nav-link { color: #cbd5e1; border-radius: .5rem; padding: .6rem .9rem; }
        .sidebar .nav-link:hover { background: #374151; color: #fff; }
        .sidebar .nav-link.active { background: #2563eb; color: #fff; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .table > :not(caption) > * > * { padding: .85rem .75rem; }
        .toast-stack { position: fixed; top: 1rem; right: 1rem; z-index: 2000; width: 340px; }
        .fade-enter-active, .fade-leave-active { transition: opacity .2s, transform .2s; }
        .fade-enter-from, .fade-leave-to { opacity: 0; transform: translateX(20px); }
    </style>
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app"></div>
</body>
</html>
