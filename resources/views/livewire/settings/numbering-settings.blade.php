<div class="max-w-3xl">
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title>Konfigurasi Pola Penomoran — {{ $workspace->name }}</x-ui.card-title>
            <x-ui.card-description>
                Tentukan format nomor surat otomatis untuk workspace ini (DEC-002). Penomoran dikunci secara atomik di dalam transaksi database.
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-6 space-y-6">
            <div class="rounded-lg border border-border bg-muted/40 p-4">
                <p class="text-xs font-medium text-muted-foreground">Pratinjau Nomor Surat (Urutan ke-12)</p>
                @if ($preview)
                    <p class="mt-1 font-mono text-lg font-semibold tracking-tight text-foreground">{{ $preview }}</p>
                @else
                    <p class="mt-1 text-xs text-destructive">{{ $previewError }}</p>
                @endif
            </div>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-ui.label for="numberPattern">String Pola Nomor</x-ui.label>
                    <x-ui.input
                        id="numberPattern"
                        wire:model.live="numberPattern"
                        class="mt-1.5 font-mono"
                        placeholder="{seq}/{kode}/{bulan_romawi}/{tahun}"
                    />
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        Token yang didukung:
                        <code class="font-mono text-foreground">{seq}</code> (atau <code class="font-mono text-foreground">{nomor_urut}</code>),
                        <code class="font-mono text-foreground">{kode}</code>,
                        <code class="font-mono text-foreground">{bulan}</code> (01–12),
                        <code class="font-mono text-foreground">{bulan_romawi}</code> (I–XII),
                        <code class="font-mono text-foreground">{tahun}</code>.
                    </p>
                    <x-ui.form-error for="numberPattern" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="numberCode">Kode Organisasi ({kode})</x-ui.label>
                        <x-ui.input id="numberCode" wire:model.live="numberCode" class="mt-1.5 font-mono" placeholder="HIMATRIS" />
                        <x-ui.form-error for="numberCode" />
                    </div>

                    <div>
                        <x-ui.label for="seqPadding">Digit Padding {seq}</x-ui.label>
                        <x-ui.select id="seqPadding" wire:model.live="seqPadding" class="mt-1.5 font-mono">
                            <option value="1">1 digit (12)</option>
                            <option value="2">2 digit (12)</option>
                            <option value="3">3 digit (012)</option>
                            <option value="4">4 digit (0012)</option>
                        </x-ui.select>
                        <x-ui.form-error for="seqPadding" />
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <x-ui.button type="submit">
                        <x-lucide-save class="size-4" />
                        <span>Simpan Pola Nomor</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>
</div>
