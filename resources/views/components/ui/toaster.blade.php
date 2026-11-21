<div
    x-data
    class="fixed bottom-4 right-4 z-[70] flex flex-col gap-2 w-[calc(100vw-2rem)] max-w-sm pointer-events-none"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            class="pointer-events-auto flex items-start gap-3 rounded-xl border border-border bg-popover p-3.5 text-popover-foreground shadow-lg transition-all"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            @mouseenter="$store.toasts.pause(toast.id)"
            @mouseleave="$store.toasts.resume(toast.id)"
        >
            {{-- Icon Indicator --}}
            <div
                class="mt-0.5 size-5 shrink-0 rounded-full flex items-center justify-center text-xs"
                :class="{
                    'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400': toast.type === 'success',
                    'bg-red-500/15 text-red-600 dark:text-red-400': toast.type === 'error',
                    'bg-amber-500/15 text-amber-600 dark:text-amber-400': toast.type === 'warning',
                    'bg-blue-500/15 text-blue-600 dark:text-blue-400': toast.type === 'info',
                }"
            >
                <template x-if="toast.type === 'success'">
                    <x-lucide-check-circle-2 class="size-3.5" />
                </template>
                <template x-if="toast.type === 'error'">
                    <x-lucide-alert-circle class="size-3.5" />
                </template>
                <template x-if="toast.type === 'warning'">
                    <x-lucide-alert-triangle class="size-3.5" />
                </template>
                <template x-if="toast.type === 'info'">
                    <x-lucide-info class="size-3.5" />
                </template>
            </div>

            {{-- Text Content --}}
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold leading-snug text-foreground" x-text="toast.title"></p>
                <p
                    x-show="toast.description"
                    class="mt-0.5 text-[11px] text-muted-foreground leading-relaxed"
                    x-text="toast.description"
                ></p>
            </div>

            {{-- Close Button --}}
            <button
                type="button"
                @click="$store.toasts.remove(toast.id)"
                class="shrink-0 mt-0.5 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                aria-label="Tutup notifikasi"
            >
                <x-lucide-x class="size-3.5" />
            </button>
        </div>
    </template>
</div>
