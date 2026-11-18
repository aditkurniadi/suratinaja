<?php

namespace App\Livewire;

use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Models\Letter;
use App\Models\LetterNumber;
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

        $counts = [
            'draft' => Letter::where('status', LetterStatus::Draft->value)->count(),
            'menunggu_ttd' => Letter::where('status', LetterStatus::MenungguTtd->value)->count(),
            'selesai' => Letter::where('status', LetterStatus::Selesai->value)->count(),
            'hangus' => LetterNumber::where('status', NumberStatus::Hangus->value)->count(),
        ];

        $recentLetters = Letter::with(['activeNumber', 'latestNumber', 'template', 'author'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('livewire.dashboard', [
            'workspace' => $workspace,
            'role' => $role,
            'counts' => $counts,
            'recentLetters' => $recentLetters,
        ]);
    }
}
