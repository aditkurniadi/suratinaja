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
            if (window.innerWidth < 1024) {
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

document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                try {
                    const data = typeof content === 'string' ? JSON.parse(content) : content;
                    if (data?.demo_restricted) {
                        preventDefault();
                        window.Alpine?.store('toasts')?.push({
                            type: 'warning',
                            title: 'Mode Demo (Hanya Lihat)',
                            description: data.message || 'Perubahan tidak disimpan dalam akun demo.',
                        });
                    }
                } catch (err) {}
            });
        });
    }
});

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        window.Alpine?.store('sidebar')?.toggle();
    }

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('open-global-search'));
    }
});

// =========================================================================
// Smart Navigation History Tracker (Per-Tab Session Storage)
// =========================================================================
const NAV_STORAGE_KEY = 'suratinaja:nav_history';
const NAV_MAX_ENTRIES = 30;

const IGNORED_BACK_PATTERNS = [
    /\/create(\?.*)?$/i,
    /\/(login|register|password|forgot-password|reset-password|two-factor-challenge)\b/i,
    /\/invitations(\/.*)?$/i,
    /\/logout\b/i,
];

export const SuratinAjaNav = {
    isIgnoredUrl(url) {
        if (!url) return true;
        try {
            const parsed = new URL(url, window.location.origin);
            const path = parsed.pathname;
            return IGNORED_BACK_PATTERNS.some((pattern) => pattern.test(path));
        } catch {
            return true;
        }
    },

    getHistory() {
        try {
            const raw = sessionStorage.getItem(NAV_STORAGE_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    },

    setHistory(history) {
        try {
            sessionStorage.setItem(NAV_STORAGE_KEY, JSON.stringify(history.slice(-NAV_MAX_ENTRIES)));
        } catch (e) {
            console.warn('Failed to save nav history:', e);
        }
    },

    getCurrentUrl() {
        return window.location.pathname + window.location.search;
    },

    trackCurrent() {
        const current = this.getCurrentUrl();
        const history = this.getHistory();

        // Do not add consecutive duplicates (e.g. page refresh)
        if (history.length > 0 && history[history.length - 1] === current) {
            return;
        }

        history.push(current);
        this.setHistory(history);
    },

    getValidPreviousUrl() {
        const current = this.getCurrentUrl();
        const history = this.getHistory();

        // Search backwards from the second-to-last item
        for (let i = history.length - 2; i >= 0; i--) {
            const candidate = history[i];
            if (candidate !== current && !this.isIgnoredUrl(candidate)) {
                return candidate;
            }
        }

        // Secondary fallback: check document.referrer if same-origin
        if (document.referrer) {
            try {
                const refUrl = new URL(document.referrer);
                if (refUrl.origin === window.location.origin) {
                    const refPath = refUrl.pathname + refUrl.search;
                    if (refPath !== current && !this.isIgnoredUrl(refPath)) {
                        return refPath;
                    }
                }
            } catch {}
        }

        return null;
    },

    getPreviousLabel() {
        const prev = this.getValidPreviousUrl();
        if (!prev) return 'Kembali ke halaman sebelumnya';
        if (prev.startsWith('/calendar')) return 'Kembali ke Kalender';
        if (prev.startsWith('/letters')) return 'Kembali ke Daftar Surat';
        if (prev.startsWith('/surat-masuk')) return 'Kembali ke Surat Masuk';
        if (prev.startsWith('/templates')) return 'Kembali ke Library Template';
        if (prev.startsWith('/settings/envelope-templates')) return 'Kembali ke Master Template Amplop';
        if (prev.startsWith('/settings/kop')) return 'Kembali ke Builder Kop Surat';
        if (prev.startsWith('/settings/members')) return 'Kembali ke Anggota & Akses';
        if (prev.startsWith('/settings/numbering')) return 'Kembali ke Pola Penomoran';
        if (prev.startsWith('/settings/letter-types')) return 'Kembali ke Jenis Surat';
        if (prev.startsWith('/settings')) return 'Kembali ke Pengaturan';
        if (prev.startsWith('/bulk')) return 'Kembali ke Bulk Generate';
        if (prev.startsWith('/dashboard')) return 'Kembali ke Dashboard';
        return 'Kembali ke halaman sebelumnya';
    },

    goBack(fallbackUrl) {
        const target = this.getValidPreviousUrl();
        const destination = target || fallbackUrl;

        if (destination) {
            if (target) {
                // Prune history up to target
                const history = this.getHistory();
                const idx = history.lastIndexOf(target);
                if (idx !== -1) {
                    this.setHistory(history.slice(0, idx + 1));
                }
            }

            if (window.Livewire?.navigate) {
                window.Livewire.navigate(destination);
            } else {
                window.location.href = destination;
            }
        } else {
            window.history.back();
        }
    },
};

window.SuratinAjaNav = SuratinAjaNav;

// Track on initial page load and when navigating via Livewire
if (typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SuratinAjaNav.trackCurrent());
    } else {
        SuratinAjaNav.trackCurrent();
    }

    document.addEventListener('livewire:navigated', () => {
        SuratinAjaNav.trackCurrent();
    });
}

