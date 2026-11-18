# DESIGN.md: SuratinAja

> **Arah visual wajib:** gaya aplikasi **Dokploy** (shadcn/ui *zinc*, sidebar collapsible, header breadcrumb, panel `Card` ber-border, monokrom). Token warna di dokumen ini disalin 1:1 dari `apps/dokploy/styles/globals.css` repositori Dokploy agar identik secara visual. Implementasi: Blade components + Alpine.js (port shadcn) di atas Tailwind v4 — tanpa React.

## 1. Design Read (Evidence-Based)

- **Product:** SuratinAja — aplikasi web manajemen persuratan multi-workspace untuk organisasi kampus/kepanitiaan, dengan pipeline template `.docx` → penomoran otomatis → generate massal.
- **Target Audience:** Sekretaris organisasi (operator harian), Ketua (monitoring read-only), Admin Workspace (konfigurasi). Pengguna terlatih; toleran terhadap kepadatan data.
- **Product Type:** SaaS Application (internal tool / dashboard operasional).
- **Primary User Goal:** Menghasilkan surat `.docx` bernomor valid — tunggal atau massal — dalam satu alur tanpa berpindah aplikasi.
- **User Roles & Workflows:**
  - *Sekretaris:* pilih template (DEC-001) → isi form dinamis `${...}` → nomor otomatis (DEC-002) → bulk generate (DEC-003) → ubah status manual ke `Selesai` (DEC-004) → revisi dengan manual assign nomor (DEC-005).
  - *Ketua:* dashboard, daftar surat, detail & pratinjau; tanpa aksi tulis.
  - *Admin:* kop surat, pola penomoran, anggota, template workspace.
- **Brand / Aesthetic Direction:** *Dokploy-style developer dashboard* — netral zinc, monokrom, hairline border, panel ber-border `rounded-lg`, sidebar gelap-terang konsisten, tanpa gradient dekoratif.
- **Key Personality Traits:** Presisi, Tenang, Auditable.

## 2. Design Dials

| Dial | Nilai | Rasional |
|:---|:---|:---|
| DESIGN_VARIANCE | 3 | Shell (sidebar + header + card) identik di semua halaman seperti Dokploy; variasi hanya pada isi card (tabel, form, split-pane). |
| MOTION_INTENSITY | 3 | Hanya `t-page-enter` Dokploy (fade + translateX 8px + blur 3px, 250ms), transisi dropdown/dialog 150ms, progress bulk. Hormati `prefers-reduced-motion`. |
| VISUAL_DENSITY | 7 | Tabel `text-sm`, sel `px-4 py-2.5`, data-heavy tetapi dengan ruang napas khas Dokploy. |

## 3. Visual Identity (Dokploy Adaptation)

- **Shell tetap:** Sidebar kiri (lebar 16rem, collapsible ke ikon 3rem, toggle `Ctrl/⌘+B`) + area `SidebarInset` dengan header 56px (trigger · separator · breadcrumb; kanan: toggle tema).
- **Halaman = Card:** setiap halaman berisi satu `Card` utama (`rounded-xl border bg-card`); `CardHeader` memuat ikon + judul (`text-xl font-semibold`) + deskripsi (`text-sm text-muted-foreground`) dan aksi utama di kanan. Tabel dan form hidup **di dalam** card.
- **Monokrom:** primary = hitam (light) / putih (dark). Warna hanya untuk status (dot) dan aksi destruktif.
- **Kop surat builder:** kanvas kertas putih ber-border (tetap putih di dark mode agar proporsional dengan dokumen cetak).

## 4. Semantic Color System (1:1 Dokploy)

Nilai HSL tanpa `hsl()`; dipetakan ke Tailwind via `@theme { --color-x: hsl(var(--x)); }`.

