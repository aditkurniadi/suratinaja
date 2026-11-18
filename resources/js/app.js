const applyTheme = (mode) => {
    const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('theme', {
        mode: localStorage.getItem('theme') || 'light',
        set(mode) {
            this.mode = mode;
            localStorage.setItem('theme', mode);
            applyTheme(mode);
        },
        toggle() {
            this.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        },
    });

    Alpine.store('toasts', {
        items: [],
        push({ type = 'success', title = '', description = '' }) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, title, description });
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    Alpine.store('sidebar', {
        open: localStorage.getItem('sidebar:open') !== 'false',
        mobileOpen: false,
        toggle() {
            if (window.innerWidth < 768) {
                this.mobileOpen = !this.mobileOpen;
                return;
            }
            this.open = !this.open;
            localStorage.setItem('sidebar:open', this.open);
        },
    });
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (window.Alpine?.store('theme')?.mode === 'system') applyTheme('system');
});

window.addEventListener('toast', (e) => {
    const detail = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    window.Alpine?.store('toasts')?.push(detail || {});
});

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        window.Alpine?.store('sidebar')?.toggle();
    }
});
