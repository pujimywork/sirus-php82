<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Txn\Ri\EmrCompletenessRITrait;

new class extends Component {
    use EmrRITrait, EmrCompletenessRITrait;

    public ?int $riHdrNo = null;
    public array $data = [];
    public array $pct = ['emr' => 0, 'sections' => ['s' => 0, 'o' => 0, 'a' => 0, 'p' => 0, 'n' => 0, 'c' => 0, 'k' => 0]];
    public array $checklist = [];
    public array $snomed = [];

    #[On('open-info-kelengkapan-emr-ri')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->data = $this->findDataRI($riHdrNo) ?? [];
        $this->pct = $this->calculateEmrPercentRI($this->data);
        $this->checklist = $this->collectChecklistRI($this->data);
        $this->snomed = $this->collectSnomedStatus($this->data);
        $this->dispatch('open-modal', name: 'info-kelengkapan-emr-ri');
    }

    /**
     * SNOMED status — field RI yang punya snomedCode. RI struktur lebih luas,
     * cek keluhan utama (di pengkajian dokter) dan alergi (di pengkajian dokter).
     */
    private function collectSnomedStatus(array $json): array
    {
        $pd = $json['pengkajianDokter'] ?? [];
        $pa = $json['pengkajianAwalPasienRawatInap'] ?? [];

        return [
            [
                'label' => 'Keluhan Utama (Dokter)',
                'value' => $pd['anamnesa']['keluhanUtama'] ?? '',
                'code'  => $pd['anamnesa']['keluhanUtamaSnomedCode'] ?? '',
                'displayId' => $pd['anamnesa']['keluhanUtamaSnomedDisplayId'] ?? '',
            ],
            [
                'label' => 'Alergi (Dokter)',
                'value' => $pd['anamnesa']['jenisAlergi'] ?? '',
                'code'  => $pd['anamnesa']['alergiSnomedCode'] ?? '',
                'displayId' => $pd['anamnesa']['alergiSnomedDisplayId'] ?? '',
            ],
            [
                'label' => 'Keluhan Utama (Pengkajian Awal)',
                'value' => $pa['bagian4PemeriksaanFisik']['keluhanUtama'] ?? '',
                'code'  => $pa['bagian4PemeriksaanFisik']['keluhanUtamaSnomedCode'] ?? '',
                'displayId' => $pa['bagian4PemeriksaanFisik']['keluhanUtamaSnomedDisplayId'] ?? '',
            ],
        ];
    }
};

?>

{{--
    Modal panduan kriteria kelengkapan EMR Rawat Inap + status pasien aktual.
    Dibuka via event `open-info-kelengkapan-emr-ri` dengan parameter riHdrNo.

    Sumber bobot & field WAJIB: App\Http\Traits\Txn\Ri\EmrCompletenessRITrait
    RI punya 2 section khas (C=CPPT, K=Askep) di luar S/O/A/P/N RJ/UGD.
--}}

