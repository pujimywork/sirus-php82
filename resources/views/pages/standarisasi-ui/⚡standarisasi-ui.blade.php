<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Pagination\LengthAwarePaginator;

// Halaman acuan/standarisasi UI internal (style guide). Struktur editorial mengikuti
// bahasa desain Claude, tapi PALET disesuaikan ke brand RSI Madinah:
// kanvas terang + hijau #157547 + lime #A1CD3A + permukaan hijau-gelap, full sans (Source Sans 3).
// Kelas .ds-* + token warnanya hidup di resources/css/app.css dan ber-sumber
// dari tailwind.config.cjs via theme() — satu sumber kebenaran (bukan hardcode).
new class extends Component {
    use WithPagination;
    use WithFileUploads;

    // State kecil untuk men-demo komponen form yang interaktif (live preview).
    public $demoFile = null; // untuk <x-file-upload>
    public ?string $demoSignature = null; // dataURL dari <x-signature.signature-pad>
    public string $demoText = '';
    public string $demoSelect = '';
    public ?string $demoNumber = '0';
    public bool $demoToggle = true;
    public bool $demoCheck = true;
    public string $demoRadio = 'rj';
    public string $demoTab = 'rj';
    public int $demoPerPage = 5; // dikendalikan <x-select-input> di demo pagination

    // Data contoh untuk demonstrasi model tabel standar.
    public array $demoRows = [
        ['id' => 'A001', 'nama' => 'Klinik Umum',     'kode' => 'UMU', 'status' => 1],
        ['id' => 'A002', 'nama' => 'Klinik Gigi',     'kode' => 'GIG', 'status' => 1],
        ['id' => 'A003', 'nama' => 'Klinik Anak',     'kode' => 'ANK', 'status' => 0],
    ];

    // Dipanggil oleh <x-toolbar-refresh-reset> (tombol Reset) — no-op untuk demo.
    public function resetFilters(): void
    {
        $this->reset('demoText', 'demoSelect');
    }

    // Penerima dataURL dari <x-signature.signature-pad> — no-op untuk demo.
    public function setSignature($data = null): void
    {
        // di halaman nyata: simpan dataURL (base64 PNG) ke property/DB.
        $this->demoSignature = $data;
    }

    // Demo tombol Cetak — di halaman nyata: buka view print / generate PDF.
    public function cetakDemo(): void
    {
        // contoh: $this->dispatch('open-print', url: route('...print'));
    }

    // Paginator ASLI untuk demo komponen pagination (Livewire WithPagination).
    // Dataset palsu 14 baris, 5 per halaman — tombol prev/next/nomor benar berfungsi.
    // Reset ke halaman 1 saat per-halaman berubah (pola standar list).
    public function updatedDemoPerPage(): void
    {
        $this->resetPage();
    }

    public function demoPaginator(): LengthAwarePaginator
    {
        $perPage = max(1, (int) $this->demoPerPage);
        $total = 14;
        $page = (int) ($this->paginators['page'] ?? 1);

        $all = collect(range(1, $total))->map(fn ($i) => [
            'no' => $i,
            'nama' => "Pasien contoh {$i}",
        ]);

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }
};
?>

@push('styles')
@endpush

