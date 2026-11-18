<div
    x-data
    class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            class="pointer-events-auto flex items-start gap-3 rounded-lg border border-border bg-popover p-3.5 text-popover-foreground shadow-lg transition-all"
        >
            <span
                class="mt-1 h-2 w-2 shrink-0 rounded-full"
                :class="toast.type === 'error' ? 'bg-red-500' : 'bg-emerald-500'"
            ></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium leading-none" x-text="toast.title"></p>
                <p
                    x-show="toast.description"
                    class="mt-1 text-xs text-muted-foreground"
                    x-text="toast.description"
                ></p>
            </div>
            <button
                type="button"
                @click="$store.toasts.remove(toast.id)"
                class="text-muted-foreground hover:text-foreground"
                aria-label="Tutup notifikasi"
            >
                <x-lucide-x class="size-3.5" />
            </button>
        </div>
    </template>
</div>