<div>
    <x-modal name="info-kelengkapan-emr-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-4rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="w-5 h-5 text-indigo-700 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-serif text-2xl text-ink dark:text-gray-100">
                                Kelengkapan EMR Rawat Inap — No. RI {{ $riHdrNo ?? '-' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Status pasien saat ini & panduan kriteria 100%.
                            </p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-ri' })">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">

                {{-- STATUS PASIEN AKTUAL (dinamis) --}}
                @if ($riHdrNo)
                    <div class="overflow-hidden border-2 border-emerald-300 rounded-lg dark:border-emerald-700">
                        <div class="px-4 py-2 bg-emerald-100 dark:bg-emerald-900/30">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-ink dark:text-gray-200">Status Pasien Ini</span>
                                <span class="text-2xl font-bold {{ $pct['emr'] >= 80 ? 'text-success dark:text-success' : ($pct['emr'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-error dark:text-rose-400') }}">
                                    {{ $pct['emr'] }}%
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-1 p-3 text-xs text-center">
                            @php
                                $labels = ['s' => 'S', 'o' => 'O', 'a' => 'A', 'p' => 'P', 'n' => 'N', 'c' => 'C', 'k' => 'K'];
                                $names  = ['s' => 'Pengkajian', 'o' => 'TTV', 'a' => 'Diagnosa', 'p' => 'Rencana', 'n' => 'Penilaian', 'c' => 'CPPT', 'k' => 'Askep'];
                            @endphp
                            @foreach ($labels as $key => $letter)
                                @php $score = $pct['sections'][$key] ?? 0; @endphp
                                <div class="p-2 rounded {{ $score >= 80 ? 'bg-emerald-50 dark:bg-emerald-900/20' : ($score >= 50 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-rose-50 dark:bg-rose-900/20') }}">
                                    <div class="font-bold {{ $score >= 80 ? 'text-emerald-700 dark:text-emerald-300' : ($score >= 50 ? 'text-amber-700 dark:text-amber-300' : 'text-error dark:text-rose-300') }}">
                                        {{ $letter }}
                                    </div>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $names[$key] }}</div>
                                    <div class="mt-1 text-sm font-semibold text-body dark:text-gray-300">{{ $score }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- SNOMED coding status --}}
                    <div class="overflow-hidden border border-hairline rounded-lg dark:border-gray-700">
                        <div class="flex items-center justify-between px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                <span class="font-semibold text-ink dark:text-gray-200">SNOMED Coding</span>
                                <span class="text-xs text-muted dark:text-gray-400">(info — tidak masuk ke %)</span>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                @foreach ($snomed as $item)
                                    <tr wire:key="info-emr-ri-snomed-{{ $item['label'] ?? $loop->index }}">
                                        <td class="px-4 py-2 text-body dark:text-gray-300 w-1/3">
                                            <strong>{{ $item['label'] }}</strong>
                                        </td>
                                        <td class="px-4 py-2 text-muted dark:text-gray-400">
                                            @if (filled($item['code']))
                                                <div class="flex items-center gap-2">
                                                    <x-badge variant="success">✓ Coded</x-badge>
                                                    <span class="font-mono text-xs">{{ $item['code'] }}</span>
                                                </div>
                                                @if (filled($item['displayId']))
                                                    <div class="mt-0.5 text-xs italic text-muted">{{ $item['displayId'] }}</div>
                                                @endif
                                            @elseif (filled($item['value']))
                                                <x-badge variant="warning">⚠ Free-text — belum SNOMED</x-badge>
                                            @else
                                                <x-badge variant="gray">— Belum diisi</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ════════════════════════════════════════════════════════
                     CHECKLIST FIELD PER SECTION (dinamis — sudah / belum diisi)
                ════════════════════════════════════════════════════════ --}}
                @if ($riHdrNo)
                    <div class="pt-2 mt-2 border-t border-dashed border-gray-300 dark:border-gray-700">
                        <p class="mb-3 text-sm font-semibold text-body dark:text-gray-300">
                            Detail field: <span class="text-success dark:text-success">✓ sudah diisi</span> /
                            <span class="text-error dark:text-rose-400">✗ belum diisi</span>.
                        </p>
                    </div>

                    @php
                        $sectionStyles = [
                            's' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'badge' => 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null],
                            'o' => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'badge' => 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null],
                            'a' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'badge' => 'text-amber-700 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null],
                            'p' => ['bg' => 'bg-orange-50 dark:bg-orange-900/20', 'badge' => 'text-orange-700 bg-orange-100 dark:bg-orange-900/40 dark:text-orange-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null],
                            'n' => ['bg' => 'bg-purple-50 dark:bg-purple-900/20', 'badge' => 'text-purple-700 bg-purple-100 dark:bg-purple-900/40 dark:text-purple-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null],
                            'c' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'badge' => 'text-indigo-700 bg-indigo-200 dark:bg-indigo-900/50 dark:text-indigo-300', 'border' => 'border-2 border-indigo-300 dark:border-indigo-700', 'caption' => 'khas RI — monitor harian', 'captionClass' => 'text-indigo-700 dark:text-indigo-300'],
                            'k' => ['bg' => 'bg-teal-100 dark:bg-teal-900/30', 'badge' => 'text-teal-700 bg-teal-200 dark:bg-teal-900/50 dark:text-teal-300', 'border' => 'border-2 border-teal-300 dark:border-teal-700', 'caption' => 'khas RI — askep per shift', 'captionClass' => 'text-teal-700 dark:text-teal-300'],
                        ];
                    @endphp

                    @foreach ($checklist as $key => $section)
                        @php
                            $style = $sectionStyles[$key] ?? ['bg' => 'bg-surface-soft dark:bg-gray-900/20', 'badge' => 'text-body bg-surface-soft dark:bg-gray-700 dark:text-gray-300', 'border' => 'border border-hairline dark:border-gray-700', 'caption' => null];
                            $filledCount = collect($section['items'])->where('filled', true)->count();
                            $totalCount = count($section['items']);
                        @endphp
                        <div class="overflow-hidden rounded-lg {{ $style['border'] }}">
                            <div class="flex items-center justify-between px-4 py-2 {{ $style['bg'] }}">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full {{ $style['badge'] }}">{{ strtoupper($key) }}</span>
                                    <span class="font-semibold text-ink dark:text-gray-200">{{ $section['label'] }}</span>
                                    @if (!empty($style['caption']))
                                        <span class="text-xs font-medium {{ $style['captionClass'] ?? 'text-muted' }}">({{ $style['caption'] }})</span>
                                    @endif
                                    <span class="text-xs text-muted dark:text-gray-400">({{ $filledCount }}/{{ $totalCount }})</span>
                                </div>
                                <span class="text-xs font-medium text-muted dark:text-gray-400">Bobot {{ $section['weight'] }}%</span>
                            </div>
                            <ul class="divide-y divide-hairline-soft dark:divide-gray-700">
                                @foreach ($section['items'] as $item)
                                    <li class="flex items-start gap-2 px-4 py-2 text-sm text-body dark:text-gray-300">
                                        @if ($item['filled'])
                                            <span class="text-success dark:text-success">✓</span>
                                            <div>{{ $item['label'] }}</div>
                                        @else
                                            <span class="text-error dark:text-rose-400">✗</span>
                                            <div class="text-muted dark:text-gray-400">{{ $item['label'] }} <span class="text-xs text-error dark:text-rose-400">(belum)</span></div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @endif

                {{-- Legenda + catatan --}}
                <div class="p-3 border border-hairline rounded-lg bg-surface-soft dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-400">Legenda Warna Progress</p>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-rose-400/80 dark:bg-rose-400"></span><span class="text-body dark:text-gray-300">&lt; 50% — kurang</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-amber-400/80 dark:bg-amber-400"></span><span class="text-body dark:text-gray-300">50–79% — sedang</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-emerald-500/80 dark:bg-emerald-400"></span><span class="text-body dark:text-gray-300">≥ 80% — lengkap</span></div>
                    </div>
                </div>

                <div class="p-3 border-l-4 border-blue-500 rounded bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-sm text-body dark:text-gray-300">
                        <span class="font-semibold">Aturan field "screening" (alergi, RPD):</span>
                        boleh diisi <em>"Tidak ada"</em> bila negatif — yang penting <strong>jangan dibiarkan kosong</strong>.
                    </p>
                </div>

                <div class="p-3 border border-hairline rounded-lg bg-surface-soft dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="text-xs text-muted dark:text-gray-400">
                        <span class="font-semibold">SNOMED coding</span> tidak masuk perhitungan persentase, hanya info kesiapan SATUSEHAT.
                        Modul observasi/SKDP/modul-dokumen bersifat opsional. Standar: <strong>Permenkes 24/2022</strong> & <strong>SNARS Ed. 1.1</strong>.
                    </p>
                </div>

            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end px-6 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-900/40 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-ri' })">
                    Mengerti
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