```css
:root {             /* LIGHT — DEFAULT */
  --background: 0 0% 100%;        --foreground: 240 10% 3.9%;
  --card: 0 0% 100%;              --card-foreground: 240 10% 3.9%;
  --popover: 0 0% 100%;           --popover-foreground: 240 10% 3.9%;
  --primary: 240 5.9% 10%;        --primary-foreground: 0 0% 98%;
  --secondary: 240 4.8% 95.9%;    --secondary-foreground: 240 5.9% 10%;
  --muted: 240 4.8% 95.9%;        --muted-foreground: 240 3.8% 46.1%;
  --accent: 240 4.8% 95.9%;       --accent-foreground: 240 5.9% 10%;
  --destructive: 0 84.2% 50.2%;   --destructive-foreground: 0 0% 98%;
  --border: 240 5.9% 90%;         --input: 240 5.9% 90%;
  --ring: 240 10% 3.9%;           --radius: 0.5rem;
  --sidebar-background: 0 0% 98%; --sidebar-foreground: 240 5.3% 26.1%;
  --sidebar-primary: 240 5.9% 10%; --sidebar-primary-foreground: 0 0% 98%;
  --sidebar-accent: 240 4.8% 95.9%; --sidebar-accent-foreground: 240 5.9% 10%;
  --sidebar-border: 220 13% 91%;  --sidebar-ring: 217.2 91.2% 59.8%;
}
.dark {             /* DARK — pure black seperti Dokploy */
  --background: 0 0% 0%;          --foreground: 0 0% 98%;
  --card: 240 4% 10%;             --card-foreground: 0 0% 98%;
  --popover: 240 10% 3.9%;        --popover-foreground: 0 0% 98%;
  --primary: 0 0% 98%;            --primary-foreground: 240 5.9% 10%;
  --secondary: 240 3.7% 15.9%;    --secondary-foreground: 0 0% 98%;
  --muted: 240 4% 10%;            --muted-foreground: 240 5% 64.9%;
  --accent: 240 3.7% 15.9%;       --accent-foreground: 0 0% 98%;
  --destructive: 0 84.2% 50.2%;   --destructive-foreground: 0 0% 98%;
  --border: 240 3.7% 15.9%;       --input: 240 4% 10%;
  --ring: 240 4.9% 83.9%;
  --sidebar-background: 240 5.9% 10%; --sidebar-foreground: 240 4.8% 95.9%;
  --sidebar-primary: 224.3 76.3% 48%; --sidebar-primary-foreground: 0 0% 100%;
  --sidebar-accent: 240 3.7% 15.9%; --sidebar-accent-foreground: 240 4.8% 95.9%;
  --sidebar-border: 240 3.7% 15.9%; --sidebar-ring: 217.2 91.2% 59.8%;
}
```

**Status surat & nomor** (pola status-dot Dokploy; selalu dot + ikon Lucide + teks):

| Status | Dot | Ikon | Label |
|:---|:---|:---|:---|
| `draft` | `bg-zinc-400` | `file-pen-line` | Draft |
| `menunggu_ttd` | `bg-amber-500` | `clock` | Menunggu TTD |
| `selesai` | `bg-emerald-500` | `circle-check` | Selesai |
| `dibatalkan` | `bg-zinc-500` | `ban` | Dibatalkan (teks dicoret) |
| nomor `aktif` | `bg-emerald-500` | `hash` | Aktif |
| nomor `hangus` | `bg-red-500` | `circle-x` | Hangus |
| bulk `queued/processing/done/failed` | zinc / amber (pulse) / emerald / red | `loader` dsb. | sesuai status |

**Aturan:** tidak ada gradient; warna status hanya untuk status; tombol utama = `bg-primary text-primary-foreground`; destruktif = `bg-destructive`.

## 5. Typography

- **Sans:** `Inter` (sama dengan Dokploy), fallback `ui-sans-serif, system-ui`.
- **Mono:** `ui-monospace, SFMono-Regular, Menlo, monospace` — nomor surat, pola `{seq}/{kode}/{bulan_romawi}/{tahun}`, token `${nama_kegiatan}`.

| Level | Kelas | Pemakaian |
|:---|:---|:---|
| Page/Card title | `text-xl font-semibold tracking-tight` | Judul card utama |
| Section title | `text-base font-medium` | Sub-bagian form |
| Body | `text-sm` | Tabel & form |
| Description | `text-sm text-muted-foreground` | Deskripsi card / helper |
| Label | `text-sm font-medium leading-none` | Label form (shadcn) |
| Caption | `text-xs text-muted-foreground` | Timestamp, metadata |
| Mono | `font-mono text-[13px]` | Nomor & token |

## 6. Layout Strategy

| Halaman | Pola (di dalam Card) |
|:---|:---|
| Dashboard | Grid 4 stat-card per status (data aktual) + card "Surat Terbaru" (tabel) |
| Daftar Surat | Toolbar (search + select status) + Table + pagination |
| Detail Surat | Split 40/60: metadata, status & aksi \| Tabs (Pratinjau PDF · Riwayat) |
| Buat/Edit Surat | Form single-column max 720px, section dipisah `Separator` |
| Bulk Generate | Two-pane: Tabs input (Manual/Upload) \| antrean bernomor + progress |
| Template | Tabs (Workspace/Global) + Table; upload = Dialog 2 langkah |
| Kop Surat | Kanvas kertas (kiri) + panel form (kanan) |
| Penomoran | Form + live preview mono |
| Anggota | Table + Dialog invite |
| Auth | Halaman tengah: logo + judul, card form `max-w-sm` |

