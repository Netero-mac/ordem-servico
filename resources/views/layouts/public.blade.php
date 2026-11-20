<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <main class="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
