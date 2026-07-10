{{-- Safety Plan (Stanley-Brown) — sesuai "Contoh Safety Planning" RSI Madinah.
     Dipakai bersama oleh sub-penilaian Risiko Bunuh Diri RJ / UGD / RI.
     State di komponen induk: formEntryResikoBunuhDiri.safetyPlan.*
     Checklist array pakai x-toggle Mode 2 (current + wireClick) yang mengirim
     nama field + INDEKS ke toggleSafetyPlan(); opsi bersumber dari
     App\Support\SafetyPlanOptions supaya indeks blade & server selalu sinkron. --}}
@php
    use App\Support\SafetyPlanOptions;

    $sp = 'formEntryResikoBunuhDiri.safetyPlan';
    $tandaBahayaOptions = SafetyPlanOptions::TANDA_BAHAYA;
    $strategiMandiriOptions = SafetyPlanOptions::STRATEGI_MANDIRI;
    $aktivitasPengalihOptions = SafetyPlanOptions::AKTIVITAS_PENGALIH;
    $amankanLingkunganOptions = SafetyPlanOptions::AMANKAN_LINGKUNGAN;
@endphp

<x-border-form title="D · Safety Plan" align="start" bgcolor="bg-canvas">
    <p class="mb-3 text-xs text-muted-soft">
        Disusun <strong>bersama pasien</strong> dengan bahasa sederhana; bersifat individual, dapat melibatkan keluarga,
        dan ditinjau ulang sebelum pasien pulang. Dianjurkan untuk risiko Sedang/Tinggi atau bila teridentifikasi risiko.
    </p>

    <div class="space-y-5">
        {{-- 1. TANDA BAHAYA --}}
        <div>
            <div class="text-sm font-semibold text-ink dark:text-gray-200">1. Tanda Bahaya (Warning Signs)</div>
            <p class="mb-1.5 text-xs text-muted-soft">Bagaimana saya mengetahui bahwa saya mulai mengalami krisis?</p>
            <div class="grid grid-cols-1 gap-y-1.5 sm:grid-cols-2">
                @foreach ($tandaBahayaOptions as $opsi)
                    <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['safetyPlan']['tandaBahaya'] ?? [], true) ? '1' : '0'"
                        trueValue="1" falseValue="0"
                        wireClick="toggleSafetyPlan('tandaBahaya', {{ $loop->index }})">
                        {{ $opsi }}
                    </x-toggle>
                @endforeach
            </div>
            <div class="mt-2">
                <x-input-label value="Lainnya" />
                <x-text-input wire:model="{{ $sp }}.tandaBahayaLainnya" class="w-full mt-1" />
            </div>
        </div>

        {{-- 2. STRATEGI MANDIRI --}}
        <div>
            <div class="text-sm font-semibold text-ink dark:text-gray-200">2. Strategi yang Dapat Saya Lakukan Sendiri</div>
            <p class="mb-1.5 text-xs text-muted-soft">Yang dapat saya lakukan untuk membantu diri sendiri:</p>
            <div class="grid grid-cols-1 gap-y-1.5 sm:grid-cols-2">
                @foreach ($strategiMandiriOptions as $opsi)
                    <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['safetyPlan']['strategiMandiri'] ?? [], true) ? '1' : '0'"
                        trueValue="1" falseValue="0"
                        wireClick="toggleSafetyPlan('strategiMandiri', {{ $loop->index }})">
                        {{ $opsi }}
                    </x-toggle>
                @endforeach
            </div>
            <div class="mt-2">
                <x-input-label value="Yang paling membantu saya" />
                <x-text-input wire:model="{{ $sp }}.strategiPalingMembantu" class="w-full mt-1" />
            </div>
        </div>

        {{-- 3. TEMPAT / AKTIVITAS PENGALIH --}}
        <div>
            <div class="text-sm font-semibold text-ink dark:text-gray-200">3. Tempat atau Aktivitas untuk Mengalihkan Pikiran</div>
            <p class="mb-1.5 text-xs text-muted-soft">Saya dapat:</p>
            <div class="grid grid-cols-1 gap-y-1.5 sm:grid-cols-2">
                @foreach ($aktivitasPengalihOptions as $opsi)
                    <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['safetyPlan']['aktivitasPengalih'] ?? [], true) ? '1' : '0'"
                        trueValue="1" falseValue="0"
                        wireClick="toggleSafetyPlan('aktivitasPengalih', {{ $loop->index }})">
                        {{ $opsi }}
                    </x-toggle>
                @endforeach
            </div>
            <div class="mt-2">
                <x-input-label value="Aktivitas lain" />
                <x-text-input wire:model="{{ $sp }}.aktivitasPengalihLainnya" class="w-full mt-1" />
            </div>
        </div>

        {{-- 4. ORANG YANG DAPAT DIHUBUNGI --}}
        <div>
            <div class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">4. Orang yang Dapat Saya Hubungi</div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input wire:model="{{ $sp }}.orangNama" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="Nomor Telepon" />
                    <x-text-input wire:model="{{ $sp }}.orangTelp" class="w-full mt-1" />
                </div>
            </div>
        </div>

        {{-- 5. PROFESIONAL --}}
        <div>
            <div class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">5. Profesional yang Dapat Dihubungi</div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label value="Dokter" />
                    <x-text-input wire:model="{{ $sp }}.profesionalDokter" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="Psikiater / Psikolog" />
                    <x-text-input wire:model="{{ $sp }}.profesionalPsikiater" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="Rumah Sakit" />
                    <x-text-input wire:model="{{ $sp }}.profesionalRs" class="w-full mt-1" />
                </div>
                <div>
                    <x-input-label value="IGD" />
                    <x-text-input wire:model="{{ $sp }}.profesionalIgd" class="w-full mt-1" />
                </div>
            </div>
        </div>

        {{-- 6. AMANKAN LINGKUNGAN --}}
        <div>
            <div class="text-sm font-semibold text-ink dark:text-gray-200">6. Cara Membuat Lingkungan Lebih Aman</div>
            <p class="mb-1.5 text-xs text-muted-soft">Saya atau keluarga akan:</p>
            <div class="grid grid-cols-1 gap-y-1.5">
                @foreach ($amankanLingkunganOptions as $opsi)
                    <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['safetyPlan']['amankanLingkungan'] ?? [], true) ? '1' : '0'"
                        trueValue="1" falseValue="0"
                        wireClick="toggleSafetyPlan('amankanLingkungan', {{ $loop->index }})">
                        {{ $opsi }}
                    </x-toggle>
                @endforeach
            </div>
        </div>

        {{-- KOMITMEN KESELAMATAN --}}
        <div class="p-3 border rounded-xl bg-surface-soft border-hairline dark:border-gray-700">
            <div class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Komitmen Keselamatan</div>
            <p class="mb-2 text-xs italic text-muted dark:text-gray-400">
                "Bila pikiran bunuh diri muncul dan saya merasa tidak mampu mengatasinya, saya akan mengikuti
                langkah-langkah di atas dan segera menghubungi orang yang saya percaya / tenaga kesehatan."
            </p>
            <x-toggle wire:model="{{ $sp }}.komitmen" :trueValue="true" :falseValue="false" :disabled="$isFormLocked">
                Pasien memahami dan menyetujui komitmen keselamatan di atas.
            </x-toggle>
        </div>
    </div>
</x-border-form>
