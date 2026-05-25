@props([
    'wireModel',                       // contoh: 'formEresep.catatanKhusus' atau 'dataDaftarPoliRJ.eresep.0.catatanKhusus'
    'options' => [],                   // array string catatan (dari rsmst_signa_catatans active)
    'disabled' => false,
    'placeholder' => 'Catatan Khusus',
    'inputId' => null,                 // id input — dipakai parent untuk focus via document.getElementById
    'enterAction' => null,             // ekspresi Alpine yg dijalankan saat Enter & dropdown tertutup
    'maxlength' => 255,
])

<div class="relative w-full"
    x-data="{
        allOptions: @js(array_values($options)),
        filtered: [],
        open: false,
        highlighted: -1,
        wireModelName: @js($wireModel),

        init() { this.filter(this.$refs.cbInput ? this.$refs.cbInput.value : ''); },

        filter(q) {
            const lq = (q ?? '').toString().toLowerCase().trim();
            this.filtered = lq.length === 0
                ? this.allOptions.slice(0, 50)
                : this.allOptions.filter(o => (o ?? '').toString().toLowerCase().includes(lq)).slice(0, 50);
        },

        openDropdown() {
            this.filter(this.$refs.cbInput ? this.$refs.cbInput.value : '');
            this.open = this.filtered.length > 0;
            this.highlighted = -1;
        },

        onInput() {
            const cur = this.$refs.cbInput ? this.$refs.cbInput.value : '';
            this.filter(cur);
            this.open = this.filtered.length > 0;
            // tidak auto-highlight; user explicit via arrow / hover
            this.highlighted = -1;
        },

        clearValue() {
            this.$wire.set(this.wireModelName, '', false);
            if (this.$refs.cbInput) {
                this.$refs.cbInput.value = '';
                this.$refs.cbInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        pick(text) {
            // defer (live=false): set value tanpa AJAX immediate, supaya tidak interrupt user yg lagi ngetik.
            this.$wire.set(this.wireModelName, text, false);
            if (this.$refs.cbInput) {
                this.$refs.cbInput.value = text;
                this.$refs.cbInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        moveDown() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = 0;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted < 0
                ? 0
                : (this.highlighted + 1) % this.filtered.length;
        },

        moveUp() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = this.filtered.length - 1;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted <= 0
                ? this.filtered.length - 1
                : this.highlighted - 1;
        },
    }"
    x-on:click.outside="open = false">

    <div class="relative">
        <input
            type="text"
            autocomplete="off"
            @disabled($disabled)
            wire:model="{{ $wireModel }}"
            placeholder="{{ $placeholder }}"
            maxlength="{{ $maxlength }}"
            @if($inputId) id="{{ $inputId }}" @endif
            x-ref="cbInput"
            x-on:input="onInput()"
            x-on:keydown.escape="open = false"
            x-on:keydown.arrow-down.prevent="moveDown()"
            x-on:keydown.arrow-up.prevent="moveUp()"
            @if($enterAction)
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
                        {{ $enterAction }};
                    }
                "
            @else
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
                    }
                "
            @endif
            {{ $attributes->merge([
                'class' => trim('border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-brand-lime focus:ring-brand-lime rounded-md shadow-sm disabled:opacity-90 disabled:bg-gray-100 disabled:cursor-not-allowed w-full ' . ($disabled ? '' : 'pr-16')),
            ]) }} />

        {{-- Tombol Clear (×) — hanya muncul kalau ada nilai --}}
        @unless($disabled)
            <button type="button"
                x-show="$refs.cbInput && $refs.cbInput.value !== ''"
                x-on:click.prevent="clearValue()"
                title="Kosongkan"
                class="absolute top-1/2 right-8 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Chevron untuk buka/tutup dropdown manual --}}
            <button type="button"
                x-on:click.prevent="open ? (open = false) : openDropdown()"
                :title="open ? 'Tutup daftar' : 'Lihat daftar catatan'"
                class="absolute top-1/2 right-2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded transition">
                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        @endunless
    </div>

    <div x-show="open && filtered.length > 0"
         x-transition.opacity.duration.100ms
         class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
            <template x-for="(opt, idx) in filtered" :key="idx + '-' + opt">
                <li :class="idx === highlighted
                        ? 'bg-brand-lime/15 dark:bg-brand-lime/25 ring-1 ring-brand-lime/30'
                        : 'hover:bg-brand-lime/10 dark:hover:bg-brand-lime/20'"
                    class="w-full px-4 py-3 text-left text-gray-800 dark:text-gray-100 rounded-lg transition-colors duration-150 cursor-pointer"
                    x-on:mousedown.prevent="pick(opt)"
                    x-on:mouseenter="highlighted = idx"
                    x-text="opt"></li>
            </template>
        </ul>
    </div>
</div>
