 {{-- SECTION: ALAMAT DOMISILI --}}
 <x-border-form :title="__('Alamat Domisili')" :align="__('start')" :bgcolor="__('bg-white')">
     <div class="space-y-5">
         {{-- Toggle Sama dengan Identitas --}}
         <div class="flex justify-end">
             <x-toggle wire:model.live="dataPasien.pasien.domisil.samadgnidentitas" trueValue="Y" falseValue="N">
                 Sama dengan Identitas
             </x-toggle>
         </div>

         {{-- Alamat Domisili --}}
         <div>
             <x-input-label value="Alamat Domisili" :required="true" />

             <x-textarea wire:key="domisil-alamat-{{ $domisilSyncTick }}"
                 wire:model.live="dataPasien.pasien.domisil.alamat" :error="$errors->has('dataPasien.pasien.domisil.alamat')" class="w-full mt-1" rows="2" />

             <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.alamat')" class="mt-1" />
         </div>

         <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

             {{-- RT Domisili --}}
             <div wire:key="domisil-rt-{{ $domisilSyncTick }}">
                 <x-input-label value="RT Domisili" :required="true" />
                 <x-text-input wire:model.live="dataPasien.pasien.domisil.rt" placeholder="3 digit" :error="$errors->has('dataPasien.pasien.domisil.rt')"
                     class="w-full mt-1" />
                 <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.rt')" class="mt-1" />
             </div>

             {{-- RW Domisili --}}
             <div wire:key="domisil-rw-{{ $domisilSyncTick }}">
                 <x-input-label value="RW Domisili" :required="true" />
                 <x-text-input wire:model.live="dataPasien.pasien.domisil.rw" placeholder="3 digit" :error="$errors->has('dataPasien.pasien.domisil.rw')"
                     class="w-full mt-1" />
                 <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.rw')" class="mt-1" />
             </div>

             {{-- Kode Pos Domisili --}}
             <div wire:key="domisil-kodepos-{{ $domisilSyncTick }}">
                 <x-input-label value="Kode Pos Domisili" />
                 <x-text-input wire:model.live="dataPasien.pasien.domisil.kodepos" class="w-full mt-1" />
             </div>

         </div>


         {{-- Alamat Lengkap Domisili --}}
         <div class="grid grid-cols-1 gap-4 sm:grid-cols-1"
             wire:key="{{ $this->renderKey('alamat_domisil', [$dataPasien['pasien']['domisil']['propinsiId'] ?? '', $dataPasien['pasien']['domisil']['kotaId'] ?? '']) }}">
             {{-- Desa Domisili (cari langsung se-Indonesia, auto-fill kec/kota/prov) --}}
             <div>
                 <livewire:lov.desa.lov-desa target="desa_domisil"
                     :initialDesaId="$dataPasien['pasien']['domisil']['desaId'] ?? null" />
                 <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.desaId')" class="mt-1" />

                 {{-- Keterangan Kecamatan, Kota, Provinsi --}}
                 @if (!empty($dataPasien['pasien']['domisil']['kotaId']))
                     <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-gray-600 dark:text-gray-400">
                         @if (!empty($dataPasien['pasien']['domisil']['kecamatanName']))
                             <span>Kec. <strong class="text-gray-800 dark:text-gray-200">{{ $dataPasien['pasien']['domisil']['kecamatanName'] }}</strong></span>
                         @endif
                         <span>Kota/Kab. <strong class="text-gray-800 dark:text-gray-200">{{ $dataPasien['pasien']['domisil']['kotaName'] ?? '-' }}</strong></span>
                         <span>Prov. <strong class="text-gray-800 dark:text-gray-200">{{ $dataPasien['pasien']['domisil']['propinsiName'] ?? '-' }}</strong></span>
                     </div>
                 @endif
             </div>

             {{-- Negara Domisili --}}
             <div>
                 <x-input-label value="Negara Domisili" />
                 <x-text-input wire:model.live="dataPasien.pasien.domisil.negara" placeholder="isi dengan ID"
                     class="w-full mt-1" />
             </div>
         </div>
     </div>
 </x-border-form>
