<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Triage Decision Support')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen">

    <header class="bg-navy text-white">
        <div class="max-w-3xl mx-auto px-4 py-5">
            <h1 class="font-heading text-xl font-semibold flex items-center gap-2">
                <span class="text-aqua-light">🚑</span> AI-Assisted Triage Priority
                <span class="text-aqua-light">Decision Support Tool</span>
            </h1>
        </div>
    </header>

    <div class="bg-amber-100 border-b border-amber-300 text-amber-900 text-sm">
        <div class="max-w-3xl mx-auto px-4 py-2.5">
            <strong>Decision support only.</strong> This tool suggests an ATS category and confidence score —
            it never auto-assigns a category. The triage nurse retains full clinical decision authority at all times.
        </div>
    </div>

    <main class="max-w-3xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="max-w-3xl mx-auto px-4 pb-8 text-center">
        <a href="{{ route('train.index') }}" class="text-xs text-gray-400 hover:text-gray-600">Model Training (admin)</a>
    </footer>

</body>
</html>
