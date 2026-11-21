{{-- Global Confirm Modal - driven by Alpine.store('confirmModal') --}}
<div
    x-data
    x-show="$store.confirmModal.isOpen"
    x-cloak
    @keydown.escape.window="$store.confirmModal.cancel()"
    class="fixed inset-0 z-[65] flex items-center justify-center p-4"
    aria-modal="true"
    role="dialog"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
        @click="$store.confirmModal.cancel()"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel --}}
    <div
        class="relative z-10 w-full max-w-sm rounded-xl border border-border bg-card p-6 shadow-2xl space-y-4"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Header & Icon --}}
        <div class="flex items-start gap-3.5">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-full"
                :class="$store.confirmModal.confirmVariant === 'warning'
                    ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400'
                    : 'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-400'"
            >
                <x-lucide-alert-triangle class="size-5" />
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-foreground leading-snug" x-text="$store.confirmModal.title"></h3>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed whitespace-pre-line" x-text="$store.confirmModal.message"></p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-2 pt-2 border-t border-border">
            <button
                type="button"
                @click="$store.confirmModal.cancel()"
                :disabled="$store.confirmModal.loading"
                class="inline-flex items-center rounded-lg border border-border bg-background px-3.5 py-2 text-xs font-semibold text-foreground shadow-xs hover:bg-muted transition cursor-pointer disabled:opacity-50"
                x-text="$store.confirmModal.cancelText"
            ></button>
            <button
                type="button"
                @click="$store.confirmModal.confirm()"
                :disabled="$store.confirmModal.loading"
                class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition cursor-pointer disabled:opacity-70"
                :class="$store.confirmModal.confirmVariant === 'warning'
                    ? 'bg-amber-600 hover:bg-amber-700'
                    : 'bg-red-600 hover:bg-red-700'"
            >
                <span
                    x-show="$store.confirmModal.loading"
                    class="size-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"
                ></span>
                <span x-text="$store.confirmModal.confirmText"></span>
            </button>
        </div>
    </div>
</div>
