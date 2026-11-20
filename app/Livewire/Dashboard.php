<?php

namespace App\Livewire;

use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Models\Disposisi;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $workspace && $user ? $workspace->roleOf($user) : null;
        $wsId = $workspace?->id;

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $today = $now->toDateString();

        // 1. KPI Surat Keluar (Single Aggregated Query)
        $keluarStatusCounts = Letter::where('workspace_id', $wsId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $hangusCount = LetterNumber::where('workspace_id', $wsId)->where('status', NumberStatus::Hangus->value)->count();

        $keluarCounts = [
            'total' => (int) $keluarStatusCounts->sum(),
            'draft' => (int) ($keluarStatusCounts[LetterStatus::Draft->value] ?? 0),
            'menunggu_ttd' => (int) ($keluarStatusCounts[LetterStatus::MenungguTtd->value] ?? 0),
            'selesai' => (int) ($keluarStatusCounts[LetterStatus::Selesai->value] ?? 0),
            'hangus' => $hangusCount,
        ];

        // 2. KPI Surat Masuk (Single Aggregated Query)
        $masukStatusCounts = SuratMasuk::where('workspace_id', $wsId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $masukCounts = [
            'total' => (int) $masukStatusCounts->sum(),
            'baru' => (int) ($masukStatusCounts['baru'] ?? 0),
            'menunggu_disposisi' => (int) ($masukStatusCounts['menunggu_disposisi'] ?? 0),
            'didisposisi' => (int) ($masukStatusCounts['didisposisi'] ?? 0),
            'selesai' => (int) ($masukStatusCounts['selesai'] ?? 0),
        ];

        // 3. Disposisi aktif yang ditugaskan ke user login
        $userActiveDisposisis = Disposisi::whereHas('suratMasuk', fn ($q) => $q->where('workspace_id', $wsId))
            ->where('kepada_user_id', $user?->id)
            ->whereIn('status_tindak_lanjut', ['belum_diproses', 'sedang_diproses'])
            ->with('suratMasuk')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $actionNeededCount = $keluarCounts['menunggu_ttd']
            + $masukCounts['baru']
            + $masukCounts['menunggu_disposisi']
            + $userActiveDisposisis->count();

        // 4. Aktivitas Bulan Ini
        $thisMonthKeluar = Letter::where('workspace_id', $wsId)
            ->whereBetween('letter_date', [$startOfMonth, $endOfMonth])
            ->count();

        $thisMonthMasuk = SuratMasuk::where('workspace_id', $wsId)
            ->whereBetween('tanggal_surat', [$startOfMonth, $endOfMonth])
            ->count();

        // Widget: Perlu Perhatian Segera
        $lettersNeedingSignature = Letter::where('workspace_id', $wsId)
            ->where('status', LetterStatus::MenungguTtd->value)
            ->with(['activeNumber', 'latestNumber', 'author'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $suratMasukPendingDisposisi = SuratMasuk::where('workspace_id', $wsId)
            ->whereIn('status', ['baru', 'menunggu_disposisi'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Widget: Agenda & Jadwal Terdekat (Hari H Acara / Batas Waktu)
        $upcomingAgendas = SuratMasuk::where('workspace_id', $wsId)
            ->whereNotNull('tanggal_acara_atau_deadline')
            ->where('tanggal_acara_atau_deadline', '>=', $today)
            ->orderBy('tanggal_acara_atau_deadline', 'asc')
            ->limit(5)
            ->get();

        // Widget: Aktivitas Terbaru (Gabungan Surat Keluar & Surat Masuk)
        $recentLetters = Letter::where('workspace_id', $wsId)
            ->with(['activeNumber', 'latestNumber', 'template', 'author'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $recentSuratMasuk = SuratMasuk::where('workspace_id', $wsId)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        // Combine and sort by created_at desc
        $combinedActivities = collect();
        foreach ($recentLetters as $l) {
            $combinedActivities->push([
                'type' => 'keluar',
                'id' => $l->id,
                'number' => $l->formatted_number ?? 'Draf',
                'perihal' => $l->perihal,
                'actor' => $l->author?->name ?? 'Sistem',
                'status' => $l->status,
                'created_at' => $l->created_at,
                'url' => route('letters.show', $l->id),
            ]);
        }
        foreach ($recentSuratMasuk as $sm) {
            $combinedActivities->push([
                'type' => 'masuk',
                'id' => $sm->id,
                'number' => $sm->nomor_agenda,
                'perihal' => $sm->perihal,
                'actor' => $sm->pengirim,
                'status' => $sm->status,
                'created_at' => $sm->created_at,
                'url' => route('surat-masuk.show', $sm->id),
            ]);
        }

        $activities = $combinedActivities->sortByDesc('created_at')->take(8)->values();

        // Preserve legacy 'counts' array for backward compatibility with existing tests
        $counts = [
            'draft' => $keluarCounts['draft'],
            'menunggu_ttd' => $keluarCounts['menunggu_ttd'],
            'selesai' => $keluarCounts['selesai'],
            'hangus' => $keluarCounts['hangus'],
        ];

        return view('livewire.dashboard', [
            'workspace' => $workspace,
            'role' => $role,
            'counts' => $counts,
            'keluarCounts' => $keluarCounts,
            'masukCounts' => $masukCounts,
            'actionNeededCount' => $actionNeededCount,
            'thisMonthKeluar' => $thisMonthKeluar,
            'thisMonthMasuk' => $thisMonthMasuk,
            'lettersNeedingSignature' => $lettersNeedingSignature,
            'suratMasukPendingDisposisi' => $suratMasukPendingDisposisi,
            'userActiveDisposisis' => $userActiveDisposisis,
            'upcomingAgendas' => $upcomingAgendas,
            'activities' => $activities,
            'recentLetters' => $recentLetters,
        ])->layout('components.layouts.app', [
            'title' => 'Dashboard',
            'breadcrumbs' => [
                ['label' => 'Dashboard'],
            ],
        ]);
    }
}

