<div class="max-w-lg space-y-5">

    {{-- ── URL ── --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            URL SIRS Kemenkes
        </label>
        <input wire:model="cfg.sirs_url"
               type="url"
               placeholder="https://sirs.kemkes.go.id/fo/index.php/"
               class="block w-full rounded-lg border border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100
                      px-3 py-2 focus:ring-2 focus:ring-brand-green focus:border-transparent
                      placeholder-gray-400 dark:placeholder-gray-500" />
        @error('cfg.sirs_url')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── RS ID ── --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Kode RS (x-rs-id)
        </label>
        <input wire:model="cfg.sirs_rs_id"
               type="text"
               placeholder="Kode RS dari Kemenkes"
               class="block w-full rounded-lg border border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100
                      px-3 py-2 focus:ring-2 focus:ring-brand-green focus:border-transparent
                      placeholder-gray-400 dark:placeholder-gray-500" />
        @error('cfg.sirs_rs_id')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── Password ── --}}
    <div x-data="{ show: false }">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Password SIRS (x-pass)
        </label>
        <div class="relative">
            <input wire:model="cfg.sirs_pass"
                   :type="show ? 'text' : 'password'"
                   placeholder="Password RS Online"
                   autocomplete="new-password"
                   class="block w-full rounded-lg border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100
                          px-3 py-2 pr-10 focus:ring-2 focus:ring-brand-green focus:border-transparent
                          placeholder-gray-400 dark:placeholder-gray-500" />
            <button type="button" @click="show = !show"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                </svg>
            </button>
        </div>

        {{-- Syarat password --}}
        <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-800/60 rounded-lg border border-gray-200 dark:border-gray-700 space-y-1.5"
             x-data="{
                 get pass() { return $wire.cfg?.sirs_pass ?? '' },
                 get hasMin()     { return this.pass.length >= 8 },
                 get hasLower()   { return /[a-z]/.test(this.pass) },
                 get hasUpper()   { return /[A-Z]/.test(this.pass) },
                 get hasDigit()   { return /[0-9]/.test(this.pass) },
                 get hasSpecial() { return /[!@#$%^&*()\-_=+\[\]{};:\'\",./<>?\\|`~]/.test(this.pass) },
             }">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Syarat Password</p>
            <template x-for="[label, ok] in [
                ['Minimal 8 karakter',          hasMin],
                ['Huruf kecil (a-z)',            hasLower],
                ['Huruf besar (A-Z)',            hasUpper],
                ['Angka (0-9)',                  hasDigit],
                ['Karakter spesial (!@#$ dll.)', hasSpecial],
            ]">
                <div class="flex items-center gap-1.5 text-xs">
                    <svg x-show="ok" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <svg x-show="!ok" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 102 0V7zm-1 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    <span :class="ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500'"
                          x-text="label"></span>
                </div>
            </template>
        </div>

        @error('cfg.sirs_pass')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── Tombol simpan & test ── --}}
    <div class="flex items-center gap-3 pt-1">
        <x-primary-button wire:click="simpanKonfigurasi" wire:loading.attr="disabled">
            <svg wire:loading wire:target="simpanKonfigurasi" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Simpan Konfigurasi
        </x-primary-button>

        <x-secondary-button wire:click="testKoneksiSirs" wire:loading.attr="disabled">
            <svg wire:loading wire:target="testKoneksiSirs" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Test Koneksi
        </x-secondary-button>
    </div>

    {{-- ── Hasil test koneksi ── --}}
    @if (!empty($cfgTestResult))
        <div class="p-3 rounded-lg border text-sm
                    {{ $cfgTestResult['ok']
                        ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300'
                        : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300' }}">
            <div class="flex items-center gap-2 font-medium">
                @if ($cfgTestResult['ok'])
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Koneksi berhasil
                @else
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Koneksi gagal
                @endif
            </div>
            <p class="mt-0.5 text-xs opacity-80">{{ $cfgTestResult['message'] }}</p>
        </div>
    @endif

    {{-- ── Info sumber config ── --}}
    <p class="text-xs text-gray-400 dark:text-gray-500 pt-1">
        Nilai disimpan langsung ke file
        <code class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">.env</code>
        (key: <code class="font-mono">SIRS_URL</code>, <code class="font-mono">SIRS_RS_ID</code>, <code class="font-mono">SIRS_PASS</code>).
    </p>

</div>
