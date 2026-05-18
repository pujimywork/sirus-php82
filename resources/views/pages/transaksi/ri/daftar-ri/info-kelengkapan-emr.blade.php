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
    public array $snomed = [];

    #[On('open-info-kelengkapan-emr-ri')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->data = $this->findDataRI($riHdrNo) ?? [];
        $this->pct = $this->calculateEmrPercentRI($this->data);
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
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="w-5 h-5 text-indigo-700 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Kelengkapan EMR Rawat Inap — No. RI {{ $riHdrNo ?? '-' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
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
                                <span class="font-semibold text-gray-800 dark:text-gray-200">Status Pasien Ini</span>
                                <span class="text-2xl font-bold {{ $pct['emr'] >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($pct['emr'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
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
                                    <div class="font-bold {{ $score >= 80 ? 'text-emerald-700 dark:text-emerald-300' : ($score >= 50 ? 'text-amber-700 dark:text-amber-300' : 'text-rose-700 dark:text-rose-300') }}">
                                        {{ $letter }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $names[$key] }}</div>
                                    <div class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $score }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- SNOMED coding status --}}
                    <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                        <div class="flex items-center justify-between px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">SNOMED Coding</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">(info — tidak masuk ke %)</span>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($snomed as $item)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300 w-1/3">
                                            <strong>{{ $item['label'] }}</strong>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                            @if (filled($item['code']))
                                                <div class="flex items-center gap-2">
                                                    <x-badge variant="success">✓ Coded</x-badge>
                                                    <span class="font-mono text-xs">{{ $item['code'] }}</span>
                                                </div>
                                                @if (filled($item['displayId']))
                                                    <div class="mt-0.5 text-xs italic text-gray-500">{{ $item['displayId'] }}</div>
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

                {{-- PANDUAN KRITERIA 100% (statis) --}}
                <div class="pt-2 mt-2 border-t border-dashed border-gray-300 dark:border-gray-700">
                    <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Panduan: Persentase mencapai <span class="text-emerald-600 dark:text-emerald-400">100%</span> jika seluruh field wajib berikut terisi.
                    </p>
                </div>

                {{-- Legenda warna --}}
                <div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Legenda Warna Progress</p>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-rose-400/80 dark:bg-rose-400"></span><span class="text-gray-700 dark:text-gray-300">&lt; 50% — kurang</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-amber-400/80 dark:bg-amber-400"></span><span class="text-gray-700 dark:text-gray-300">50–79% — sedang</span></div>
                        <div class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded bg-emerald-500/80 dark:bg-emerald-400"></span><span class="text-gray-700 dark:text-gray-300">≥ 80% — lengkap</span></div>
                    </div>
                </div>

                <div class="p-3 border-l-4 border-blue-500 rounded bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">Aturan field "screening" (alergi, RPD):</span>
                        boleh diisi <em>"Tidak ada"</em> bila negatif — yang penting <strong>jangan dibiarkan kosong</strong>.
                    </p>
                </div>

                {{-- S — Pengkajian Awal --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-blue-50 dark:bg-blue-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">S</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Pengkajian Awal Pasien Rawat Inap</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 15%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Kondisi saat masuk</strong></div></li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>DPJP</strong> (dokter penanggung jawab)</div></li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Keluhan utama</strong> (di pemeriksaan fisik)</div></li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Riwayat penyakit / operasi / cedera</strong> — pilihan + deskripsi <span class="text-xs text-gray-500">(boleh "Tidak ada")</span></div></li>
                    </ul>
                </div>

                {{-- O — Pemeriksaan/TTV --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-emerald-50 dark:bg-emerald-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Pemeriksaan / Tanda Vital</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 15%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Tekanan darah</strong> (sistolik + distolik), <strong>nadi</strong>, <strong>napas</strong>, <strong>suhu</strong>, <strong>SpO2</strong></div></li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>BB</strong> & <strong>TB</strong></div></li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Tingkat kesadaran</strong> (neurologi)</div></li>
                    </ul>
                </div>

                {{-- A — Diagnosa --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-amber-700 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Diagnosa</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 15%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div>Minimal <strong>1 diagnosa</strong> (ICD-10 atau free-text)</div></li>
                    </ul>
                </div>

                {{-- P — Perencanaan --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-orange-50 dark:bg-orange-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-orange-700 bg-orange-100 dark:bg-orange-900/40 dark:text-orange-300">P</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Perencanaan / Discharge</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 10%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Tindak lanjut</strong> ATAU <strong>tanggal pulang</strong> ATAU <strong>discharge planning</strong></div></li>
                    </ul>
                </div>

                {{-- N — Penilaian --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-purple-50 dark:bg-purple-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold text-purple-700 bg-purple-100 rounded-full dark:bg-purple-900/40 dark:text-purple-300">N</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Penilaian</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 5%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div><strong>Penilaian nyeri</strong> & <strong>risiko jatuh</strong> (min 1 entry per assessment)</div></li>
                    </ul>
                </div>

                {{-- C — CPPT (khas RI) --}}
                <div class="overflow-hidden border-2 border-indigo-300 rounded-lg dark:border-indigo-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-indigo-100 dark:bg-indigo-900/30">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-indigo-700 bg-indigo-200 dark:bg-indigo-900/50 dark:text-indigo-300">C</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">CPPT — Catatan Perkembangan Pasien Terintegrasi</span>
                            <span class="text-xs font-medium text-indigo-700 dark:text-indigo-300">(khas RI — monitor harian)</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 20%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div>Minimal <strong>1 entry CPPT</strong> dengan SOAP (Subjective / Objective / Assessment / Plan)</div></li>
                    </ul>
                </div>

                {{-- K — Askep (khas RI) --}}
                <div class="overflow-hidden border-2 border-teal-300 rounded-lg dark:border-teal-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-teal-100 dark:bg-teal-900/30">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-teal-700 bg-teal-200 dark:bg-teal-900/50 dark:text-teal-300">K</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Asuhan Keperawatan (SDKI / SLKI / SIKI)</span>
                            <span class="text-xs font-medium text-teal-700 dark:text-teal-300">(khas RI — askep per shift)</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 20%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300"><span class="text-emerald-600 dark:text-emerald-400">✓</span><div>Minimal <strong>1 diagnosis keperawatan</strong> (SDKI) + <strong>intervensi</strong> (SIKI)</div></li>
                    </ul>
                </div>

                {{-- Catatan --}}
                <div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">SNOMED coding</span> tidak masuk perhitungan persentase, hanya info kesiapan SATUSEHAT.
                        Modul observasi/SKDP/modul-dokumen bersifat opsional. Standar: <strong>Permenkes 24/2022</strong> & <strong>SNARS Ed. 1.1</strong>.
                    </p>
                </div>

            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-ri' })">
                    Mengerti
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