Container konten: `mx-auto w-full max-w-[87.5rem] px-4 md:px-8 py-6`. Spacing basis 4px.

## 7. Anti-AI-Slop Rules

| Avoid | Prefer |
|:---|:---|
| Hero, blob, gradient ungu-biru | Shell Dokploy monokrom |
| Kartu fitur dekoratif | Card fungsional berisi tabel/form |
| Ikon dekoratif besar | Ikon Lucide 16px fungsional |
| Animasi layout-heavy | `t-page-enter` + transisi opacity 150ms |

## 8. Card & Container Discipline

- Satu card utama per halaman (plus stat-card di dashboard, card di split-pane detail).
- Tidak ada card di dalam card; sub-bagian dalam card dipisah `Separator` / `border-t`.
- Dialog & Sheet mengikuti shadcn (`rounded-lg border bg-background p-6 shadow-lg`).

## 9. Component Rules & Action Hierarchy (shadcn variants)

- **default** (primary): satu per viewport — "Buat Surat", "Generate", "Simpan".
- **outline / secondary**: aksi pendukung — Unduh, Duplikasi, Filter.
- **ghost**: aksi baris tabel, ikon toolbar.
- **destructive**: Batalkan surat, Revoke anggota (selalu via AlertDialog).
- **States:** hover `bg-primary/90` / `bg-accent`; focus `focus-visible:ring-2 ring-ring ring-offset-2`; disabled `opacity-50 pointer-events-none`; active nav sidebar `bg-sidebar-accent font-medium`.
- Kontras ≥ 4.5:1 (WCAG AA).

## 10. Responsive

| Breakpoint | Perilaku |
|:---|:---|
| `< 768px` | Sidebar menjadi Sheet off-canvas (trigger di header); split-pane stack; tabel sembunyikan kolom sekunder (tanggal, template) |
| `768–1024px` | Sidebar collapsed ke ikon default; split 50/50 |
| `≥ 1024px` | Sidebar expanded; split 40/60; tabel penuh |

## 11. States

- **Loading:** `Skeleton` (`animate-pulse rounded-md bg-muted`) per baris tabel/field; `wire:loading` pada tombol (spinner `loader-circle` animate-spin).
- **Empty:** ikon dalam lingkaran `bg-muted`, judul, deskripsi, tombol CTA (pola empty-state Dokploy). Contoh: "Belum ada surat di workspace ini" → "Buat Surat Pertama".
- **Error:** `Alert variant=destructive` inline dengan pesan spesifik + aksi "Coba Lagi"; toast (pojok kanan bawah, gaya sonner) untuk feedback aksi.

## 12. Accessibility (WCAG 2.1 AA)

- Navigasi keyboard penuh; focus ring terlihat.
- Tabel `role="grid"`, dialog `role="dialog" aria-modal="true"` + focus trap, badge `aria-label="Status: Selesai"`.
- Touch target ≥ 44px di mobile.
- Status tidak pernah hanya warna (dot + ikon + teks).

## 13. Design Rationale

Dokploy dipilih sebagai bahasa visual karena menyajikan data operasional padat dengan shell yang konsisten dan netral; sekretaris berpindah cepat antara daftar, form, dan hasil generate tanpa beban kognitif. Monokrom zinc membuat satu-satunya warna di layar — status surat — langsung terbaca, memetakan state machine (`draft`/`menunggu_ttd`/`selesai`/`dibatalkan`, nomor `aktif`/`hangus`) sesuai DEC-004 & DEC-005. Mono untuk nomor & token menjembatani bahasa template `.docx` dengan pengguna.

## 14. NO Invented Design Content Rule

Dilarang testimoni, logo fiktif, screenshot palsu, statistik karangan. Data contoh hanya dari scope PRD (workspace `HIMATRIS`, `OASIS`; placeholder `${nama_kegiatan}`, `${nomor_surat}`). Angka dashboard berasal dari data aktual workspace.

## 15. Design Pre-Flight Checklist

- [x] Design is derived from PRD/project context.
- [x] Visual direction (Dokploy) is explicit and token-exact.
- [x] Design dials are intentional.
- [x] Color hierarchy is intentional (monokrom + status).
- [x] Typography is intentional.
- [x] Cards are disciplined (no nesting).
- [x] Responsive behavior is defined.
- [x] Loading / empty / error states are defined.
- [x] Accessibility is defined.
- [x] No fake content.
- [x] A coding agent can implement without inventing major visual decisions.
