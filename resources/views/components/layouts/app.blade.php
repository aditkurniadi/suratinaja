@props([
    'title' => 'Dashboard',
    'breadcrumbs' => [],
    'backUrl' => null,
    'fullWidth' => false,
])

@php
    $user = auth()->user();
    $activeWorkspace = app(\App\Support\CurrentWorkspace::class)->get() ?? $user?->activeWorkspace();
    $role = $activeWorkspace && $user ? $activeWorkspace->roleOf($user) : null;
    $canWrite = $user?->can('surat_keluar.create') || ($role?->canWrite() ?? false);
    $isAdmin = (bool) $user?->is_super_admin || ($role?->isAdmin() ?? false);
    $canSuratMasuk = $user?->can('surat_masuk.view') || $user?->can('surat_masuk.preview_download') || ($role !== null);
    $canManageNumbering = $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
    $canViewMembers = (bool) $user?->is_super_admin || $user?->can('member.view') || $user?->can('role.view') || ($role?->isAdmin() ?? false);
    $canViewBulk = $user?->can('bulk.preview_download') || $canWrite;
    $unreadCount = $user ? \Illuminate\Support\Facades\Cache::store('array')->remember('unread_notif_' . $user->id, 60, fn () => $user->unreadNotifications()->count()) : 0;

    $navGroups = [
        [
            'label' => 'Persuratan',
            'items' => array_values(array_filter([
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'show' => true],
                ['label' => 'Surat Masuk', 'route' => 'surat-masuk.index', 'icon' => 'inbox', 'show' => $canSuratMasuk],
                ['label' => 'Daftar Surat', 'route' => 'letters.index', 'icon' => 'files', 'show' => $user?->can('surat_keluar.view') || $user?->can('surat_keluar.preview_download') || true],
                ['label' => 'Kalender', 'route' => 'calendar.index', 'icon' => 'calendar', 'show' => true],
                ['label' => 'Buat Surat', 'route' => 'letters.create', 'icon' => 'file-plus-2', 'show' => $canWrite],
                ['label' => 'Bulk Generate', 'route' => 'bulk.index', 'icon' => 'layers', 'show' => $canViewBulk],
            ], fn ($i) => $i['show'])),
        ],
        [
            'label' => 'Dokumen & Template',
            'items' => array_values(array_filter([
                ['label' => 'Library Template', 'route' => 'templates.index', 'icon' => 'file-code-2', 'show' => $user?->can('template.view') ?? true],
            ], fn ($i) => $i['show'])),
        ],
        [
            'label' => 'Sistem & Super Admin',
            'items' => array_values(array_filter([
                ['label' => 'Panel Super Admin', 'route' => 'superadmin.index', 'icon' => 'shield-check', 'show' => (bool) $user?->is_super_admin],
                ['label' => 'Semua Workspace', 'route' => 'admin.workspaces.index', 'icon' => 'building-2', 'show' => (bool) $user?->is_super_admin],
                ['label' => 'Template Global', 'route' => 'admin.global-templates', 'icon' => 'globe', 'show' => (bool) $user?->is_super_admin],
            ], fn ($i) => $i['show'])),
        ],
        [
            'label' => 'Konfigurasi Workspace',
            'items' => array_values(array_filter([
                ['label' => 'Pengaturan Workspace', 'route' => 'settings.workspace', 'icon' => 'settings', 'show' => $isAdmin],
                ['label' => 'Builder Kop Surat', 'route' => 'settings.kop', 'icon' => 'stamp', 'show' => $isAdmin],
                ['label' => 'Pola Penomoran', 'route' => 'settings.numbering', 'icon' => 'hash', 'show' => $canManageNumbering],
                ['label' => 'Jenis Surat', 'route' => 'settings.letter-types', 'icon' => 'tag', 'show' => $canManageNumbering],
                ['label' => 'Anggota & Akses', 'route' => 'settings.members', 'icon' => 'users', 'show' => $canViewMembers],
            ], fn ($i) => $i['show'])),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'SuratinAja') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

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
<body x-data class="min-h-screen bg-background text-foreground font-sans">
    <div class="flex min-h-screen w-full">
        {{-- Desktop Sidebar (Dokploy & Notion style) --}}
        <aside
            class="hidden lg:flex flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-[width] duration-200 shrink-0 h-screen h-dvh sticky top-0 overflow-hidden"
            :class="$store.sidebar.open ? 'w-64' : 'w-14'"
        >
            <div class="flex h-14 shrink-0 items-center border-b border-sidebar-border px-2.5">
                <livewire:workspace-switcher />
            </div>

            <nav class="flex-1 overflow-y-auto min-h-0 px-2 py-3 space-y-5" aria-label="Menu Utama">
                @foreach ($navGroups as $group)
                    @if (count($group['items']) > 0)
                        <div>
                            <div
                                x-show="$store.sidebar.open"
                                class="px-2.5 pb-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"
                            >
                                {{ $group['label'] }}
                            </div>
                            <ul class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    @php
                                        $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*');
                                    @endphp
                                    <li>
                                        <a
                                            href="{{ route($item['route']) }}"
                                            wire:navigate
                                            @class([
                                                'flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors',
                                                'bg-sidebar-accent text-sidebar-accent-foreground font-medium' => $active,
                                                'text-sidebar-foreground/80 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground' => ! $active,
                                            ])
                                            title="{{ $item['label'] }}"
                                        >
                                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="size-4 shrink-0" />
                                            <span x-show="$store.sidebar.open" class="truncate">{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-sidebar-border p-2.5 bg-sidebar">
                <div class="flex items-center gap-2.5 px-1.5 py-1">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary text-secondary-foreground text-xs font-semibold">
                        {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div x-show="$store.sidebar.open" class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-sidebar-foreground">{{ $user?->name }}</p>
                        <p class="truncate text-[11px] text-muted-foreground">{{ $user?->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" x-show="$store.sidebar.open">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md p-1.5 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                            title="Keluar"
                            aria-label="Keluar"
                        >
                            <x-lucide-log-out class="size-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobile off-canvas Sheet sidebar --}}
        <div
            x-cloak
            x-show="$store.sidebar.mobileOpen"
            class="fixed inset-0 z-50 lg:hidden flex"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-black/60" @click="$store.sidebar.mobileOpen = false"></div>
            <div class="relative z-10 flex w-64 flex-col h-screen h-dvh bg-sidebar text-sidebar-foreground border-r border-sidebar-border overflow-hidden">
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-sidebar-border px-3">
                    <span class="font-semibold text-sm">{{ $activeWorkspace?->name ?? 'SuratinAja' }}</span>
                    <button type="button" @click="$store.sidebar.mobileOpen = false" class="p-1 text-muted-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto min-h-0 p-3 space-y-4">
                    @foreach ($navGroups as $group)
                        @if (count($group['items']) > 0)
                            <div>
                                <div class="px-2 pb-1 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    {{ $group['label'] }}
                                </div>
                                <ul class="space-y-1">
                                    @foreach ($group['items'] as $item)
                                        <li>
                                            <a
                                                href="{{ route($item['route']) }}"
                                                wire:navigate
                                                class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm hover:bg-sidebar-accent"
                                            >
                                                <x-dynamic-component :component="'lucide-' . $item['icon']" class="size-4 shrink-0" />
                                                <span>{{ $item['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </nav>
                <div class="shrink-0 border-t border-sidebar-border p-3 bg-sidebar">
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2.5 min-w-0 flex-1 hover:opacity-85 transition-opacity cursor-pointer group" title="Buka Profil & Pengaturan Akun">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary text-secondary-foreground text-xs font-semibold group-hover:ring-2 group-hover:ring-primary/40 transition-all">
                                {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium text-sidebar-foreground group-hover:text-primary transition-colors">{{ $user?->name }}</p>
                                <p class="truncate text-[11px] text-muted-foreground">{{ $user?->email }}</p>
                            </div>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md p-1.5 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                                title="Keluar"
                                aria-label="Keluar"
                            >
                                <x-lucide-log-out class="size-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Inset --}}
        <div class="flex flex-1 flex-col min-w-0">
            @if ($user?->is_demo)
                <div class="bg-amber-500/10 border-b border-amber-500/20 px-3 py-1.5 text-center text-xs font-medium text-amber-800 dark:text-amber-300 flex items-center justify-center gap-2 shrink-0">
                    <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span><strong>Mode Demo (Hanya Lihat):</strong> Anda sedang menjelajahi data sampel. Navigasi dan pratinjau aktif, perubahan data tidak disimpan.</span>
                </div>
            @endif
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between border-b border-border bg-background/95 px-3 sm:px-4 md:px-6 backdrop-blur">
                <div class="flex items-center gap-2 sm:gap-2.5 min-w-0 flex-1 mr-2">
                    <button
                        type="button"
                        @click="$store.sidebar.toggle()"
                        class="inline-flex h-10 w-10 lg:h-8 lg:w-8 min-w-[40px] min-h-[40px] lg:min-w-0 lg:min-h-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground touch-manipulation shrink-0"
                        aria-label="Toggle Sidebar (Ctrl+B)"
                        title="Toggle Sidebar (Ctrl+B)"
                    >
                        <x-lucide-panel-left class="size-5 lg:size-4" />
                    </button>

                    @php
                        $effectiveBackUrl = $backUrl;
                        if (empty($effectiveBackUrl) && count($breadcrumbs) > 1) {
                            for ($i = count($breadcrumbs) - 2; $i >= 0; $i--) {
                                if (! empty($breadcrumbs[$i]['url'])) {
                                    $effectiveBackUrl = $breadcrumbs[$i]['url'];
                                    break;
                                }
                            }
                        }
                    @endphp

                    @if (! empty($effectiveBackUrl))
                        <a
                            href="{{ $effectiveBackUrl }}"
                            x-data="{
                                tooltip: 'Kembali ke halaman sebelumnya',
                                init() {
                                    if (window.SuratinAjaNav) {
                                        this.tooltip = window.SuratinAjaNav.getPreviousLabel();
                                    }
                                }
                            }"
                            :title="tooltip"
                            @click.prevent="window.SuratinAjaNav ? window.SuratinAjaNav.goBack('{{ $effectiveBackUrl }}') : (window.location.href = '{{ $effectiveBackUrl }}')"
                            class="inline-flex h-9 w-9 sm:h-8 sm:w-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-accent hover:text-foreground transition-colors shrink-0 cursor-pointer"
                            title="Kembali ke halaman sebelumnya"
                            aria-label="Kembali ke halaman sebelumnya"
                        >
                            <x-lucide-arrow-left class="size-4" />
                        </a>
                    @endif

                    <x-ui.separator orientation="vertical" class="mx-0.5 sm:mx-1 h-5 shrink-0" />

                    <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs sm:text-sm min-w-0 flex-nowrap overflow-hidden">
                        <a href="{{ route('dashboard') }}" wire:navigate class="text-muted-foreground hover:text-foreground font-medium shrink-0 hover:underline max-w-[90px] sm:max-w-[160px] truncate">
                            {{ $activeWorkspace?->name ?? 'Workspace' }}
                        </a>
                        @foreach ($breadcrumbs as $crumb)
                            <x-lucide-chevron-right class="size-3.5 text-muted-foreground/70 shrink-0" />
                            @if (! empty($crumb['url']) && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" wire:navigate class="text-muted-foreground hover:text-foreground hover:underline truncate max-w-[80px] sm:max-w-none hidden sm:inline">
                                    {{ $crumb['label'] }}
                                </a>
                            @else
                                <span class="font-semibold text-foreground truncate max-w-[130px] sm:max-w-none">{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                        @if (empty($breadcrumbs))
                            <x-lucide-chevron-right class="size-3.5 text-muted-foreground/70 shrink-0" />
                            <span class="font-semibold text-foreground truncate max-w-[140px] sm:max-w-none">{{ $title }}</span>
                        @endif
                    </nav>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Global Search Trigger (Desktop/Tablet & Mobile) --}}
                    <button
                        type="button"
                        @click="$dispatch('open-global-search')"
                        class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1.5 h-8 text-xs text-muted-foreground bg-muted/40 hover:bg-muted hover:text-foreground border border-border/80 rounded-lg transition cursor-pointer select-none"
                        title="Pencarian Global (⌘K / Ctrl+K)"
                    >
                        <x-lucide-search class="size-3.5 shrink-0" />
                        <span class="text-xs">Cari surat, amplop...</span>
                        <kbd class="ml-1 inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono font-medium text-muted-foreground bg-background border border-border rounded shadow-2xs">
                            ⌘K
                        </kbd>
                    </button>

                    <button
                        type="button"
                        @click="$dispatch('open-global-search')"
                        class="sm:hidden inline-flex items-center justify-center h-10 w-10 min-w-[40px] min-h-[40px] text-muted-foreground hover:bg-accent hover:text-foreground rounded-md transition touch-manipulation"
                        aria-label="Pencarian Global (⌘K)"
                        title="Pencarian Global"
                    >
                        <x-lucide-search class="size-4" />
                    </button>

                    @if ($role)
                        <x-ui.badge variant="outline" class="hidden sm:inline-flex">
                            {{ $role->label() }}
                        </x-ui.badge>
                    @endif

                    @if ($unreadCount > 0)
                        <a
                            href="{{ route('bulk.index') }}"
                            class="relative inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-foreground hover:bg-accent"
                            title="{{ $unreadCount }} notifikasi baru"
                        >
                            <x-lucide-bell class="size-4" />
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-semibold text-white">
                                {{ $unreadCount }}
                            </span>
                        </a>
                    @endif

                    <button
                        type="button"
                        @click="$store.theme.toggle()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                        aria-label="Ganti Tema Light/Dark"
                        title="Ganti Tema Light/Dark"
                    >
                        <x-lucide-sun class="size-4 dark:hidden" />
                        <x-lucide-moon class="size-4 hidden dark:block" />
                    </button>
                </div>
            </header>

            <main @class([
                'w-full flex-1',
                'mx-auto max-w-[87.5rem] px-4 md:px-8 py-6' => ! $fullWidth,
                'px-2 sm:px-3 py-2 flex flex-col min-h-0' => $fullWidth,
            ])>
                @if (session('status'))
                    <x-ui.alert class="mb-4">
                        {{ session('status') }}
                    </x-ui.alert>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    <livewire:global-search />
    <x-ui.toaster />
    @livewireScripts
    @stack('scripts')
</body>
</html>
