<?php

namespace App\Services\Numbering;

use App\Exceptions\DomainException;
use App\Models\Workspace;
use Carbon\CarbonInterface;

class NumberPatternRenderer
{
    public const ALLOWED_TOKENS = [
        'seq',
        'nomor', // alias for {seq}
        'nomor_urut', // alias for {seq}
        'kode',
        'bulan',
        'bulan_romawi',
        'tahun',
        'kategori',
        'jenis_surat', // alias for {kategori}
        'parameter',
        'kode_kegiatan',
        'panitia',
        'kepanitiaan', // alias for {panitia}
    ];

    private const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    /**
     * Validates that a pattern contains at least {seq} (or {nomor_urut}) and only allowed tokens.
     *
     * @throws DomainException
     */
    public function validate(string $pattern): void
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            throw new DomainException('PATTERN_INVALID', 'Pola nomor surat tidak boleh kosong.', 422);
        }

        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $pattern, $matches);
        $tokens = $matches[1] ?? [];

        if (empty($tokens)) {
            throw new DomainException('PATTERN_INVALID', 'Pola nomor harus mengandung minimal satu token seperti {seq}.', 422);
        }

        $unknown = array_values(array_diff($tokens, self::ALLOWED_TOKENS));
        if (! empty($unknown)) {
            $list = implode(', ', array_map(fn ($t) => "{{$t}}", $unknown));
            throw new DomainException(
                'PATTERN_INVALID',
                "Token pola penomoran tidak dikenal: {$list}. Token yang didukung: {seq}, {kode}, {kategori}, {parameter}, {kode_kegiatan}, {panitia}, {bulan}, {bulan_romawi}, {tahun}.",
                422,
                ['unknown_tokens' => $unknown]
            );
        }

        if (! in_array('seq', $tokens, true) && ! in_array('nomor', $tokens, true) && ! in_array('nomor_urut', $tokens, true)) {
            throw new DomainException('PATTERN_INVALID', 'Pola nomor wajib menyertakan token {seq}, {nomor}, atau {nomor_urut}.', 422);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(
        string $pattern,
        Workspace $workspace,
        int $sequence,
        CarbonInterface $date,
        array $context = [],
    ): string {
        $this->validate($pattern);

        $padding = max(1, (int) ($workspace->seq_padding ?? config('suratinaja.numbering.default_padding', 3)));
        $seqFormatted = str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
        $month = (int) $date->format('n');

        $kategori = (string) ($context['kategori'] ?? $context['jenis_surat'] ?? 'SU');
        $parameter = (string) ($context['parameter'] ?? '-i');
        $kodeKegiatan = (string) ($context['kode_kegiatan'] ?? 'JKBFEST');
        $panitia = trim($kodeKegiatan) !== '' ? 'PAN-'.strtoupper($kodeKegiatan) : (string) ($workspace->number_code ?: 'HIMATRIS');

        $replacements = [
            '{seq}' => $seqFormatted,
            '{nomor}' => $seqFormatted,
            '{nomor_urut}' => $seqFormatted,
            '{kode}' => (string) ($workspace->number_code ?: strtoupper($workspace->slug)),
            '{bulan}' => $date->format('m'),
            '{bulan_romawi}' => self::ROMAN_MONTHS[$month] ?? 'I',
            '{tahun}' => $date->format('Y'),
            '{kategori}' => $kategori,
            '{jenis_surat}' => $kategori,
            '{parameter}' => $parameter,
            '{kode_kegiatan}' => $kodeKegiatan,
            '{panitia}' => $panitia,
            '{kepanitiaan}' => $panitia,
        ];

        return strtr($pattern, $replacements);
    }
}
