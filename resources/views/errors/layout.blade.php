<!doctype html>
<html lang="en-IN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Vector7</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="flex min-h-screen items-center justify-center bg-ground p-6">
<main class="card-pad max-w-md text-center">
    <p class="text-sm font-bold text-teal">@yield('code')</p>
    <h1 class="mt-1 text-2xl font-bold">@yield('title')</h1>
    <p class="mt-2 text-ink-2">@yield('message')</p>
    <a href="{{ url('/') }}" class="btn-primary mt-6">Go to home</a>
</main>
</body>
</html>