<div>
    {{-- v2: full sans (Source Sans 3) + mono untuk angka/kode; serif dipensiunkan --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />


    <div class="ds">
        <div class="ds-section">

            {{-- ============ HERO ============ --}}
            <header class="ds-band">
                <div class="flex items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="ds-spike"></span>
                        <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                        <span class="ds-body-sm" style="color:var(--muted-soft)">/ Standarisasi UI</span>
                    </div>
                    {{-- v2: toggle mode gelap/terang (sama mekanisme app — .dark + localStorage) --}}
                    <x-theme-toggle />
                </div>
                <div class="grid items-center grid-cols-1 gap-12 lg:grid-cols-2">
                    <div>
                        <div class="ds-eyebrow mb-4">Design System Internal</div>
                        <h1 class="ds-display-xl">Standarisasi<br>antarmuka kita.</h1>
                        <p class="ds-body-md mt-6" style="max-width:46ch; color:var(--body-strong)">
                            Satu acuan warna, tipografi, dan komponen — supaya setiap halaman SIMRS
                            terasa konsisten, tenang, dan mudah dibaca. Memakai warna brand RSI&nbsp;Madinah:
                            kanvas terang, aksen <em>hijau</em> &amp; lime, dan permukaan hijau-gelap
                            untuk panel data.
                        </p>
                        <div class="flex flex-wrap gap-3 mt-8">
                            <a href="#warna" class="ds-btn ds-btn-primary">Lihat token warna</a>
                            <a href="#komponen" class="ds-btn ds-btn-secondary">Komponen</a>
                            <a href="#v2" class="ds-btn ds-btn-secondary">Standar v2</a>
                        </div>
                    </div>

                    {{-- Mockup code window (dark product chrome) --}}
                    <div class="ds-card-dark" style="padding:0; overflow:hidden">
                        <div class="flex items-center gap-2 px-4 py-3" style="background:var(--surface-dark-soft)">
                            <span style="width:12px;height:12px;border-radius:9999px;background:#f25f57;display:inline-block"></span>
                            <span style="width:12px;height:12px;border-radius:9999px;background:#fabc2e;display:inline-block"></span>
                            <span style="width:12px;height:12px;border-radius:9999px;background:#5db872;display:inline-block"></span>
                        </div>
                        <pre class="ds-code" style="margin:0; padding:24px; color:var(--on-dark-soft); overflow-x:auto"><span style="color:#8b948c"># token desain — pakai di mana saja</span>
<span style="color:var(--accent-teal)">canvas</span>  = <span style="color:var(--accent-amber)">"#f6f8f5"</span>  <span style="color:#8b948c"># kanvas terang</span>
<span style="color:var(--accent-teal)">primary</span> = <span style="color:var(--accent-amber)">"#157547"</span>  <span style="color:#8b948c"># hijau brand / CTA</span>
<span style="color:var(--accent-teal)">accent</span>  = <span style="color:var(--accent-amber)">"#A1CD3A"</span>  <span style="color:#8b948c"># lime</span>
<span style="color:var(--accent-teal)">surface</span> = <span style="color:var(--accent-amber)">"#14201a"</span>  <span style="color:#8b948c"># panel gelap</span></pre>
                    </div>
                </div>
            </header>

            {{-- ============ WARNA ============ --}}
            <section id="warna" class="ds-band">
                <div class="ds-eyebrow mb-3">01 — Colors</div>
                <h2 class="ds-display-lg mb-2">Palet warna</h2>
                <p class="ds-body-md mb-10" style="max-width:56ch">
                    Trinitas brand: kanvas <strong>terang</strong> sebagai dasar, <strong>hijau&nbsp;#157547</strong>
                    sebagai voltase brand (hemat di tombol, royal di kartu callout) dengan companion
                    <strong>lime&nbsp;#A1CD3A</strong>, dan <strong>hijau-gelap</strong> untuk permukaan panel data.
                    Jangan menambah warna keempat.
                </p>
                <div class="ds-card-outline mb-8" style="padding:16px 20px">
                    <span class="ds-spike" style="vertical-align:middle"></span>
                    <span class="ds-body-sm" style="color:var(--body-strong)">
                        Semua token ini terdaftar di <code class="ds-code" style="color:var(--primary)">tailwind.config.cjs</code> —
                        pakai langsung sebagai utility: <code class="ds-code">bg-canvas</code>,
                        <code class="ds-code">text-ink</code>, <code class="ds-code">border-hairline</code>,
                        <code class="ds-code">bg-surface-dark</code>, <code class="ds-code">font-sans</code>,
                        <code class="ds-code">text-display-lg</code>, <code class="ds-code">py-section</code>.
                    </span>
                </div>

                @php
                    // [nama, hex, kegunaan, class Tailwind ('—' jika belum ada token)]
                    $groups = [
                        'Brand & Accent' => [
                            ['Hijau / Primary', '#157547', 'CTA utama, callout', 'bg-brand-green'],
                            ['Hijau Active', '#0f5634', 'Hover / press', 'hover:bg-brand-green/90'],
                            ['Hijau Disabled', '#cfe0d3', 'Status nonaktif', '—'],
                            ['Accent Lime', '#A1CD3A', 'Highlight / badge', 'bg-brand-lime'],
                            ['Hijau Soft', '#4f9e6a', 'Indikator sekunder', '—'],
                        ],
                        'Surface' => [
                            ['Canvas', '#f6f8f5', 'Lantai halaman', 'bg-canvas'],
                            ['Surface Soft', '#eef2ec', 'Pembatas band', 'bg-surface-soft'],
                            ['Surface Card', '#e6ece4', 'Kartu fitur', 'bg-surface-card'],
                            ['Green Strong', '#dbe6d6', 'Tab terpilih', 'bg-surface-strong'],
                            ['Surface Dark', '#14201a', 'Panel data / footer', 'bg-surface-dark'],
                            ['Dark Elevated', '#1e2b23', 'Kartu di atas dark', 'bg-surface-dark-elevated'],
                        ],
                        'Text' => [
                            ['Ink', '#13201a', 'Judul & teks utama', 'text-ink'],
                            ['Body Strong', '#233029', 'Paragraf lead', 'text-body-strong'],
                            ['Body', '#3c463f', 'Teks berjalan', 'text-body'],
                            ['Muted', '#69736b', 'Sub-judul', 'text-muted'],
                            ['Muted Soft', '#8b948c', 'Caption / fine-print', 'text-muted-soft'],
                        ],
                        'Semantic' => [
                            ['Success', '#3fae6a', 'Status tersedia', 'text-green-600'],
                            ['Warning', '#d4a017', 'Peringatan', 'text-yellow-500'],
                            ['Error', '#c64545', 'Validasi gagal', 'text-red-600'],
                        ],
                    ];
                @endphp

                @foreach ($groups as $title => $items)
                    <div class="mb-8">
                        <div class="ds-caption-up mb-4" style="color:var(--muted)">{{ $title }}</div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach ($items as [$name, $hex, $use, $tw])
                                <div>
                                    <div class="ds-swatch" style="background:{{ $hex }}"></div>
                                    <div class="ds-title-sm mt-3">{{ $name }}</div>
                                    <div class="ds-code" style="font-size:12px;color:var(--muted)">{{ $hex }}</div>
                                    <div class="ds-code mt-0.5" style="font-size:11px;color:var(--primary)">{{ $tw }}</div>
                                    <div class="ds-body-sm" style="font-size:12px;color:var(--muted-soft)">{{ $use }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- ============ TIPOGRAFI ============ --}}
            <section id="tipografi" class="ds-band">
                <div class="ds-eyebrow mb-3">02 — Typography</div>
                <h2 class="ds-display-lg mb-2">Tipografi</h2>
                <p class="ds-body-md mb-10" style="max-width:56ch">
                    Sejak v2 seluruh teks <strong>full sans — Source Sans 3</strong> (humanis, x-height tinggi,
                    mudah dibaca segala umur); <strong>serif dipensiunkan dari aplikasi</strong>. Display
                    memakai sans bobot 700 dengan letter-spacing negatif. Lihat skala lengkap di seksi
                    <a href="#v2" class="text-brand-green" style="text-decoration:underline">Standar UI v2</a>.
                </p>

                @php
                    // [token, class .ds, spesifikasi(size/weight/tracking), contoh, class Tailwind]
                    $scale = [
                        ['display-xl', 'ds-display-xl', '52 / 700 / -2%', 'Sistem Informasi RS', 'font-sans font-bold text-display-xl'],
                        ['display-lg', 'ds-display-lg', '38 / 700 / -2%', 'Section heads', 'font-sans font-bold text-display-lg'],
                        ['display-md', 'ds-display-md', '30 / 700 / -1%', 'Nama modul', 'font-sans font-bold text-display-md'],
                        ['display-sm', 'ds-display-sm', '24 / 700 / -1%', 'Callout headline', 'font-sans font-bold text-display-sm'],
                        ['title-lg', 'ds-title-lg', '20 / 600 / 0', 'Label besar', 'text-xl font-semibold'],
                        ['title-md', 'ds-title-md', '17 / 600 / 0', 'Judul kartu', 'text-lg font-semibold'],
                        ['body-md', 'ds-body-md', '15 / 400 / 0', 'Teks berjalan default', 'text-[15px]'],
                        ['body-sm', 'ds-body-sm', '13.5 / 400 / 0', 'Footer / fine-print', 'text-sm'],
                        ['code', 'ds-code', '13 / 400 / mono', 'console.log("halo")', 'font-mono text-sm'],
                    ];
                @endphp
                <div class="ds-card-outline" style="padding:0;overflow:hidden">
                    <div class="overflow-x-auto">
                        <table class="ds-table">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>px / weight / tracking</th>
                                    <th>Class Tailwind</th>
                                    <th>Contoh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($scale as [$token, $cls, $meta, $sample, $tw])
                                    <tr>
                                        <td class="ds-td-token">{{ $token }}</td>
                                        <td class="ds-td-meta">{{ $meta }}</td>
                                        <td class="ds-td-class">{{ $tw }}</td>
                                        <td><span class="{{ $cls }}" style="color:var(--ink)">{{ $sample }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ============ KOMPONEN ============ --}}
            <section id="komponen" class="ds-band">
                <div class="ds-eyebrow mb-3">03 — Components</div>
                <h2 class="ds-display-lg mb-2">Komponen</h2>
                <p class="ds-body-md mb-10" style="max-width:62ch">
                    Komponen <strong>asli</strong> yang dipakai di seluruh SIMRS (komponen Blade <code class="ds-code">&lt;x-…&gt;</code>),
                    di-render langsung di sini — bukan tiruan. Acuan lengkap: <code class="ds-code">docs/standar-komponen-tombol.md</code>
                    &amp; <code class="ds-code">docs/standar-ui-komponen.md</code>.
                </p>

                {{-- ===== TOMBOL ===== --}}
                <div class="ds-card-outline mb-6">
                    <div class="ds-caption-up mb-5" style="color:var(--muted)">Tombol — &lt;x-*-button&gt;</div>
                    <div class="flex flex-wrap items-center gap-3 mb-5">
                        <x-primary-button type="button">Primary</x-primary-button>
                        <x-secondary-button>Secondary</x-secondary-button>
                        <x-success-button>Success</x-success-button>
                        <x-danger-button>Danger</x-danger-button>
                        <x-warning-button>Warning</x-warning-button>
                        <x-info-button>Info</x-info-button>
                        <x-outline-button>Outline</x-outline-button>
                        <x-ghost-button>Ghost</x-ghost-button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mb-5">
                        <x-primary-button type="button" :disabled="true">Disabled</x-primary-button>
                        <x-now-button />
                        <x-toolbar-refresh-reset />
                        <x-confirm-button variant="danger" action="$refresh" title="Hapus data?"
                            message="Tindakan ini tidak bisa dibatalkan." confirmText="Hapus">Konfirmasi (Hapus)</x-confirm-button>

                        {{-- Tombol Cetak — ikon printer + state loading "Mencetak..." (pola modul-dokumen) --}}
                        <x-primary-button type="button" wire:click="cetakDemo"
                            wire:loading.attr="disabled" wire:target="cetakDemo">
                            <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="cetakDemo">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </span>
                            <span class="inline-flex items-center gap-2" wire:loading wire:target="cetakDemo">
                                <x-loading /> Mencetak...
                            </span>
                        </x-primary-button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach (['green','lime','red','yellow','blue','gray'] as $c)
                            <x-icon-button :color="$c" type="button" title="icon-button {{ $c }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </x-icon-button>
                        @endforeach
                        <span class="ds-body-sm ml-1" style="color:var(--muted-soft)">&lt;x-icon-button color="…"&gt;</span>
                    </div>
                    {{-- Ikon umum: hapus baris (sampah) & tutup — pola eresep/iDRG --}}
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <x-icon-button color="red" type="button" title="Hapus baris">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </x-icon-button>
                        <x-icon-button color="gray" type="button" title="Tutup">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </x-icon-button>
                        <span class="ds-body-sm ml-1" style="color:var(--muted-soft)">
                            Hapus baris (sampah, <code class="ds-code">color="red"</code> + <code class="ds-code">wire:confirm</code>) · Tutup (<code class="ds-code">color="gray"</code>)
                        </span>
                    </div>
                </div>

                {{-- ===== BADGE ===== --}}
                <div class="ds-card-outline mb-6">
                    <div class="ds-caption-up mb-5" style="color:var(--muted)">Badge — &lt;x-badge variant="…"&gt;</div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach (['brand','alternative','gray','success','warning','danger','info','purple'] as $v)
                            <x-badge :variant="$v">{{ ucfirst($v) }}</x-badge>
                        @endforeach
                    </div>
                </div>

                {{-- ===== FORM ===== --}}
                <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-5" style="color:var(--muted)">Input teks &amp; pilihan</div>
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Nama / No. RM" :required="true" />
                                <x-text-input class="block w-full mt-1" wire:model.blur="demoText" placeholder="Ketik nama pasien…" />
                            </div>
                            <div>
                                <x-input-label value="Unit Layanan" />
                                <x-select-input class="block w-full mt-1" wire:model.live="demoSelect">
                                    <option value="">— pilih —</option>
                                    <option value="rj">Rawat Jalan</option>
                                    <option value="ugd">UGD</option>
                                    <option value="ri">Rawat Inap</option>
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label value="Berat Badan (kg)" />
                                <x-text-input-number class="block w-full mt-1" wire:model="demoNumber" />
                            </div>
                            <div>
                                <x-input-label value="Catatan" />
                                <x-textarea class="block w-full mt-1" rows="2" placeholder="Catatan tambahan…" />
                            </div>
                            <div>
                                <x-input-label value="Contoh state error" />
                                <x-text-input class="block w-full mt-1" :error="true" placeholder="Field tidak valid" />
                                <x-input-error class="mt-1" :messages="['Field ini wajib diisi.']" />
                            </div>
                        </div>
                    </div>

                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-5" style="color:var(--muted)">Toggle, checkbox, radio</div>
                        <div class="space-y-5">
                            <x-toggle wire:model.live="demoToggle" :trueValue="true" :falseValue="false" label="Status Aktif" />
                            <x-check-box label="Pasien menyetujui (consent)" wire:model.live="demoCheck" :checked="$demoCheck" />
                            <div>
                                <x-input-label value="Jenis layanan" class="mb-2" />
                                <div class="space-y-2">
                                    <x-radio-button wire:model.live="demoRadio" value="rj" label="Rawat Jalan" :checked="$demoRadio==='rj'" />
                                    <x-radio-button wire:model.live="demoRadio" value="ugd" label="UGD" :checked="$demoRadio==='ugd'" />
                                    <x-radio-button wire:model.live="demoRadio" value="ri" label="Rawat Inap" :checked="$demoRadio==='ri'" />
                                </div>
                            </div>
                            <div class="ds-body-sm pt-2" style="color:var(--muted-soft);border-top:1px solid var(--hairline)">
                                Nilai terbaca:
                                <code class="ds-code">toggle={{ $demoToggle ? 'true' : 'false' }}</code>,
                                <code class="ds-code">radio={{ $demoRadio }}</code>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== STATE DISABLE ===== --}}
                <div class="ds-card-outline mb-6">
                    <div class="ds-caption-up mb-5" style="color:var(--muted)">State disable — :disabled="true"</div>
                    <div class="flex flex-wrap items-center gap-3 mb-5">
                        <x-primary-button type="button" :disabled="true">Primary</x-primary-button>
                        <x-secondary-button :disabled="true">Secondary</x-secondary-button>
                        <x-success-button :disabled="true">Success</x-success-button>
                        <x-danger-button :disabled="true">Danger</x-danger-button>
                        <x-warning-button :disabled="true">Warning</x-warning-button>
                        <x-info-button :disabled="true">Info</x-info-button>
                        <x-outline-button :disabled="true">Outline</x-outline-button>
                        <x-now-button :disabled="true" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Input disabled" />
                            <x-text-input class="block w-full mt-1" :disabled="true" placeholder="Tidak bisa diisi" />
                        </div>
                        <div>
                            <x-input-label value="Select disabled" />
                            <x-select-input class="block w-full mt-1" :disabled="true">
                                <option>— terkunci —</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Toggle disabled" class="mb-2" />
                            <x-toggle :trueValue="true" :falseValue="false" label="Aktif" :disabled="true" />
                        </div>
                    </div>
                    <p class="ds-body-sm mt-4" style="color:var(--muted-soft)">
                        Pola seragam: <code class="ds-code">opacity-50 + cursor-not-allowed + pointer-events-none</code>.
                    </p>
                </div>

                {{-- ===== MODEL TABEL ===== --}}
                <div class="mb-6">
                    <div class="ds-caption-up mb-3" style="color:var(--muted)">Model tabel data</div>
                    <div class="ds-card-outline" style="padding:0;overflow:hidden">
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>Kode</th>
                                        <th>Status</th>
                                        <th class="ds-c">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($demoRows as $row)
                                        <tr>
                                            <td class="ds-td-token">{{ $row['id'] }}</td>
                                            <td class="ds-td-strong">{{ $row['nama'] }}</td>
                                            <td class="ds-td-token">{{ $row['kode'] }}</td>
                                            <td>
                                                <x-badge :variant="$row['status'] === 1 ? 'success' : 'gray'">
                                                    {{ $row['status'] === 1 ? 'Aktif' : 'Nonaktif' }}
                                                </x-badge>
                                            </td>
                                            <td class="ds-c">
                                                <div class="flex justify-center gap-2">
                                                    <x-secondary-button type="button" class="px-2.5 py-1.5 text-sm">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                        Edit
                                                    </x-secondary-button>
                                                    <x-confirm-button variant="danger" action="$refresh" title="Hapus data?"
                                                        message="Yakin hapus {{ $row['nama'] }}?" confirmText="Ya, hapus"
                                                        class="px-2.5 py-1.5 text-sm">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="ds-table-foot">Menampilkan {{ count($demoRows) }} dari {{ count($demoRows) }} data</div>
                    </div>
                </div>

                {{-- ===== PAGINATION ===== --}}
                @php $pg = $this->demoPaginator(); @endphp
                <div class="mb-6">
                    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                        <div class="ds-caption-up" style="color:var(--muted)">Pagination · &#123;&#123; $rows-&gt;links() &#125;&#125;</div>
                        {{-- Per-halaman pakai komponen asli <x-select-input> (mengontrol pagination) --}}
                        <div class="flex items-center gap-2">
                            <span class="ds-body-sm" style="color:var(--muted)">Per halaman</span>
                            <div class="w-20">
                                <x-select-input wire:model.live="demoPerPage" class="block w-full">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="14">14</option>
                                </x-select-input>
                            </div>
                        </div>
                    </div>
                    <div class="ds-card-outline" style="padding:0;overflow:hidden">
                        <div class="overflow-x-auto">
                            <table class="ds-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pg as $item)
                                        <tr>
                                            <td class="ds-td-token">{{ $item['no'] }}</td>
                                            <td class="ds-td-strong">{{ $item['nama'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- Bar pagination ber-tema — prev/next/nomor berfungsi (wire:click WithPagination) --}}
                        <div class="ds-table-foot" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <span>Menampilkan {{ $pg->firstItem() }}–{{ $pg->lastItem() }} dari {{ $pg->total() }} data</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="ds-page-btn" wire:click="previousPage" @disabled($pg->onFirstPage())>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                                @for ($p = 1; $p <= $pg->lastPage(); $p++)
                                    <button type="button" wire:click="gotoPage({{ $p }})"
                                        class="ds-page-btn {{ $p === $pg->currentPage() ? 'ds-page-btn-active' : '' }}">{{ $p }}</button>
                                @endfor
                                <button type="button" class="ds-page-btn" wire:click="nextPage" @disabled(!$pg->hasMorePages())>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                        Klik nomor / panah untuk pindah halaman (live, tanpa reload).
                        Di halaman app sungguhan cukup <code class="ds-code">&#123;&#123; $rows-&gt;links() &#125;&#125;</code>.
                    </p>
                </div>

                {{-- ===== TAB ===== --}}
                <div class="mb-6">
                    <div class="ds-caption-up mb-3" style="color:var(--muted)">Tab · nav border-bawah, aktif hijau brand</div>
                    <div class="ds-card-outline" style="padding:0;overflow:hidden">
                        <div class="flex px-4" style="border-bottom:1px solid var(--hairline)">
                            @foreach (['rj' => 'Rawat Jalan', 'ugd' => 'UGD', 'ri' => 'Rawat Inap'] as $key => $label)
                                <button type="button" wire:click="$set('demoTab','{{ $key }}')"
                                    class="px-4 py-2.5 -mb-px text-sm font-medium transition border-b-2"
                                    style="{{ $demoTab === $key ? 'color:var(--primary);border-color:var(--primary)' : 'color:var(--muted);border-color:transparent' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <div class="p-6 ds-body-md">
                            @if ($demoTab === 'rj')
                                Konten tab <strong>Rawat Jalan</strong>.
                            @elseif ($demoTab === 'ugd')
                                Konten tab <strong>UGD</strong>.
                            @else
                                Konten tab <strong>Rawat Inap</strong>.
                            @endif
                        </div>
                    </div>
                    <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                        Aktif ditandai garis bawah + teks hijau brand. Untuk banyak tab dalam satu baris yang bisa di-scroll, bungkus dengan <code class="ds-code">&lt;x-scrollable-tabs&gt;</code>.
                    </p>
                </div>

                {{-- ===== MODAL ===== --}}
                <div class="ds-frame mb-6" x-data>
                    <div class="ds-frame-label">Modal · &lt;x-modal name="…"&gt;</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button type="button"
                            x-on:click="$dispatch('open-modal', { name: 'ds-demo-modal' })">
                            Buka modal contoh
                        </x-primary-button>
                        <span class="ds-body-sm" style="color:var(--muted-soft)">
                            Dipicu via <code class="ds-code">$dispatch('open-modal', &#123; name &#125;)</code> —
                            ESC / klik overlay untuk tutup.
                        </span>
                    </div>

                    {{-- Modal asli: overlay gelap + panel putih rounded-2xl, sama persis dgn modal master --}}
                    <x-modal name="ds-demo-modal" size="lg" focusable>
                        {{-- Header --}}
                        <div class="flex items-start justify-between pb-3 mb-4 border-b border-gray-200 dark:border-gray-700">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Data Contoh</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pola modal form standar (header · body · footer).</p>
                            </div>
                            <button type="button" x-on:click="show = false"
                                class="inline-flex items-center justify-center w-8 h-8 text-gray-400 rounded-lg hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span class="sr-only">Tutup</span>
                            </button>
                        </div>

                        {{-- Body --}}
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Nama" :required="true" />
                                <x-text-input class="block w-full mt-1" placeholder="Nama poli / unit…" />
                            </div>
                            <div>
                                <x-input-label value="Status" />
                                <x-toggle :trueValue="true" :falseValue="false" label="Aktif" />
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="flex justify-end gap-2 pt-4 mt-5 border-t border-gray-200 dark:border-gray-700">
                            <x-secondary-button x-on:click="show = false">Batal</x-secondary-button>
                            <x-primary-button type="button" x-on:click="show = false">Simpan</x-primary-button>
                        </div>
                    </x-modal>

                    {{-- Dokumentasi props + cara pakai --}}
                    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-2">
                        {{-- Props --}}
                        <div>
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Props</div>
                            <div class="ds-card-outline" style="padding:0;overflow:hidden">
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr>
                                                <th>Prop</th>
                                                <th>Default</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([
                                                ['name', '— (wajib)', 'ID unik modal; dipakai event open/close-modal.'],
                                                ['size', 'lg', 'Lebar: md · lg · xl · 2xl · 3xl · 4xl · full.'],
                                                ['height', 'auto', 'auto = setinggi konten · full = setinggi layar + scroll internal.'],
                                                ['padding', 'p-4 sm:p-6', 'Padding panel; override bila perlu.'],
                                                ['show', 'false', 'Tampil saat awal render.'],
                                                ['focusable', '—', 'Atribut: auto-fokus elemen pertama saat dibuka.'],
                                            ] as [$prop, $def, $ket])
                                                <tr>
                                                    <td class="ds-td-token">{{ $prop }}</td>
                                                    <td class="ds-td-meta">{{ $def }}</td>
                                                    <td>{{ $ket }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Cara pakai --}}
                        <div>
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Cara pakai</div>
                            <div class="ds-card-dark" style="padding:20px">
<pre class="ds-code" style="margin:0;color:var(--on-dark-soft);overflow-x:auto;font-size:13px"><span style="color:var(--muted-soft)">&#123;&#123;-- 1. Letakkan modal --&#125;&#125;</span>
&lt;x-modal name=<span style="color:var(--accent-amber)">"edit-poli"</span> size=<span style="color:var(--accent-amber)">"xl"</span> focusable&gt;
    <span style="color:var(--muted-soft)">... header · body · footer ...</span>
&lt;/x-modal&gt;

<span style="color:var(--muted-soft)">&#123;&#123;-- 2a. Buka via Alpine (tombol mana pun) --&#125;&#125;</span>
x-on:click=<span style="color:var(--accent-amber)">"$dispatch('open-modal', &#123; name: 'edit-poli' &#125;)"</span>

<span style="color:var(--muted-soft)">&#123;&#123;-- 2b. Buka via Livewire (PHP) --&#125;&#125;</span>
$this-&gt;dispatch(<span style="color:var(--accent-amber)">'open-modal'</span>, name: <span style="color:var(--accent-amber)">'edit-poli'</span>);

<span style="color:var(--muted-soft)">&#123;&#123;-- 3. Tutup: ESC / klik overlay / show=false --&#125;&#125;</span>
$dispatch(<span style="color:var(--accent-amber)">'close-modal'</span>, &#123; name: <span style="color:var(--accent-amber)">'edit-poli'</span> &#125;)</pre>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== KOMPONEN LANJUTAN ===== --}}
                <div class="ds-caption-up mb-4 mt-10" style="color:var(--muted)">Komponen lanjutan</div>
                <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                    {{-- Loading --}}
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-4" style="color:var(--muted)">Loading · &lt;x-loading&gt;</div>
                        <div class="flex items-center gap-5" style="color:var(--primary)">
                            <x-loading size="xs" />
                            <x-loading size="sm" />
                            <x-loading size="md" />
                            <x-loading size="lg" />
                            <span class="inline-flex items-center gap-2 ds-body-sm" style="color:var(--body)">
                                <x-loading size="sm" /> Memuat data…
                            </span>
                        </div>
                        <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">size: xs · sm · md · lg. Mewarisi <code class="ds-code">currentColor</code>.</p>
                    </div>

                    {{-- Dropdown --}}
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-4" style="color:var(--muted)">Dropdown · &lt;x-dropdown&gt;</div>
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-secondary-button type="button">
                                    Menu
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </x-secondary-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#">Lihat detail</x-dropdown-link>
                                <x-dropdown-link href="#">Edit</x-dropdown-link>
                                <x-dropdown-link href="#">Cetak</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                        <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">Slot <code class="ds-code">trigger</code> + <code class="ds-code">content</code> (isi pakai &lt;x-dropdown-link&gt;).</p>
                    </div>

                    {{-- File upload --}}
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-4" style="color:var(--muted)">File upload · &lt;x-file-upload&gt;</div>
                        <x-file-upload name="demoFile" label="Unggah berkas" accept="image/*,application/pdf" />
                        <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">Props: <code class="ds-code">name</code> (wajib, wire:model), <code class="ds-code">label</code>, <code class="ds-code">accept</code>, <code class="ds-code">loadingStyle</code> (text/bar).</p>
                    </div>

                    {{-- Signature pad --}}
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-4" style="color:var(--muted)">Signature · &lt;x-signature.signature-pad&gt;</div>
                        <x-signature.signature-pad wireMethod="setSignature" :width="380" :height="150" />
                        <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">Props: <code class="ds-code">wireMethod</code> (penerima dataURL base64), <code class="ds-code">width</code>, <code class="ds-code">height</code>.</p>
                    </div>
                </div>

                {{-- Scrollable tabs --}}
                <div class="mb-6">
                    <div class="ds-caption-up mb-3" style="color:var(--muted)">Scrollable tabs · &lt;x-scrollable-tabs&gt; (panah muncul saat overflow)</div>
                    <div class="ds-card-outline" style="padding:8px">
                        <x-scrollable-tabs style="border-bottom:1px solid var(--hairline)">
                            <ul class="flex gap-1 flex-nowrap whitespace-nowrap">
                                @foreach (['Identitas','Anamnesis','Pemeriksaan Fisik','Diagnosis','Tindakan','Resep & Obat','Penunjang','Laboratorium','Radiologi','CPPT','Edukasi','Asesmen Awal','Perencanaan Pulang','Resume Medis','Rujukan','Persetujuan','Tanda Vital','Riwayat'] as $i => $t)
                                    <li>
                                        <button type="button"
                                            class="px-4 py-2 -mb-px text-sm font-medium transition border-b-2"
                                            style="{{ $i === 0 ? 'color:var(--primary);border-color:var(--primary)' : 'color:var(--muted);border-color:transparent' }}">
                                            {{ $t }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </x-scrollable-tabs>
                    </div>
                    <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">Bungkus <code class="ds-code">&lt;ul&gt;</code> tab; otomatis single-line + 2 tombol panah brand saat konten ter-hidden.</p>
                </div>

                {{-- ===== SURFACE & KOMPOSISI (pola halaman) ===== --}}
                <div class="ds-caption-up mb-4 mt-10" style="color:var(--muted)">Surface &amp; komposisi (pola band)</div>
                <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-3">
                    <div class="ds-card">
                        <div class="ds-spike mb-4"></div>
                        <div class="ds-title-md mb-2">Kartu terang</div>
                        <p class="ds-body-md">Latar satu tingkat lebih gelap dari kanvas. Untuk grid fitur. Tanpa shadow.</p>
                    </div>
                    <div class="ds-card-outline">
                        <div class="ds-display-md mb-1">Kartu outline</div>
                        <p class="ds-body-md">Kanvas + garis hairline. Untuk kartu data & perbandingan.</p>
                    </div>
                    <div class="ds-card-dark">
                        <div class="flex items-center gap-2 mb-3">
                            <span style="width:8px;height:8px;border-radius:9999px;background:var(--success);display:inline-block"></span>
                            <span class="ds-caption" style="color:var(--on-dark-soft)">Panel data</span>
                        </div>
                        <div class="ds-title-md mb-2" style="color:var(--on-dark)">Permukaan hijau-gelap</div>
                        <p class="ds-body-md" style="color:var(--on-dark-soft)">Untuk tabel, editor kode, footer. Chrome produk.</p>
                    </div>
                </div>

                {{-- Callout hijau (voltase brand full-bleed) --}}
                <div class="ds-callout">
                    <div class="grid items-center grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <h3 class="ds-display-sm" style="color:var(--on-primary)">Callout hijau</h3>
                            <p class="ds-body-md mt-2" style="color:rgba(255,255,255,.88)">
                                Satu-satunya tempat hijau brand dipakai royal — full-bleed. Tombol di dalamnya
                                memakai gaya inversi (terang di atas hijau).
                            </p>
                        </div>
                        <div class="flex lg:justify-end">
                            <button class="ds-btn ds-btn-oncream">Aksi utama</button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ RESPONSIVE ============ --}}
            <section id="responsive" class="ds-band">
                <div class="ds-eyebrow mb-3">04 — Responsive</div>
                <h2 class="ds-display-lg mb-2">Tampilan layar</h2>
                <p class="ds-body-md mb-8" style="max-width:60ch">
                    Acuan: halaman master (mis. <code class="ds-code">/master/poli</code>). Toolbar
                    <strong>search + aksi</strong> menumpuk di mobile (<code class="ds-code">flex-col</code>) lalu
                    sebaris di desktop (<code class="ds-code">lg:flex-row</code>); kartu tabel scroll internal,
                    kolom yang melebar di-<em>scroll</em> horizontal — bukan di-wrap.
                </p>

                {{-- Tabel breakpoint --}}
                <div class="ds-card-outline mb-8" style="padding:0;overflow:hidden">
                    <div class="overflow-x-auto">
                        <table class="ds-table">
                            <thead>
                                <tr><th>Breakpoint</th><th>Lebar</th><th>Prefix</th><th>Perubahan kunci</th></tr>
                            </thead>
                            <tbody>
                                @foreach ([
                                    ['Mobile', '< 768px', '(default)', 'Toolbar menumpuk · tabel scroll-x · grid 1 kolom · sidebar jadi drawer'],
                                    ['Tablet', '768–1024px', 'sm: / md:', 'Toolbar mulai sebaris · grid 2 kolom · kartu fitur 2-up'],
                                    ['Desktop', '1024–1440px', 'lg:', 'Toolbar sebaris penuh · grid 3 kolom · sidebar tetap'],
                                    ['Wide', '> 1440px', 'xl: / 2xl:', 'Sama dgn desktop, konten dibatasi max 1200px'],
                                ] as [$bp, $w, $px, $ket])
                                    <tr>
                                        <td class="ds-td-strong">{{ $bp }}</td>
                                        <td class="ds-td-meta">{{ $w }}</td>
                                        <td class="ds-td-class">{{ $px }}</td>
                                        <td>{{ $ket }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Device previews --}}
                @php
                    // Komponen mock kecil dipakai ulang di tiap device (statis, ilustratif).
                    $mockRows = [['A001','Klinik Umum','UMU',1],['A002','Klinik Gigi','GIG',1],['A003','Klinik Anak','ANK',0]];
                @endphp

                {{-- DESKTOP --}}
                <div class="mb-6">
                    <div class="ds-caption-up mb-3" style="color:var(--muted)">Desktop ≥ 1024px</div>
                    <div class="overflow-hidden border rounded-xl" style="border-color:var(--hairline);background:var(--canvas)">
                        <div class="flex items-center gap-1.5 px-3 h-8" style="background:var(--surface-soft);border-bottom:1px solid var(--hairline)">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:#f25f57"></span>
                            <span class="w-2.5 h-2.5 rounded-full" style="background:#fabc2e"></span>
                            <span class="w-2.5 h-2.5 rounded-full" style="background:#5db872"></span>
                            <span class="ds-code ml-2" style="color:var(--muted-soft)">/master/poli</span>
                        </div>
                        <div class="p-4">
                            {{-- toolbar sebaris — komponen asli: search + per-halaman x-select + Tambah + refresh/reset --}}
                            <div class="flex items-end justify-between gap-2 mb-3">
                                <div class="w-72">
                                    <x-text-input placeholder="Cari poli…" class="block w-full" />
                                </div>
                                <div class="flex items-end gap-2">
                                    <div class="w-20">
                                        <x-input-label value="Per halaman" class="sr-only" />
                                        <x-select-input class="block w-full">
                                            <option>10</option>
                                            <option>20</option>
                                            <option>50</option>
                                        </x-select-input>
                                    </div>
                                    <x-primary-button type="button">+ Tambah</x-primary-button>
                                    <x-toolbar-refresh-reset :label="null" />
                                </div>
                            </div>
                            {{-- tabel penuh --}}
                            <div class="overflow-hidden border rounded-xl" style="border-color:var(--hairline)">
                                @include('pages.standarisasi-ui.partial-mock-table', ['rows' => $mockRows])
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TABLET + MOBILE --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- TABLET --}}
                    <div class="lg:col-span-2">
                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tablet 768–1024px</div>
                        <div class="overflow-hidden border rounded-xl" style="border-color:var(--hairline);background:var(--canvas)">
                            <div class="flex items-center gap-1.5 px-3 h-8" style="background:var(--surface-soft);border-bottom:1px solid var(--hairline)">
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#f25f57"></span>
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#fabc2e"></span>
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#5db872"></span>
                            </div>
                            <div class="p-3">
                                {{-- toolbar tablet — komponen asli: search melebar + per-halaman + Tambah + refresh/reset --}}
                                <div class="flex items-end gap-2 mb-3">
                                    <div class="flex-1">
                                        <x-text-input placeholder="Cari…" class="block w-full" />
                                    </div>
                                    <div class="w-20">
                                        <x-input-label value="Per halaman" class="sr-only" />
                                        <x-select-input class="block w-full">
                                            <option>10</option>
                                            <option>20</option>
                                            <option>50</option>
                                        </x-select-input>
                                    </div>
                                    <x-primary-button type="button">+ Tambah</x-primary-button>
                                    <x-toolbar-refresh-reset :label="null" />
                                </div>
                                <div class="overflow-hidden border rounded-xl" style="border-color:var(--hairline)">
                                    @include('pages.standarisasi-ui.partial-mock-table', ['rows' => $mockRows])
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- MOBILE --}}
                    <div>
                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Mobile &lt; 768px</div>
                        <div class="mx-auto overflow-hidden border rounded-xl" style="border-color:var(--hairline);background:var(--canvas);max-width:375px">
                            <div class="flex items-center gap-1.5 px-3 h-8" style="background:var(--surface-soft);border-bottom:1px solid var(--hairline)">
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#f25f57"></span>
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#fabc2e"></span>
                                <span class="w-2.5 h-2.5 rounded-full" style="background:#5db872"></span>
                            </div>
                            <div class="p-3">
                                {{-- toolbar MENUMPUK — komponen asli (search full → per-halaman + Tambah + refresh/reset, wrap) --}}
                                <div class="flex flex-col gap-2 mb-3">
                                    <x-text-input placeholder="Cari poli…" class="block w-full" />
                                    <div class="flex items-end gap-2">
                                        <div class="w-20 shrink-0">
                                            <x-select-input class="block w-full">
                                                <option>10</option>
                                                <option>20</option>
                                            </x-select-input>
                                        </div>
                                        <x-primary-button type="button" class="flex-1 justify-center whitespace-nowrap">+ Tambah</x-primary-button>
                                        <x-toolbar-refresh-reset :label="null" :iconOnly="true" class="shrink-0" />
                                    </div>
                                </div>
                                {{-- tabel scroll-x --}}
                                <div class="overflow-hidden border rounded-xl" style="border-color:var(--hairline)">
                                    @include('pages.standarisasi-ui.partial-mock-table', ['rows' => $mockRows, 'minWidth' => '520px'])
                                </div>
                                <p class="mt-2 ds-body-sm" style="color:var(--muted-soft);font-size:11px">⟷ tabel di-scroll horizontal, bukan di-wrap</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ SPACING & RADIUS ============ --}}
            <section id="dasar" class="ds-band">
                <div class="ds-eyebrow mb-3">05 — Foundations</div>
                <h2 class="ds-display-lg mb-2">Spasi & sudut</h2>
                <p class="ds-body-md mb-10" style="max-width:56ch">Unit dasar 4px. Jarak antar band utama 96px. Padding dalam kartu murah hati (32px).</p>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-5" style="color:var(--muted)">Spacing</div>
                        @foreach ([['xxs',4],['xs',8],['sm',12],['md',16],['lg',24],['xl',32],['xxl',48],['section',96]] as [$n,$v])
                            <div class="flex items-center gap-4 mb-3">
                                <div class="ds-code" style="width:80px;font-size:12px;color:var(--muted)">{{ $n }} · {{ $v }}</div>
                                <div style="height:16px;width:{{ $v }}px;background:var(--primary);border-radius:3px"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="ds-card-outline">
                        <div class="ds-caption-up mb-5" style="color:var(--muted)">Border radius</div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            @foreach ([['sm','6px'],['md','8px'],['lg','12px'],['xl','16px'],['pill','9999px']] as [$n,$v])
                                <div>
                                    <div style="height:64px;background:var(--surface-card);border:1px solid var(--hairline);border-radius:{{ $v }}"></div>
                                    <div class="ds-code mt-2" style="font-size:12px;color:var(--muted)">{{ $n }} · {{ $v }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ DO / DON'T ============ --}}
            <section class="ds-band">
                <div class="ds-eyebrow mb-3">06 — Guideline</div>
                <h2 class="ds-display-lg mb-8">Lakukan & hindari</h2>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="ds-card-outline">
                        <div class="ds-title-md mb-4" style="color:var(--success)">✓ Lakukan</div>
                        <ul class="ds-body-md" style="margin:0;padding-left:18px;display:grid;gap:10px">
                            <li>Pakai kanvas terang brand sebagai dasar setiap halaman.</li>
                            <li>Headline full sans (Source Sans 3) bobot 700 dengan tracking negatif.</li>
                            <li>Hijau brand hanya untuk CTA utama & kartu callout full-bleed.</li>
                            <li>Selang-seling band terang dan permukaan gelap untuk ritme.</li>
                        </ul>
                    </div>
                    <div class="ds-card-outline">
                        <div class="ds-title-md mb-4" style="color:var(--error)">✕ Hindari</div>
                        <ul class="ds-body-md" style="margin:0;padding-left:18px;display:grid;gap:10px">
                            <li>Putih murni / abu dingin sebagai kanvas.</li>
                            <li>Memakai serif (sudah dipensiunkan) atau teks fungsional &lt; 13px.</li>
                            <li>Hijau brand bertaburan di banyak elemen kecil.</li>
                            <li>Mencampur warna lain di luar hijau, lime & netral.</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- ============ STANDAR UI v2 ============ --}}
            <section id="v2" class="ds-band">
                <div class="ds-eyebrow mb-3">v2 — Pembaruan</div>
                <h2 class="ds-display-lg mb-2">Standar UI siRUS v2</h2>
                <p class="ds-body-md mb-10" style="max-width:60ch">
                    Lapisan baru di atas fondasi: <strong>semantic 4 warna</strong> (incl. info) dengan
                    <code class="ds-code">-tint</code>/<code class="ds-code">-deep</code>, font
                    <strong>Source Sans 3</strong> (mudah dibaca lansia), <strong>tab segmented</strong>,
                    angka <code class="ds-code">.input-num</code>, dan token gelap yang ber-swap otomatis.
                    Aturan tetap: <strong>hijau brand ≠ success</strong> — hijau utk aksi/navigasi, success utk status hasil.
                </p>

                {{-- Semantic 4 warna: base / tint / deep --}}
                <div class="ds-frame mb-8">
                    <div class="ds-frame-label">Semantic — base · tint (bg) · deep (teks)</div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['Success', 'success', 'Selesai / aktif'],
                            ['Warning', 'warning', 'Menunggu'],
                            ['Error', 'error', 'Gagal / batal'],
                            ['Info', 'info', 'Dilayani / proses'],
                        ] as [$nama, $key, $arti])
                            <div class="ds-elevated" style="padding:14px">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="ds-title-sm">{{ $nama }}</span>
                                    <span class="badge-{{ $key }}-ds">{{ $arti }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <div><div class="ds-swatch" style="background:var(--{{ $key }});height:40px"></div><div class="ds-td-meta mt-1">base</div></div>
                                    <div><div class="ds-swatch" style="background:var(--{{ $key }}-tint);height:40px"></div><div class="ds-td-meta mt-1">tint</div></div>
                                    <div><div class="ds-swatch" style="background:var(--{{ $key }}-deep);height:40px"></div><div class="ds-td-meta mt-1">deep</div></div>
                                </div>
                                <code class="ds-code mt-2" style="display:block;color:var(--primary)">bg-{{ $key }}-tint · text-{{ $key }}-deep</code>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-2">
                    {{-- Badge status alur pasien --}}
                    <div class="ds-frame">
                        <div class="ds-frame-label">Badge status alur pasien</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge-warning-ds">Menunggu</span>
                            <span style="color:var(--muted-soft)">→</span>
                            <span class="badge-info-ds">Dilayani</span>
                            <span style="color:var(--muted-soft)">→</span>
                            <span class="badge-success-ds">Selesai</span>
                            <span style="color:var(--muted-soft)">·</span>
                            <span class="badge-error-ds">Batal</span>
                        </div>
                        <p class="ds-caption mt-3">Komponen Blade: <code class="ds-code">&lt;x-badge variant="info"&gt;</code> (kini pakai tint/deep).</p>
                    </div>

                    {{-- Alert / notifikasi --}}
                    <div class="ds-frame">
                        <div class="ds-frame-label">Alert / notifikasi</div>
                        <div class="grid gap-2">
                            <div class="alert-success-ds">Data pasien berhasil disimpan.</div>
                            <div class="alert-warning-ds">SEP belum diterbitkan untuk kunjungan ini.</div>
                            <div class="alert-error-ds">Gagal kirim ke BPJS — periksa koneksi lalu coba lagi.</div>
                            <div class="alert-info-ds">Pasien sedang dalam proses pelayanan.</div>
                        </div>
                    </div>

                    {{-- Tab segmented pill --}}
                    <div class="ds-frame" x-data="{ t: 'resume' }">
                        <div class="ds-frame-label">Tab segmented pill (aktif hijau solid)</div>
                        <div class="ds-tabs">
                            <button type="button" class="ds-tab" :class="t==='resume' ? 'ds-tab-active' : ''" @click="t='resume'">Resume</button>
                            <button type="button" class="ds-tab" :class="t==='dokumen' ? 'ds-tab-active' : ''" @click="t='dokumen'">Modul Dokumen</button>
                            <button type="button" class="ds-tab" :class="t==='log' ? 'ds-tab-active' : ''" @click="t='log'">Log</button>
                        </div>
                        <p class="ds-caption mt-3" x-text="'Tab aktif: ' + t"></p>
                    </div>

                    {{-- Input angka --}}
                    <div class="ds-frame">
                        <div class="ds-frame-label">Angka / tanggal — <code class="ds-code">.input-num</code></div>
                        <div class="grid gap-2">
                            <input class="ds-input input-num" value="12/06/2026 14:30" readonly>
                            <input class="ds-input input-num" value="0001234567 · No. RM" readonly>
                        </div>
                        <p class="ds-caption mt-3">Mono + <code class="ds-code">tabular-nums</code> → digit rata kolom.</p>
                    </div>
                </div>

                {{-- Kosakata badge lengkap (status + penjamin + aktif) --}}
                <div class="ds-frame mb-8">
                    <div class="ds-frame-label">Kosakata badge — tetap, jangan bikin status baru per halaman</div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <div class="ds-caption mb-2">Alur pasien</div>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge-warning-ds">Menunggu</span>
                                <span class="badge-info-ds">Dilayani</span>
                                <span class="badge-success-ds">Selesai</span>
                                <span class="badge-error-ds">Batal</span>
                            </div>
                        </div>
                        <div>
                            <div class="ds-caption mb-2">Penjamin</div>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge-success-ds">BPJS</span>
                                <span class="badge-ds" style="background:var(--surface-card);color:var(--ink)">Umum</span>
                            </div>
                        </div>
                        <div>
                            <div class="ds-caption mb-2">Master / data</div>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge-success-ds">Aktif</span>
                                <span class="badge-ds" style="background:var(--surface-card);color:var(--muted)">Nonaktif</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Kartu statistik dashboard --}}
                <div class="ds-frame mb-8">
                    <div class="ds-frame-label">Kartu statistik dashboard — overline + angka 30/700 tabular</div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['Kunjungan hari ini', '128', '▲ 12% vs kemarin', 'success'],
                            ['Antrean menunggu', '23', 'terpanjang: Umum', 'warning'],
                            ['SEP diterbitkan', '96', '▲ 8% vs kemarin', 'info'],
                            ['Batal / tidak hadir', '5', 'hari ini', 'error'],
                        ] as [$labelStat, $angkaStat, $subStat, $warnaStat])
                            <div class="ds-elevated" style="padding:18px">
                                <div class="t-overline">{{ $labelStat }}</div>
                                <div class="t-num" style="font-size:30px;font-weight:700;color:var(--ink);line-height:1.1;margin:6px 0">{{ $angkaStat }}</div>
                                <span class="badge-{{ $warnaStat }}-ds">{{ $subStat }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Nomor antrian (display & panggilan) --}}
                <div class="ds-frame mb-8">
                    <div class="ds-frame-label">Nomor antrian — yang aktif hijau solid (terbaca dari jauh)</div>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div style="background:var(--primary);color:#fff;border-radius:14px;padding:20px;text-align:center">
                            <div class="t-overline" style="color:rgba(255,255,255,.82)">Sedang dilayani</div>
                            <div class="t-num" style="font-size:44px;font-weight:700;line-height:1.05">A-012</div>
                            <div class="t-caption" style="color:rgba(255,255,255,.88)">Klinik Umum</div>
                        </div>
                        @foreach ([['Berikutnya', 'A-013', 'Klinik Umum'], ['Antrean', 'A-014', 'Klinik Gigi'], ['Antrean', 'A-015', 'Klinik Anak']] as [$qLabel, $qNo, $qPoli])
                            <div class="ds-elevated" style="padding:20px;text-align:center">
                                <div class="t-overline">{{ $qLabel }}</div>
                                <div class="t-num" style="font-size:44px;font-weight:700;line-height:1.05;color:var(--muted)">{{ $qNo }}</div>
                                <div class="t-caption">{{ $qPoli }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tipografi v2 (.t-*) --}}
                <div class="ds-frame mb-8">
                    <div class="ds-frame-label">Tipografi v2 — Source Sans 3 (display pun sans)</div>
                    <div class="grid gap-2.5">
                        <div class="t-display">Display 32/700</div>
                        <div class="t-h1">Heading 1 — 24/700</div>
                        <div class="t-h2">Heading 2 — 20/600</div>
                        <div class="t-h3">Heading 3 — 18/600</div>
                        <div class="t-title">Title — 16/600</div>
                        <div class="t-body">Body 16/400 — teks berjalan untuk paragraf isi rekam medis & deskripsi.</div>
                        <div class="t-body-sm">Body kecil 14.5/400 — isi tabel & sel data.</div>
                        <div class="t-caption">Caption 13.5/500 — keterangan & fine-print (min 13px).</div>
                        <div class="t-overline">Overline 12 · uppercase</div>
                    </div>
                </div>

                {{-- Kamus UX writing --}}
                <div class="ds-frame">
                    <div class="ds-frame-label">Kamus kata tombol (baku)</div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ([
                            'Simpan', 'Batal', 'Ya, Hapus', '+ Tambah', 'Cari', 'Refresh + Reset',
                            'Cetak', 'Panggil', 'Lewati', 'Selesai', 'Tutup', 'Lanjut / Kembali',
                            'Keluar', 'Lanjut Mengisi', 'Keluar Tanpa Simpan',
                        ] as $kata)
                            <span class="ds-badge-pill">{{ $kata }}</span>
                        @endforeach
                    </div>
                    <p class="ds-caption mt-4">Form belum tersimpan → <strong>Lanjut Mengisi</strong> / <strong>Keluar Tanpa Simpan</strong>. Pesan error = <em>apa + cara memperbaiki</em>.</p>
                </div>
            </section>

            {{-- footer kecil --}}
            <footer class="ds-band" style="padding-bottom:96px">
                <hr class="ds-divider mb-6">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-body-sm" style="color:var(--muted)">Standarisasi UI — acuan internal SIMRS RSI Madinah. Warna brand: hijau #157547 &amp; lime #A1CD3A.</span>
                </div>
            </footer>

        </div>
    </div>
</div>
