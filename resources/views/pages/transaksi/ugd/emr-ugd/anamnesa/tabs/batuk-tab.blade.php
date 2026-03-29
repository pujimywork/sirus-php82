{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/batuk-tab.blade.php --}}
<div class="space-y-4">

    <x-border-form :title="__('Screening Batuk')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            <p class="text-xs text-gray-400">
                Tandai gejala yang dialami pasien. Setiap item dapat disertai keterangan tambahan.
            </p>

            <x-border-form :title="__('Gejala & Riwayat')" :align="__('start')" :bgcolor="__('bg-white')">
                <div class="mt-4 space-y-3">

                    {{-- Riwayat Demam --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.riwayatDemam" trueValue="1"
                                falseValue="0" :disabled="$isFormLocked">
                                {{ __('Riwayat Demam') }}
                            </x-toggle>
                            <div>
                                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganRiwayatDemam"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has('dataDaftarUGD.anamnesa.batuk.keteranganRiwayatDemam')"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.batuk.keteranganRiwayatDemam')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Berkeringat Malam Hari --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.berkeringatMlmHari" trueValue="1"
                                falseValue="0" :disabled="$isFormLocked">
                                {{ __('Berkeringat Malam Tanpa Aktivitas') }}
                            </x-toggle>
                            <div>
                                <x-text-input
                                    wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganBerkeringatMlmHari"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has(
                                        'dataDaftarUGD.anamnesa.batuk.keteranganBerkeringatMlmHari',
                                    )"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.batuk.keteranganBerkeringatMlmHari')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Bepergian Daerah Wabah --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.bepergianDaerahWabah" trueValue="1"
                                falseValue="0" :disabled="$isFormLocked">
                                {{ __('Riwayat ke Daerah Wabah') }}
                            </x-toggle>
                            <div>
                                <x-text-input
                                    wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganBepergianDaerahWabah"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has(
                                        'dataDaftarUGD.anamnesa.batuk.keteranganBepergianDaerahWabah',
                                    )"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.batuk.keteranganBepergianDaerahWabah')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Pemakaian Obat Jangka Panjang --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.riwayatPakaiObatJangkaPanjangan"
                                trueValue="1" falseValue="0" :disabled="$isFormLocked">
                                {{ __('Pemakaian Obat Jangka Panjang') }}
                            </x-toggle>
                            <div>
                                <x-text-input
                                    wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has(
                                        'dataDaftarUGD.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan',
                                    )"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get(
                                    'dataDaftarUGD.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan',
                                )" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Berat Badan Turun --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.BBTurunTanpaSebab" trueValue="1"
                                falseValue="0" :disabled="$isFormLocked">
                                {{ __('Berat Badan Turun Tanpa Sebab') }}
                            </x-toggle>
                            <div>
                                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganBBTurunTanpaSebab"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has('dataDaftarUGD.anamnesa.batuk.keteranganBBTurunTanpaSebab')"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.batuk.keteranganBBTurunTanpaSebab')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Pembesaran Kelenjar Getah Bening --}}
                    <div
                        class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                            <x-toggle wire:model.live="dataDaftarUGD.anamnesa.batuk.pembesaranGetahBening"
                                trueValue="1" falseValue="0" :disabled="$isFormLocked">
                                {{ __('Pembesaran Kelenjar Getah Bening') }}
                            </x-toggle>
                            <div>
                                <x-text-input
                                    wire:model.live="dataDaftarUGD.anamnesa.batuk.keteranganPembesaranGetahBening"
                                    placeholder="Keterangan (opsional)" class="w-full" :error="$errors->has(
                                        'dataDaftarUGD.anamnesa.batuk.keteranganPembesaranGetahBening',
                                    )"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.batuk.keteranganPembesaranGetahBening')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                </div>
            </x-border-form>

        </div>
    </x-border-form>

</div>
