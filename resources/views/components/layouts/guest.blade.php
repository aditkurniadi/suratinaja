@props(['title' => 'Masuk'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'SuratinAja') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        (function () {
            const mode = localStorage.getItem('theme') || 'light';
            const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body x-data class="min-h-screen bg-background text-foreground font-sans flex flex-col justify-center items-center p-4">
    <div class="fixed top-4 right-4">
        <button
            type="button"
            @click="$store.theme.toggle()"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            aria-label="Ganti Tema Light/Dark"
        >
            <x-lucide-sun class="size-4 dark:hidden" />
            <x-lucide-moon class="size-4 hidden dark:block" />
        </button>
    </div>

    <div class="w-full max-w-sm space-y-6">
        <div class="flex flex-col items-center text-center space-y-2">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm shadow-xs">
                SA
            </div>
            <h1 class="text-xl font-semibold tracking-tight">SuratinAja</h1>
            <p class="text-xs text-muted-foreground">
                Manajemen Persuratan Multi-Workspace Organisasi
            </p>
        </div>

        <x-ui.card>
            <x-ui.card-content class="pt-6">
                {{ $slot }}
            </x-ui.card-content>
        </x-ui.card>
    </div>

    @livewireScripts(['navigate' => false])
</body>
</html>
