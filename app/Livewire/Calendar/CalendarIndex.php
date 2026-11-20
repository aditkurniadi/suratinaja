<?php

namespace App\Livewire\Calendar;

use App\Enums\LetterStatus;
use App\Models\Letter;
use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kalender Persuratan')]
class CalendarIndex extends Component
{
    public int $year;

    public int $month;

    public string $filterType = 'all'; // 'all', 'surat_keluar', 'surat_masuk'

    public string $dateReference = 'letter_date'; // 'letter_date', 'event_date'

    public ?string $activeModal = null; // null | 'day-list' | 'pdf-preview'

    public bool $showPreviewModal = false;

    public bool $showMoreModal = false;

    public bool $openedFromDayList = false;

    public ?int $previewId = null;

    public ?string $previewType = null; // 'surat_keluar', 'surat_masuk'

    public ?string $moreModalDate = null;

    public ?string $selectedMobileDate = null;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            abort(403);
        }

        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
        $this->selectedMobileDate = now()->toDateString();
    }

    public function prevMonth(): void
    {
        $dt = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = (int) $dt->year;
        $this->month = (int) $dt->month;
        $this->selectedMobileDate = $dt->copy()->startOfMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $dt = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = (int) $dt->year;
        $this->month = (int) $dt->month;
        $this->selectedMobileDate = $dt->copy()->startOfMonth()->toDateString();
    }

    public function goToToday(): void
    {
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
        $this->selectedMobileDate = now()->toDateString();
    }

    public function selectMobileDate(string $date): void
    {
        $this->selectedMobileDate = $date;
    }

    public function setFilterType(string $type): void
    {
        if (in_array($type, ['all', 'surat_keluar', 'surat_masuk'], true)) {
            $this->filterType = $type;
        }
    }

    public function setDateReference(string $ref): void
    {
        if (in_array($ref, ['letter_date', 'event_date'], true)) {
            $this->dateReference = $ref;
        }
    }

    public function openPreview(string $type, int $id, bool $fromDayList = false): void
    {
        $this->previewType = $type;
        $this->previewId = $id;
        $this->openedFromDayList = $fromDayList;
        $this->activeModal = 'pdf-preview';
        $this->showPreviewModal = true;
        $this->showMoreModal = false;
    }

    public function backToDayList(): void
    {
        $this->activeModal = 'day-list';
        $this->openedFromDayList = false;
        $this->showPreviewModal = false;
        $this->showMoreModal = true;
    }

    public function openMoreModal(string $date): void
    {
        $this->moreModalDate = $date;
        $this->activeModal = 'day-list';
        $this->openedFromDayList = false;
        $this->showPreviewModal = false;
        $this->showMoreModal = true;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->openedFromDayList = false;
        $this->previewId = null;
        $this->previewType = null;
        $this->showPreviewModal = false;
        $this->showMoreModal = false;
    }

    public function closePreview(): void
    {
        $this->closeModal();
    }

    public function closeMoreModal(): void
    {
        $this->closeModal();
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        $currentMonth = Carbon::create($this->year, $this->month, 1);

        // Notion Calendar starts on Sunday (Sun -> Sat)
        $startOfCalendar = $currentMonth->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endOfCalendar = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $todayStr = now()->toDateString();

        $itemsByDate = [];

        // 1. Ambil Surat Keluar
        if ($this->filterType === 'all' || $this->filterType === 'surat_keluar') {
            $letterQuery = Letter::where('workspace_id', $ws->id);

            if ($this->dateReference === 'event_date') {
                $letterQuery->whereNotNull('tanggal_kegiatan')
                    ->whereBetween('tanggal_kegiatan', [$startOfCalendar->toDateString(), $endOfCalendar->toDateString()]);
            } else {
                $letterQuery->whereBetween('letter_date', [$startOfCalendar->toDateString(), $endOfCalendar->toDateString()]);
            }

            $letters = $letterQuery->with(['activeNumber', 'latestNumber', 'author', 'template'])->get();

            foreach ($letters as $letter) {
                $d = $this->dateReference === 'event_date'
                    ? $letter->tanggal_kegiatan?->toDateString()
                    : $letter->letter_date?->toDateString();

                if ($d) {
                    $statusEnum = $letter->status;
                    $statusKey = $statusEnum instanceof LetterStatus ? $statusEnum->value : (string) $statusEnum;
                    $statusLabel = match ($statusKey) {
                        'draft' => 'Draf',
                        'menunggu_ttd' => 'Menunggu TTD',
                        'selesai' => 'Selesai',
                        'hangus' => 'Hangus',
                        default => ucfirst(str_replace('_', ' ', $statusKey)),
                    };
                    $dotColor = match ($statusKey) {
                        'selesai' => 'bg-emerald-500',
                        'menunggu_ttd' => 'bg-amber-500',
                        'draft' => 'bg-slate-400',
                        'hangus' => 'bg-rose-500',
                        default => 'bg-sky-500',
                    };

                    $recipient = $letter->field_values['penerima'] ?? ($letter->field_values['instansi'] ?? '—');
                    $tag = $letter->template?->name ?? 'Surat Keluar';

                    $itemsByDate[$d][] = [
                        'type' => 'surat_keluar',
                        'id' => $letter->id,
                        'number' => $letter->formatted_number ?? 'Draf',
                        'perihal' => $letter->perihal ?: 'Tanpa Perihal',
                        'status' => $statusKey,
                        'status_label' => $statusLabel,
                        'dot_color' => $dotColor,
                        'actor' => $letter->author?->name ?? '—',
                        'recipient' => $recipient,
                        'tag' => $tag,
                        'has_file' => true,
                        'date' => $d,
                    ];
                }
            }
        }

        // 2. Ambil Surat Masuk
        if ($this->filterType === 'all' || $this->filterType === 'surat_masuk') {
            $smQuery = SuratMasuk::where('workspace_id', $ws->id);

            if ($this->dateReference === 'event_date') {
                $smQuery->whereNotNull('tanggal_acara_atau_deadline')
                    ->whereBetween('tanggal_acara_atau_deadline', [$startOfCalendar->toDateString(), $endOfCalendar->toDateString()]);
            } else {
                $smQuery->whereBetween('tanggal_surat', [$startOfCalendar->toDateString(), $endOfCalendar->toDateString()]);
            }

            $suratMasuks = $smQuery->get();

            foreach ($suratMasuks as $sm) {
                $d = $this->dateReference === 'event_date'
                    ? $sm->tanggal_acara_atau_deadline?->toDateString()
                    : $sm->tanggal_surat?->toDateString();

                if ($d) {
                    $statusKey = is_object($sm->status) ? $sm->status->value : (string) $sm->status;
                    $statusLabel = match ($statusKey) {
                        'baru' => 'Baru',
                        'menunggu_disposisi' => 'Menunggu Disposisi',
                        'didisposisi' => 'Didisposisi',
                        'selesai' => 'Selesai',
                        default => ucfirst(str_replace('_', ' ', $statusKey)),
                    };
                    $dotColor = match ($statusKey) {
                        'selesai' => 'bg-emerald-500',
                        'menunggu_disposisi' => 'bg-amber-500',
                        'didisposisi' => 'bg-indigo-500',
                        'baru' => 'bg-sky-500',
                        default => 'bg-slate-400',
                    };

                    $itemsByDate[$d][] = [
                        'type' => 'surat_masuk',
                        'id' => $sm->id,
                        'number' => $sm->nomor_agenda,
                        'perihal' => $sm->perihal ?: 'Tanpa Perihal',
                        'status' => $statusKey,
                        'status_label' => $statusLabel,
                        'dot_color' => $dotColor,
                        'actor' => $sm->pengirim,
                        'recipient' => 'Sekretariat',
                        'tag' => $sm->pengirim,
                        'has_file' => ! empty($sm->file_path),
                        'has_link' => ! empty($sm->document_url),
                        'date' => $d,
                    ];
                }
            }
        }

        // 3. Bangun Grid Kalender (Minggu -> Sabtu)
        $weeks = [];
        $cursor = $startOfCalendar->copy();
        while ($cursor->lte($endOfCalendar)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateStr = $cursor->toDateString();
                $dayItems = $itemsByDate[$dateStr] ?? [];
                $isFirstOfMonth = $cursor->day === 1;

                $week[] = [
                    'date' => $dateStr,
                    'day' => (int) $cursor->format('j'),
                    'dayLabel' => $isFirstOfMonth ? $cursor->translatedFormat('M j') : (string) $cursor->day,
                    'isCurrentMonth' => $cursor->month === $this->month,
                    'isToday' => $dateStr === $todayStr,
                    'items' => $dayItems,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // 4. Data Modal
        $previewData = null;
        if ($this->activeModal === 'pdf-preview' && $this->previewId) {
            if ($this->previewType === 'surat_keluar') {
                $previewData = Letter::with(['activeNumber', 'author', 'template', 'suratMasuk'])
                    ->where('workspace_id', $ws->id)
                    ->find($this->previewId);
            } else {
                $previewData = SuratMasuk::with(['createdBy', 'linkedSuratKeluar', 'disposisis'])
                    ->where('workspace_id', $ws->id)
                    ->find($this->previewId);
            }
        }

        $moreModalItems = [];
        if ($this->activeModal === 'day-list' && $this->moreModalDate) {
            $moreModalItems = $itemsByDate[$this->moreModalDate] ?? [];
        }

        $user = auth()->user();
        $canCreateLetter = $user && ($user->can('surat_keluar.create') || ($ws->roleOf($user)?->canWrite() ?? false));
        $canCreateSuratMasuk = $user && ($user->can('surat_masuk.create') || ($ws->roleOf($user)?->canWrite() ?? false));

        $selectedMobileDateStr = $this->selectedMobileDate ?? now()->toDateString();
        $selectedMobileItems = $itemsByDate[$selectedMobileDateStr] ?? [];

        return view('livewire.calendar.calendar-index', [
            'workspace' => $ws,
            'currentMonth' => $currentMonth,
            'weeks' => $weeks,
            'previewData' => $previewData,
            'moreModalItems' => $moreModalItems,
            'selectedMobileDate' => $selectedMobileDateStr,
            'selectedMobileItems' => $selectedMobileItems,
            'canCreateLetter' => $canCreateLetter,
            'canCreateSuratMasuk' => $canCreateSuratMasuk,
        ])->layout('components.layouts.app', [
            'title' => 'Kalender Persuratan',
            'breadcrumbs' => [
                ['label' => 'Persuratan', 'url' => route('dashboard')],
                ['label' => 'Kalender Persuratan'],
            ],
        ]);
    }
}
