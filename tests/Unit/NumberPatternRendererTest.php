<?php

namespace Tests\Unit;

use App\Exceptions\DomainException;
use App\Models\Workspace;
use App\Services\Numbering\NumberPatternRenderer;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class NumberPatternRendererTest extends TestCase
{
    public function test_it_renders_standard_pattern_with_roman_month_and_padding(): void
    {
        $renderer = new NumberPatternRenderer;
        $ws = new Workspace([
            'name' => 'HIMATRIS',
            'slug' => 'himatris',
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'HIMATRIS',
            'seq_padding' => 3,
        ]);

        $formatted = $renderer->render(
            '{seq}/{kode}/{bulan_romawi}/{tahun}',
            $ws,
            12,
            Carbon::parse('2025-06-15')
        );

        $this->assertSame('012/HIMATRIS/VI/2025', $formatted);
    }

    public function test_it_supports_nomor_urut_alias_and_numeric_month(): void
    {
        $renderer = new NumberPatternRenderer;
        $ws = new Workspace([
            'name' => 'OASIS',
            'slug' => 'oasis',
            'number_pattern' => '{nomor_urut}/{kode}/{bulan}/{tahun}',
            'number_code' => 'OASIS',
            'seq_padding' => 4,
        ]);

        $formatted = $renderer->render(
            '{nomor_urut}/{kode}/{bulan}/{tahun}',
            $ws,
            7,
            Carbon::parse('2025-11-03')
        );

        $this->assertSame('0007/OASIS/11/2025', $formatted);
    }

    public function test_it_rejects_unknown_tokens(): void
    {
        $renderer = new NumberPatternRenderer;

        $this->expectException(DomainException::class);
        $renderer->validate('{seq}/{foo}/{tahun}');
    }

    public function test_it_rejects_pattern_without_sequence_token(): void
    {
        $renderer = new NumberPatternRenderer;

        $this->expectException(DomainException::class);
        $renderer->validate('{kode}/{bulan_romawi}/{tahun}');
    }
}
