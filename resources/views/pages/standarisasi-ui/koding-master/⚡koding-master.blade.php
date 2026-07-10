<?php

use Livewire\Component;
use Livewire\Attributes\On;

// Tutorial standarisasi koding modul MASTER — versi web dari docs/standar-master-module.md.
// Gaya navigasi per-submenu (sidebar kiri) seperti situs dokumentasi Livewire.
// Semua contoh kode disimpan sebagai nowdoc di sini agar TIDAK dikompilasi Blade
// (tag komponen / direktif di dalam string PHP aman dari compiler).
new class extends Component {
    // State & aksi demo untuk live preview di bab Pemakaian Komponen.
    // Semua no-op — tidak ada data yang ditulis.
    public string $demoText = '';
    public string $demoSelect = '';
    public ?string $demoNumber = '0';

    public function resetFilters(): void
    {
        $this->reset(['demoText', 'demoSelect']);
        $this->demoNumber = '0';
    }

    public function demoAksi(): void
    {
        $this->dispatch('toast', type: 'success', message: 'Aksi demo dijalankan — tidak ada data yang berubah.');
    }

    // Listener demo LOV (bab 08) — persis pola parent sungguhan, hanya menampung payload.
    public string $demoLovId = '';
    public string $demoLovName = '';

    #[On('lov.selected.demo-koding-master')]
    public function onDemoLovSelected(string $target, array $payload): void
    {
        $this->demoLovId   = (string) ($payload['product_id'] ?? '');
        $this->demoLovName = (string) ($payload['product_name'] ?? '');
    }

    public function snippets(): array
    {
        return [

'tree' => <<<'TXT'
resources/views/pages/master/master-<nama>/
├── ⚡master-<nama>.blade.php           # LIST : tabel + toolbar + pagination
└── ⚡master-<nama>-actions.blade.php   # FORM : modal create/edit + delete handler
TXT,

'route' => <<<'TXT'
// routes/web.php — di dalam group middleware(['auth'])
Route::livewire('/master/<nama>', 'pages::master.master-<nama>.master-<nama>')
    ->name('master.<nama>');
TXT,

'mount' => <<<'TXT'
{{-- di paling bawah markup LIST: mount FORM sebagai child --}}
<livewire:pages::master.master-<nama>.master-<nama>-actions
    wire:key="master-<nama>-actions" />
TXT,

'alur-salin' => <<<'TXT'
# dari root repo — contoh membuat master baru "pekerjaan"
cp -r resources/views/pages/master/master-agama \
      resources/views/pages/master/master-pekerjaan

cd resources/views/pages/master/master-pekerjaan
mv ⚡master-agama.blade.php         ⚡master-pekerjaan.blade.php
mv ⚡master-agama-actions.blade.php ⚡master-pekerjaan-actions.blade.php

# lalu di DALAM kedua file, cari-ganti manual (jangan sed membabi-buta):
#   master-agama       → master-pekerjaan     (nama komponen child + nama modal)
#   master.agama.*     → master.pekerjaan.*   (namespace event = nama folder)
#   rsmst_religions    → nama tabel barumu
#   rel_id / rel_desc  → kolom barumu (key $form = nama kolom DB)
#   "Agama"            → label Indonesia barumu (judul, pesan validasi, toast)
TXT,

'alur-route-menu' => <<<'TXT'
// 1) routes/web.php — di dalam group Route::middleware(['auth'])
Route::livewire('/master/pekerjaan', 'pages::master.master-pekerjaan.master-pekerjaan')
    ->name('master.pekerjaan');

// 2) app/Services/AppMenu.php — supaya modul muncul di menu dashboard (difilter role)
$entry([
    'group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13,
    'route' => 'master.pekerjaan', 'title' => 'Master Pekerjaan',
    'desc'  => 'Kelola data pekerjaan pasien',
    'roles' => $masterRoles, 'badge' => 'Pelayanan',
]),
TXT,

'event-flow' => <<<'TXT'
LIST ──dispatch('master.agama.openEdit', relId: 12)──▶ FORM   (#[On] buka modal)
FORM ──simpan ok → dispatch('master.agama.saved')  ──▶ LIST   (#[On] resetPage)
TXT,

'list-class' => <<<'TXT'
new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    // LIST hanya MENYURUH — tidak pernah menyimpan/validasi sendiri.
    public function openCreate(): void
    {
        $this->dispatch('master.agama.openCreate');
    }

    public function openEdit(int $relId): void
    {
        $this->dispatch('master.agama.openEdit', relId: $relId);
    }

    public function requestDelete(int $relId): void
    {
        $this->dispatch('master.agama.requestDelete', relId: $relId);
    }

    #[On('master.agama.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_religions')
            ->select('rel_id', 'rel_desc')
            ->orderBy('rel_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(rel_desc) LIKE ?', ["%{$kw}%"]);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
TXT,

'toolbar' => <<<'TXT'
{{-- TOOLBAR sticky di atas card tabel --}}
<div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20
            dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full lg:max-w-md">
            <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                placeholder="Cari..." class="block w-full" />
        </div>
        <div class="flex items-center justify-end gap-2">
            <div class="w-28">
                <x-select-input wire:model.live="itemsPerPage">
                    <option value="10">10</option>
                    <option value="20">20</option>
                </x-select-input>
            </div>
            <x-primary-button type="button" wire:click="openCreate">
                + Tambah Data
            </x-primary-button>
            <x-toolbar-refresh-reset :label="null" />
        </div>
    </div>
</div>
TXT,

'table' => <<<'TXT'
<table class="ds-table">
    <thead class="sticky top-0 z-10">
        <tr>
            <th>ID</th>
            <th>Agama</th>
            <th class="ds-c">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($this->rows as $row)
            <tr wire:key="agama-{{ $row->rel_id }}">
                <td class="ds-td-token">{{ $row->rel_id }}</td>
                <td class="ds-td-strong">{{ $row->rel_desc }}</td>
                <td class="ds-c">
                    <div class="flex justify-center gap-2">
                        <x-action-edit wire:click="openEdit({{ $row->rel_id }})" />
                        <x-action-delete
                            :action="'requestDelete(' . $row->rel_id . ')'"
                            title="Hapus Agama"
                            message="Yakin hapus agama {{ $row->rel_desc }}?" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-6 py-10">
                    {{-- ikon + teks: "Data agama tidak ditemukan." --}}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
TXT,

'form-class' => <<<'TXT'
new class extends Component {
    use WithRenderVersioningTrait;   // renderKey → modal remount bersih tiap buka

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'rel_id'   => '',
        'rel_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.agama.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-id');
    }

    #[On('master.agama.openEdit')]
    public function openEdit(int $relId): void
    {
        $row = DB::table('rsmst_religions')->where('rel_id', $relId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $relId;
        $this->form = [
            'rel_id'   => (string) $row->rel_id,
            'rel_desc' => (string) ($row->rel_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-desc');
    }

    public function save(): void
    {
        // validate() SELALU duluan — jangan ada early-return sebelum ini,
        // kalau tidak border merah field wajib tak pernah muncul.
        $this->validate($rules, $messages, $attributes);

        $payload = ['rel_desc' => mb_strtoupper($this->form['rel_desc'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_religions')
                ->insert(['rel_id' => (int) $this->form['rel_id'], ...$payload]);
        } else {
            DB::table('rsmst_religions')
                ->where('rel_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.agama.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-agama-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['rel_id' => '', 'rel_desc' => ''];
        $this->resetValidation();
    }
};
TXT,

'modal' => <<<'TXT'
<x-modal name="master-agama-actions" size="full" height="full" focusable>
    <x-dirty-modal-content
        name="master-agama-actions"
        event="master.agama.saved"
        label="Agama"
        :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

        {{-- HEADER: logo + judul Tambah/Ubah + badge Mode + close X (tryClose) --}}

        {{-- BODY: bg-surface-soft + x-enter-chain + fokus via event window --}}
        <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
             x-data
             x-on:focus-rel-id.window="$nextTick(() => setTimeout(() => $refs.inputRelId?.focus(), 150))">

            <x-border-form title="Data Agama">
                <div>
                    <x-input-label value="Nama Agama" />
                    <x-text-input wire:model.live="form.rel_desc" x-ref="inputRelDesc"
                        :error="$errors->has('form.rel_desc')"
                        class="w-full mt-1"
                        x-on:keydown.enter.prevent="$wire.save()" />
                    <x-input-error :messages="$errors->get('form.rel_desc')" class="mt-1" />
                </div>
            </x-border-form>
        </div>

        {{-- FOOTER: sticky bottom — hint Enter · Batal (tryClose) · Simpan --}}
        <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline">
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan</span>
                    <span wire:loading>Saving...</span>
                </x-primary-button>
            </div>
        </div>

    </x-dirty-modal-content>
</x-modal>
TXT,

'validasi-inline' => <<<'TXT'
// FORM KECIL (≤ ±5 field): tiga array inline di save() — gaya baseline master-agama
$rules = [
    'form.rel_id'   => $this->formMode === 'create'
        ? 'required|integer|min:1|max:99|unique:rsmst_religions,rel_id'
        : 'required|integer',
    'form.rel_desc' => 'required|string|max:15',
];

$messages = [
    'form.rel_id.required'   => 'ID Agama wajib diisi.',
    'form.rel_id.unique'     => 'ID Agama sudah digunakan.',
    'form.rel_desc.required' => 'Nama Agama wajib diisi.',
    'form.rel_desc.max'      => 'Nama Agama maksimal 15 karakter.',
];

$attributes = [
    'form.rel_id'   => 'ID Agama',
    'form.rel_desc' => 'Nama Agama',
];

$this->validate($rules, $messages, $attributes);
TXT,

'validasi-method' => <<<'TXT'
// FORM BESAR: pisahkan jadi method supaya save() tetap pendek
// (gaya master-poli / master-diagnosa / master-karyawan — resmi, bukan deviasi)
protected function rules(): array
{
    return [ /* ... */ ];
}

protected function messages(): array
{
    return [ /* pesan Bahasa Indonesia ... */ ];
}

protected function validationAttributes(): array
{
    return [ /* nama field manusiawi ... */ ];
}

public function save(): void
{
    $this->validate();   // otomatis pakai ketiga method di atas
    // ...
}
TXT,

'delete' => <<<'TXT'
#[On('master.agama.requestDelete')]
public function deleteAgama(int $relId): void
{
    try {
        $deleted = DB::table('rsmst_religions')->where('rel_id', $relId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Agama berhasil dihapus.');
        $this->dispatch('master.agama.saved');
    } catch (QueryException $e) {
        // Lapis 2: guard FK Oracle — tanpa ini user kena error 500
        if (str_contains($e->getMessage(), 'ORA-02292')) {
            $this->dispatch('toast', type: 'error',
                message: 'Agama tidak bisa dihapus karena masih dipakai di data pasien.');
            return;
        }
        throw $e;
    }
}
TXT,

'c-page-title' => <<<'TXT'
{{-- selalu paling atas markup list; title & subtitle plain text (tanpa HTML) --}}
<x-page-title
    title="Master Agama"
    subtitle="Kelola data agama pasien" />
TXT,

'c-input' => <<<'TXT'
{{-- trio wajib per field: label + input + error --}}
<div>
    <x-input-label value="Nama Agama" :required="true" />
    <x-text-input wire:model.live="form.rel_desc" x-ref="inputRelDesc"
        maxlength="15"
        :error="$errors->has('form.rel_desc')"
        class="w-full mt-1"
        x-on:keydown.enter.prevent="$wire.save()" />
    <x-input-error :messages="$errors->get('form.rel_desc')" class="mt-1" />
</div>

{{-- dropdown --}}
<x-select-input wire:model.live="form.kategori" :error="$errors->has('form.kategori')">
    <option value="">— pilih —</option>
    <option value="A">Kategori A</option>
</x-select-input>
TXT,

'c-number' => <<<'TXT'
{{-- SEMUA field nominal uang (harga/tarif/biaya) wajib komponen ini.
     Format ribuan otomatis saat display, sync integer bersih saat blur.
     Pakai wire:model TANPA .live — komponen sync via $wire.set() saat blur. --}}
<x-text-input-number wire:model="form.harga"
    :error="$errors->has('form.harga')"
    class="w-full mt-1"
    x-on:keydown.enter.prevent="$refs.inputBerikutnya?.focus()" />
TXT,

'c-border-form' => <<<'TXT'
{{-- pengelompok field di body modal — JANGAN bikin card manual div+h3 --}}
<x-border-form title="Data Agama">
    <div class="space-y-4">
        {{-- fields --}}
    </div>
</x-border-form>

{{-- dua section berdampingan --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-border-form title="Data Dokter"> ... </x-border-form>
    <x-border-form title="Tarif & Administrasi"> ... </x-border-form>
</div>
TXT,

'c-actions' => <<<'TXT'
{{-- kolom Aksi baris tabel — selalu pasangan ini, jangan tombol manual --}}
<x-action-edit wire:click="openEdit({{ $row->rel_id }})" />
<x-action-edit wire:click="openEdit({{ $row->rel_id }})">Ubah</x-action-edit>

<x-action-delete
    :action="'requestDelete(' . $row->rel_id . ')'"
    title="Hapus Agama"
    message="Yakin hapus agama {{ $row->rel_desc }}?" />
TXT,

'c-refresh' => <<<'TXT'
{{-- icon-only (standar toolbar list) — memanggil resetFilters() di komponen --}}
<x-toolbar-refresh-reset :label="null" />

{{-- varian dgn teks + method reset custom --}}
<x-toolbar-refresh-reset label="Aksi" resetAction="resetSemua" :iconOnly="false" />
TXT,

'c-modal' => <<<'TXT'
<x-modal name="master-agama-actions" size="full" height="full" focusable>
    <x-dirty-modal-content
        name="master-agama-actions"                              {{-- = nama modal --}}
        event="master.agama.saved"                               {{-- event reset dirty --}}
        label="Agama"                                            {{-- teks di dialog peringatan --}}
        :wireKey="$this->renderKey('modal', [$formMode, $originalId])">
        {{-- header / body / footer --}}
    </x-dirty-modal-content>
</x-modal>

{{-- tombol tutup di dalam dirty-modal-content SELALU lewat tryClose() --}}
<x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
TXT,

'c-badge' => <<<'TXT'
{{-- badge Mode di header modal --}}
<x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
</x-badge>
TXT,

'lov-mount' => <<<'TXT'
{{-- contoh nyata: master-obat-kronis-actions.
     Mode tambah = LOV; mode edit = field readonly (atau kirim initial-product-id). --}}
@if ($formMode === 'create')
    <div>
        <livewire:lov.product.lov-product
            target="master-obat-kronis"
            label="Obat (cari dari master obat)"
            placeholder="Ketik nama/kode/kandungan obat..."
            wire:key="lov-master-obat-kronis-{{ $renderVersions['modal'] ?? 0 }}" />

        {{-- error tetap milik field parent, bukan milik LOV --}}
        <x-input-error :messages="$errors->get('form.product_id')" class="mt-1" />

        @if ($form['product_id'] !== '')
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Terpilih: <span class="font-mono">{{ $form['product_id'] }}</span>
                — {{ $form['product_name'] }}
            </p>
        @endif
    </div>
@endif
TXT,

'lov-listener' => <<<'TXT'
// Nama event SELALU 'lov.selected.' . target — target harus unik per pemakaian.
#[On('lov.selected.master-obat-kronis')]
public function onProductSelected(string $target, array $payload): void
{
    $this->form['product_id']   = (string) ($payload['product_id'] ?? '');
    $this->form['product_name'] = (string) ($payload['product_name'] ?? '');
    $this->resetValidation('form.product_id');
}

// Validasi TETAP di parent — payload LOV tidak dipercaya mentah-mentah:
'form.product_id' => ['required', Rule::exists('immst_products', 'product_id')],
TXT,

'lov-anatomy' => <<<'TXT'
Props umum (semua LOV):
  target       pembeda pemakaian → jadi suffix event lov.selected.<target>
  label        judul field         placeholder   hint di input
  readonly     kunci LOV (form terkunci / mode lihat)
  initial*Id   mode edit — LOV mount langsung dalam keadaan terpilih

Perilaku bawaan (tidak perlu dikoding ulang):
  • ketik ≥ 2 huruf → cari (debounce 250ms, UPPER LIKE Oracle)
  • ketik ID persis (angka) → langsung auto-terpilih
  • keyboard: ↓ / ↑ navigasi · Enter ambil · Esc tutup
  • setelah terpilih → tampil nama + tombol "Ubah" (clearSelected)
TXT,

'level-kamar' => <<<'TXT'
// LEVEL 3 — hierarki induk-anak (master-kamar: bangsal → kamar → bed).
// Namespace event tetap satu (master.kamar.*) tapi VERB-nya spesifik per entitas:

public function openCreateKamar(): void { ... }          // bukan openCreate generik
public function openCreateBed(string $roomId): void { ... }
public function requestDeleteBed(string $bedNo, string $roomId): void { ... }

// List anak mendengarkan pilihan induk:
#[On('bangsal.selected')]
public function onBangsalSelected(string $bangsalId, string $bangsalName): void
{
    // set konteks bangsal aktif → computed rooms() otomatis terfilter
}

// Event saved membawa KONTEKS supaya refresh-nya presisi:
#[On('master.kamar.saved')]
public function afterSaved(string $entity, string $roomId = ''): void
{
    // entity 'kamar' → refresh list; entity 'bed' → cukup refresh panel detail
}
TXT,

'level-kamar-bed-actions' => <<<'TXT'
// CRUD UTUH SATU ENTITAS ANAK — ⚡master-bed-actions.blade.php (dipadatkan).
// Polanya sama persis dgn form bab 06; bedanya: KONTEKS INDUK (room_id)
// ikut di state, di validasi, dan di payload event saved.

public array $formBed = ['bed_no' => '', 'bed_desc' => '', 'room_id' => ''];

#[On('master.kamar.openCreateBed')]
public function openCreateBed(string $roomId): void
{
    $this->resetAll();
    $this->formMode           = 'create';
    $this->formBed['room_id'] = $roomId;          // konteks induk dikunci sejak awal
    $this->incrementVersion('modal');
    $this->dispatch('open-modal', name: 'master-kamar-bed');
    $this->dispatch('focus-bed-no');
}

#[On('master.kamar.openEditBed')]
public function openEditBed(string $bedNo, string $roomId): void
{
    $row = DB::table('rsmst_beds')
        ->where('bed_no', $bedNo)->where('room_id', $roomId)->first();
    if (! $row) return;
    // ... isi $formBed dari $row, formMode = 'edit', buka modal (spt bab 06)
}

public function save(): void
{
    $this->validate($rules, [], $attributes);     // validate() tetap paling atas

    if ($this->formMode === 'create') {
        // PK komposit (bed_no + room_id) → cek duplikat manual, bukan rule unique:
        $exists = DB::table('rsmst_beds')
            ->where('bed_no', $this->formBed['bed_no'])
            ->where('room_id', $this->formBed['room_id'])->exists();
        if ($exists) {
            $this->addError('formBed.bed_no', 'No Bed sudah ada di kamar ini.');
            return;
        }
        DB::table('rsmst_beds')->insert([ /* bed_no + bed_desc + room_id */ ]);
    } else {
        DB::table('rsmst_beds')
            ->where('bed_no', $this->formBed['bed_no'])
            ->where('room_id', $this->formBed['room_id'])
            ->update(['bed_desc' => $this->formBed['bed_desc'] ?: null]);
    }

    $roomId = $this->formBed['room_id'];          // ambil dulu — closeModal() me-reset form
    $this->dispatch('toast', type: 'success', message: 'Data bed berhasil disimpan.');
    $this->closeModal();
    $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $roomId);
}
TXT,

'level-kamar-delete-guard' => <<<'TXT'
// DELETE INDUK — ⚡master-bangsal-actions.blade.php.
// Lapis tambahan khas hierarki: cek anak langsung SEBELUM delete supaya
// pesannya spesifik; ORA-02292 tetap ditangkap sbg jaring pengaman FK lain.

#[On('master.kamar.deleteBangsal')]
public function deleteBangsal(string $bangsalId): void
{
    try {
        $hasRooms = DB::table('rsmst_rooms')->where('bangsal_id', $bangsalId)->exists();
        if ($hasRooms) {
            $this->dispatch('toast', type: 'error',
                message: 'Bangsal tidak bisa dihapus karena masih memiliki kamar.');
            return;
        }

        $deleted = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data bangsal tidak ditemukan.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Bangsal berhasil dihapus.');
        $this->dispatch('master.kamar.saved', entity: 'bangsal');
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'ORA-02292')) {
            $this->dispatch('toast', type: 'error',
                message: 'Bangsal tidak bisa dihapus karena masih dipakai di data lain.');
            return;
        }
        throw $e;
    }
}
TXT,

'level-kamar-refresh' => <<<'TXT'
// REFRESH PRESISI — sisi list (⚡master-kamar.blade.php).
// SATU listener utk semua entitas; payload menentukan bagian mana yang
// disegarkan — save bed tidak perlu memuat ulang tabel kamar.

#[On('master.kamar.saved')]
public function afterSaved(string $entity, string $roomId = ''): void
{
    if ($entity === 'kamar') {
        unset($this->computedPropertyCache);          // buang cache computed rooms()
        $this->resetPage('pageKamar');
        if ($this->selectedRoomId) {
            $this->selectRoom($this->selectedRoomId); // segarkan panel detail
        }
    }

    if ($entity === 'bed' && $roomId) {
        if ($roomId === $this->selectedRoomId) {
            $this->loadBeds();                        // cukup panel bed, bukan seluruh list
        }
        unset($this->computedPropertyCache);
    }
}
TXT,

'level-jm' => <<<'TXT'
// LEVEL 3 — sub-list di dalam form (master-jasa-medis: paket obat/lain-lain).
// Tiap sub-form punya LOV sendiri (target unik) + validate() BERTAHAP sendiri:

#[On('lov.selected.paket-obat-master-jm')]
public function onObatSelected(?array $payload): void { ... }

public function addPaketObat(): void
{
    $this->validate($rulesPaketObat, $messagesPaketObat); // HANYA field sub-form
    $this->form['paketObat'][] = [ /* payload LOV + qty */ ];
    // reset field sub-form utk entri berikutnya
}

public function removePaketObat(int $idx): void
{
    unset($this->form['paketObat'][$idx]);
    $this->form['paketObat'] = array_values($this->form['paketObat']);
}

public function save(): void
{
    $this->validate($rules, $messages);   // validasi form UTAMA (terakhir)
    // simpan header, lalu loop insert baris detail paket
}
TXT,

'pasien-tree' => <<<'TXT'
master-pasien/
├── ⚡master-pasien.blade.php                       # LIST
├── ⚡master-pasien-actions.blade.php               # state + save + tab (Alpine activeTab)
├── master-pasien-actions-identitas.blade.php       # partial murni (tanpa kelas Volt)
├── master-pasien-actions-alamat-identitas.blade.php
├── master-pasien-actions-data-sosial.blade.php
└── ...                                             # satu partial per section/tab
TXT,

        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        // Sidebar per-submenu (gaya docs Livewire). Key = id section di bawah.
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'alur'        => 'Alur: Buat Master Baru',
                'struktur'    => 'Struktur File & Routing',
                'penamaan'    => 'Kontrak Penamaan',
            ],
            'Komponen' => [
                'list'     => 'Halaman List',
                'form'     => 'Form Modal (Actions)',
                'komponen' => 'Pemakaian Komponen',
                'lov'      => 'LOV (List of Values)',
                'anatomi'  => 'Anatomi Visual (UI/UX)',
            ],
            'Aturan' => [
                'validasi' => 'Validasi',
                'delete'   => 'Delete & ORA-02292',
                'partial'  => 'Ukuran File & Partial',
            ],
            'Lanjutan' => [
                'varian'    => 'Varian & Level Kompleksitas',
                'checklist' => 'Checklist & Referensi',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));

        // Style bantu untuk bab Anatomi Visual (badge nomor zona + input mock).
        $badge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1';
        $mockInput = 'height:36px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center';
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('standarisasi-ui') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Master</span>
                </div>
                <x-theme-toggle />
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR (per-submenu) ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Sumber: <span class="ds-code">docs/standar-master-module.md</span><br>
                            Acuan kanonik: <span class="ds-code">master-agama</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Koding Modul Master</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tutorial ini adalah versi web dari <span class="ds-code" style="color:var(--primary)">docs/standar-master-module.md</span> —
                            standar resmi cara menulis modul master (CRUD list + form) di SIRUS:
                            <strong>Laravel 12 + Livewire/Volt 4 + Oracle</strong>. Tujuannya satu:
                            semua modul master memakai <strong>SATU pola yang sama</strong>, sehingga kode
                            ringkas, mudah di-review, dan programmer baru cukup hafal satu bentuk.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Acuan kanonik adalah modul <strong>Master Agama</strong>
                            (<span class="ds-code">resources/views/pages/master/master-agama/</span>) —
                            implementasi terbersih generasi design-system <span class="ds-code">ds-*</span>:
                            hanya 154 baris untuk list dan 229 baris untuk form, tapi lengkap dengan
                            pencarian, pagination, modal dirty-guard, validasi, dan guard FK Oracle.
                        </p>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prinsip inti:</strong> satu modul = dua file (LIST + FORM).
                                LIST hanya menampilkan &amp; menyuruh lewat event; semua tulis-DB dan
                                validasi hidup di FORM. Kalau kamu menulis <span class="ds-code">validate()</span>
                                atau <span class="ds-code">insert()</span> di file LIST, berhenti — itu salah tempat.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">2 File / Modul</div>
                                <div class="ds-body-sm">List + Actions, Volt SFC anonymous class, tanpa Controller.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Event Bernamespace</div>
                                <div class="ds-body-sm">Komunikasi list ↔ form via <span class="ds-code">master.&lt;folder&gt;.*</span> — tidak ada pemanggilan method lintas komponen.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Aman Oracle</div>
                                <div class="ds-body-sm">Pencarian UPPER LIKE, delete dengan guard ORA-02292, kolom string kosong = NULL.</div>
                            </div>
                        </div>

                        <p class="ds-body-md mt-8" style="max-width:62ch">
                            <strong>Baru pertama kali membuat master?</strong> Mulai dari bab
                            <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                x-on:click="go('alur')">Alur: Buat Master Baru</button>
                            — peta jalan 9 langkah dari salin baseline sampai checklist merge;
                            tiap langkah menunjuk bab referensi detailnya. Bab-bab lain di menu kiri
                            adalah referensi yang bisa dibaca lepas.
                        </p>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Bukan developer?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('anatomi')">Anatomi Visual (UI/UX)</button>
                                — mockup halaman list, modal form, LOV, dan alur event dengan zona bernomor,
                                tanpa perlu membaca kode.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ALUR ====== --}}
                    <section x-show="section === 'alur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur: Buat Master Baru</h1>
                        <p class="ds-body-md mb-8" style="max-width:62ch">
                            Peta jalan dari nol sampai siap merge — kerjakan <strong>berurutan</strong>.
                            Prinsipnya: tidak pernah menulis dari kosong; selalu salin baseline
                            <span class="ds-code">master-agama</span> lalu sesuaikan. Tiap langkah
                            menunjuk bab referensi yang membahas detailnya.
                        </p>

                        @php
                            $alurCircle = 'display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9999px;background:var(--primary);color:#fff;font-weight:700;font-size:14px;flex:none';
                            $alurSteps = [
                                [
                                    't' => 'Kenali tabel & tentukan level',
                                    'd' => 'Sebelum menulis kode: pastikan tabel Oracle-nya — nama tabel (biasanya <span class="ds-code">rsmst_*</span>), primary key, kolom wajib, dan ada/tidaknya kolom <span class="ds-code">active_status</span> (\'1\'/\'0\'). Lalu tentukan level: CRUD satu tabel = <strong>Level 1</strong> · ada FK / LOV / toggle status = <strong>Level 2</strong> · induk-anak seperti bangsal→kamar→bed = <strong>Level 3</strong>. Kalau ragu, mulai Level 1.',
                                    'go' => 'varian', 'label' => 'Bab 13 · Varian & Level Kompleksitas',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Salin baseline master-agama',
                                    'd' => 'Salin folder acuan kanonik, ganti nama kedua file ⚡, lalu cari-ganti identitas domain di dalamnya. Selesai langkah ini, modulmu sudah jalan — tinggal disesuaikan.',
                                    'go' => 'struktur', 'label' => 'Bab 03 · Struktur File & Routing',
                                    'snip' => 'alur-salin', 'sniptitle' => 'Terminal — salin & ganti nama',
                                ],
                                [
                                    't' => 'Daftarkan route & menu',
                                    'd' => 'Route eksplisit di <span class="ds-code">routes/web.php</span> (repo ini tidak memakai auto-discovery), lalu tambahkan entri di <span class="ds-code">app/Services/AppMenu.php</span> supaya modul muncul di menu dashboard sesuai role penggunanya.',
                                    'go' => null, 'label' => null,
                                    'snip' => 'alur-route-menu', 'sniptitle' => 'routes/web.php + AppMenu.php',
                                ],
                                [
                                    't' => 'Kerjakan file LIST',
                                    'd' => 'Sesuaikan kolom tabel &amp; query <span class="ds-code">rows()</span>. Pertahankan kontrak penamaan: <span class="ds-code">searchKeyword</span>, <span class="ds-code">itemsPerPage</span>, <span class="ds-code">resetFilters()</span>, event <span class="ds-code">master.&lt;folder&gt;.*</span> (Bab 04). Ingat prinsip inti: LIST tidak pernah validasi / menulis DB — tombolnya hanya mengirim event.',
                                    'go' => 'list', 'label' => 'Bab 05 · Halaman List',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Kerjakan file FORM (actions)',
                                    'd' => 'Key <span class="ds-code">$form</span> = nama kolom DB. Sesuaikan <span class="ds-code">openCreate</span> / <span class="ds-code">openEdit</span> / <span class="ds-code">save()</span> / <span class="ds-code">closeModal()</span> dan nama modal yang unik. PK di-<span class="ds-code">:disabled</span> saat edit; rule <span class="ds-code">unique</span> hanya saat create.',
                                    'go' => 'form', 'label' => 'Bab 06 · Form Modal (Actions)',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Rapikan validasi & delete guard',
                                    'd' => 'Pesan validasi <strong>selalu Bahasa Indonesia</strong> dan <span class="ds-code">validate()</span> paling atas di save() (Bab 10). Handler delete menangkap <span class="ds-code">ORA-02292</span> menjadi toast yang manusiawi (Bab 11) — tanpa ini user melihat error 500.',
                                    'go' => 'validasi', 'label' => 'Bab 10 · Validasi',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Cocokkan tampilan',
                                    'd' => 'Bandingkan halamanmu dengan mockup zona bernomor di Anatomi Visual. Pakai komponen standar — <span class="ds-code">x-text-input</span>, <span class="ds-code">x-primary-button</span>, <span class="ds-code">x-action-edit/delete</span>, <span class="ds-code">x-modal</span> (Bab 07). Ada field yang merujuk master lain? Pakai LOV (Bab 08), jangan dropdown ribuan baris.',
                                    'go' => 'anatomi', 'label' => 'Bab 09 · Anatomi Visual (UI/UX)',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Uji CRUD ujung-ke-ujung',
                                    'd' => 'Jalankan semuanya di browser: tambah (Enter di field terakhir = simpan), edit, cari + reset, pagination, tutup modal saat ada perubahan belum disimpan (dirty-guard harus konfirmasi), dan hapus baris yang sudah dipakai transaksi — harus muncul toast merah, bukan error 500.',
                                    'go' => null, 'label' => null,
                                    'snip' => null, 'sniptitle' => null,
                                ],
                                [
                                    't' => 'Checklist sebelum PR',
                                    'd' => 'Pastikan semua butir checklist hijau; LIST ≤ ±300 baris, FORM ≤ ±400 baris (lebih dari itu → pecah partial, Bab 12). Kerjakan di branch <span class="ds-code">develop</span> / feature branch, lalu ajukan PR.',
                                    'go' => 'checklist', 'label' => 'Bab 14 · Checklist & Referensi',
                                    'snip' => null, 'sniptitle' => null,
                                ],
                            ];
                        @endphp

                        <div>
                            @foreach ($alurSteps as $st)
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <span style="{{ $alurCircle }}">{{ $loop->iteration }}</span>
                                        @if (! $loop->last)
                                            <span class="flex-1" style="width:2px; background:var(--hairline); margin-top:4px"></span>
                                        @endif
                                    </div>
                                    <div class="flex-1 {{ $loop->last ? '' : 'pb-8' }}" style="min-width:0">
                                        <div class="ds-title-sm mb-1" style="padding-top:4px">{{ $st['t'] }}</div>
                                        <p class="ds-body-sm" style="max-width:62ch">{!! $st['d'] !!}</p>
                                        @if ($st['go'])
                                            <button type="button" class="mt-2 text-sm font-semibold hover:underline" style="color:var(--primary)"
                                                x-on:click="go('{{ $st['go'] }}')">→ {{ $st['label'] }}</button>
                                        @endif
                                        @if ($st['snip'])
                                            <div class="ds-card-dark mt-3" style="padding:0; overflow:hidden">
                                                <div class="px-4 py-2" style="background:var(--surface-dark-soft)">
                                                    <span class="ds-caption-up" style="color:var(--on-dark-soft)">{{ $st['sniptitle'] }}</span>
                                                </div>
                                                <pre class="ds-code" style="margin:0; padding:16px 20px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip[$st['snip']] }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Contoh hidup per level:</strong>
                                Level 1 → <span class="ds-code">master-agama</span> ·
                                Level 2 → <span class="ds-code">master-obat</span> / <span class="ds-code">master-dokter</span> ·
                                Level 3 → <span class="ds-code">master-kamar</span> (bangsal→kamar→bed).
                                Buka kodenya berdampingan dengan tutorial ini.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 STRUKTUR ====== --}}
                    <section x-show="section === 'struktur'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur File &amp; Routing</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu modul master = <strong>satu folder, dua file Volt SFC</strong>.
                            Nama folder dan nama file selalu memakai prefix <span class="ds-code">master-</span>
                            dan kebab-case.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Struktur folder</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['tree'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Routing <strong>eksplisit</strong> di <span class="ds-code">routes/web.php</span>
                            (repo ini tidak memakai Folio auto-discovery). Selalu beri nama route
                            <span class="ds-code">master.&lt;nama&gt;</span>:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">routes/web.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['route'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            LIST me-mount FORM sebagai <strong>child component</strong> di akhir markup —
                            modal form selalu siap menerima event tanpa perlu route sendiri:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">⚡master-&lt;nama&gt;.blade.php (paling bawah)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['mount'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Boleh menyimpang dari struktur 2-file hanya untuk <strong>3 varian resmi</strong>
                                (master-detail hierarkis, form bertab, single-file integrasi) —
                                lihat bab <em>Varian Resmi</em>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 PENAMAAN ====== --}}
                    <section x-show="section === 'penamaan'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Kontrak Penamaan</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Nama state, method, dan event <strong>tidak bebas</strong> — semuanya kontrak.
                            Reviewer harus bisa menebak isi file tanpa membukanya.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Hal</th><th>Standar</th><th>Catatan</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">State pencarian</td><td class="ds-td-class">searchKeyword</td><td class="ds-body-sm">+ updatedSearchKeyword() → resetPage()</td></tr>
                                    <tr><td class="ds-td-strong">Per halaman</td><td class="ds-td-class">itemsPerPage</td><td class="ds-body-sm">default 10</td></tr>
                                    <tr><td class="ds-td-strong">Reset filter</td><td class="ds-td-class">resetFilters()</td><td class="ds-body-sm">dipanggil x-toolbar-refresh-reset</td></tr>
                                    <tr><td class="ds-td-strong">Data list</td><td class="ds-td-class">#[Computed] rows()</td><td class="ds-body-sm">akses $this->rows di markup</td></tr>
                                    <tr><td class="ds-td-strong">Namespace event</td><td class="ds-td-class">master.&lt;namafolder&gt;.*</td><td class="ds-body-sm">HARUS sama dgn nama folder</td></tr>
                                    <tr><td class="ds-td-strong">Verb event</td><td class="ds-td-class">openCreate · openEdit · requestDelete · saved</td><td class="ds-body-sm">hanya 4 ini utk CRUD standar</td></tr>
                                    <tr><td class="ds-td-strong">Mode form</td><td class="ds-td-class">$formMode + $originalId</td><td class="ds-body-sm">'create' | 'edit'</td></tr>
                                    <tr><td class="ds-td-strong">State form</td><td class="ds-td-class">array $form</td><td class="ds-body-sm">key = nama kolom DB</td></tr>
                                    <tr><td class="ds-td-strong">wire:key baris</td><td class="ds-td-class">&lt;slug&gt;-{pk}</td><td class="ds-body-sm">contoh: agama-12</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Alur komunikasi list ↔ form <strong>selalu lewat event</strong>, tidak pernah
                            memanggil method komponen lain secara langsung:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Alur event</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['event-flow'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Jangan tiru deviasi lama:</strong> <span class="ds-code">master.class.*</span>
                                (kelas-rawat) dan <span class="ds-code">master.diagkep.*</span> (diag-keperawatan)
                                tidak mengikuti nama folder — keduanya masuk backlog penyeragaman, bukan contoh.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 05 LIST ====== --}}
                    <section x-show="section === 'list'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Halaman List</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kelas PHP komponen LIST hanya berisi: state filter, dispatch event, dan satu
                            computed <span class="ds-code">rows()</span>. Query memakai
                            <span class="ds-code">DB::table()</span> (bukan Eloquent) dan pencarian
                            case-insensitive Oracle: <span class="ds-code">UPPER(kolom) LIKE</span> +
                            <span class="ds-code">mb_strtoupper</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kelas PHP — ⚡master-agama.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['list-class'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Markup mengikuti urutan wajib: <span class="ds-code">x-page-title</span> →
                            frame flex-fill <span class="ds-code">h-[calc(100vh-5rem)]</span> →
                            toolbar sticky → card tabel → pagination sticky. Toolbar standar:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Toolbar</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['toolbar'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Tabel memakai kelas <span class="ds-code">ds-table</span> — jangan menulis ulang
                            kelas header/padding manual. Sel ID pakai <span class="ds-code">ds-td-token</span>
                            (mono), sel nama utama <span class="ds-code">ds-td-strong</span>, kolom tengah
                            <span class="ds-code">ds-c</span>. Aksi baris selalu
                            <span class="ds-code">x-action-edit</span> + <span class="ds-code">x-action-delete</span>:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Tabel + aksi baris + empty state</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['table'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Query kompleks (banyak join / dipakai ulang untuk export)? Pisahkan
                                <span class="ds-code">baseQuery()</span> privat, lalu <span class="ds-code">rows()</span>
                                tinggal <span class="ds-code">-&gt;paginate()</span> — contoh: <span class="ds-code">master-obat</span>.
                                Detail frame &amp; empty state: <span class="ds-code">docs/page-frame-pattern.md</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 06 FORM ====== --}}
                    <section x-show="section === 'form'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Form Modal (Actions)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            File <span class="ds-code">-actions</span> memegang <strong>seluruh</strong> logika tulis:
                            buka modal, validasi, simpan, hapus. Ia memakai
                            <span class="ds-code">WithRenderVersioningTrait</span> supaya modal
                            di-remount bersih setiap kali dibuka (tidak ada sisa state/validasi lama).
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kelas PHP — ⚡master-agama-actions.blade.php (inti)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['form-class'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Markup modal = 3 bagian tetap (header / body / footer) dibungkus
                            <span class="ds-code">x-dirty-modal-content</span> — user yang menutup modal
                            dengan perubahan belum tersimpan otomatis dikonfirmasi:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Markup modal (kerangka)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['modal'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Wajib di body</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">x-enter-chain</span> + Enter di field terakhir = simpan</li>
                                    <li>Fokus otomatis field pertama saat modal terbuka (event window + x-ref)</li>
                                    <li>Tiap field: <span class="ds-code">:error</span> + <span class="ds-code">x-input-error</span> di bawahnya</li>
                                    <li>Field nominal uang → <span class="ds-code">x-text-input-number</span></li>
                                    <li>Section field dibungkus <span class="ds-code">x-border-form</span></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Alur wajib method</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>open*</strong>: resetForm → set mode → incrementVersion → open-modal → fokus</li>
                                    <li><strong>save</strong>: validate → tulis DB → toast → closeModal → event saved</li>
                                    <li><strong>closeModal</strong>: resetForm → close-modal → resetVersion</li>
                                    <li>Tutup via tombol selalu <span class="ds-code">tryClose()</span> (dirty-guard), bukan closeModal langsung</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 07 PEMAKAIAN KOMPONEN ====== --}}
                    <section x-show="section === 'komponen'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Pemakaian Komponen</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Katalog komponen Blade yang dipakai modul master — semuanya di
                            <span class="ds-code">resources/views/components/</span>. Aturan utamanya sederhana:
                            <strong>kalau komponennya ada, pakai — jangan tulis markup manual</strong>.
                        </p>

                        {{-- peta komponen --}}
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Komponen</th><th>Dipakai di</th><th>Props kunci</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">x-page-title</td><td class="ds-body-sm">atas semua halaman list</td><td class="ds-td-meta">title · subtitle</td></tr>
                                    <tr><td class="ds-td-class">x-toolbar-refresh-reset</td><td class="ds-body-sm">kanan toolbar list</td><td class="ds-td-meta">label · resetAction · iconOnly</td></tr>
                                    <tr><td class="ds-td-class">x-action-edit / x-action-delete</td><td class="ds-body-sm">kolom Aksi tabel</td><td class="ds-td-meta">action · title · message</td></tr>
                                    <tr><td class="ds-td-class">x-input-label / x-input-error</td><td class="ds-body-sm">pasangan tiap field</td><td class="ds-td-meta">value · required / messages</td></tr>
                                    <tr><td class="ds-td-class">x-text-input / x-select-input</td><td class="ds-body-sm">field form</td><td class="ds-td-meta">error · disabled</td></tr>
                                    <tr><td class="ds-td-class">x-text-input-number</td><td class="ds-body-sm">SEMUA field nominal uang</td><td class="ds-td-meta">error · disabled · extraBlur</td></tr>
                                    <tr><td class="ds-td-class">x-border-form</td><td class="ds-body-sm">section field di body modal</td><td class="ds-td-meta">title · align · bgcolor · padding</td></tr>
                                    <tr><td class="ds-td-class">x-modal</td><td class="ds-body-sm">wrapper form actions</td><td class="ds-td-meta">name · size (md–full) · height · focusable</td></tr>
                                    <tr><td class="ds-td-class">x-dirty-modal-content</td><td class="ds-body-sm">isi modal (dirty-guard)</td><td class="ds-td-meta">name · event · label · wireKey</td></tr>
                                    <tr><td class="ds-td-class">x-badge</td><td class="ds-body-sm">badge Mode header modal</td><td class="ds-td-meta">variant (success|warning|info|...)</td></tr>
                                    <tr><td class="ds-td-class">x-primary/secondary/icon-button</td><td class="ds-body-sm">Simpan · Batal/Edit · close X</td><td class="ds-td-meta">type · disabled · color</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- A. halaman & list --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Halaman &amp; List</h2>

                        {{-- live preview: komponen aksi asli, no-op --}}
                        <div class="ds-frame mt-2">
                            <div class="ds-frame-label">Tampilan — silakan diklik (aksi demo, tidak mengubah data)</div>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <x-action-edit wire:click="demoAksi" />
                                <x-action-delete :action="'demoAksi'"
                                    title="Hapus Demo"
                                    message="Ini dialog konfirmasi asli milik x-action-delete — aman diklik." />
                                <x-toolbar-refresh-reset :label="null" />
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Tombol Hapus memunculkan dialog konfirmasi bawaan; ⟳ memuat ulang komponen; ↩ mereset filter demo.
                            </p>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-page-title</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-page-title'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-action-edit · x-action-delete</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-actions'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-toolbar-refresh-reset</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-refresh'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <span class="ds-code">x-action-delete</span> adalah pembungkus
                                <span class="ds-code">x-confirm-button variant="danger"</span> + ikon sampah —
                                dialog konfirmasi sudah termasuk. JANGAN pakai <span class="ds-code">wire:confirm</span>
                                (dialog browser native) atau tombol hapus manual.
                            </span>
                        </div>

                        {{-- B. form & input --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Form &amp; Input</h2>

                        {{-- live preview: trio field + number + error state, dibungkus x-border-form asli --}}
                        <div class="ds-frame mt-2">
                            <div class="ds-frame-label">Tampilan — silakan diketik</div>
                            <div class="mt-3">
                                <x-border-form title="Data Contoh (x-border-form)">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="Nama (x-text-input)" :required="true" />
                                            <x-text-input wire:model.live.debounce.300ms="demoText"
                                                placeholder="Ketik sesuatu..." class="w-full mt-1" />
                                            <p class="mt-1 text-xs" style="color:var(--muted)">
                                                Nilai tersinkron: <span class="ds-code" style="color:var(--primary)">{{ $demoText !== '' ? $demoText : '—' }}</span>
                                            </p>
                                        </div>
                                        <div>
                                            <x-input-label value="Kategori (x-select-input)" />
                                            <x-select-input wire:model.live="demoSelect" class="w-full mt-1">
                                                <option value="">— pilih —</option>
                                                <option value="A">Kategori A</option>
                                                <option value="B">Kategori B</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Harga (x-text-input-number)" />
                                            <x-text-input-number wire:model="demoNumber" class="w-full mt-1" />
                                            <p class="mt-1 text-xs" style="color:var(--muted)">
                                                Ketik angka lalu klik di luar → otomatis berformat ribuan.
                                            </p>
                                        </div>
                                        <div>
                                            <x-input-label value="Kondisi error (x-input-error)" />
                                            <x-text-input :error="true" placeholder="Border merah saat gagal validasi"
                                                class="w-full mt-1" />
                                            <x-input-error :messages="['Contoh pesan validasi Bahasa Indonesia.']" class="mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-input-label · x-text-input · x-input-error · x-select-input</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-input'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-text-input-number (nominal uang)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-number'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-border-form</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-border-form'] }}</pre>
                        </div>

                        {{-- C. modal --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Modal</h2>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-modal + x-dirty-modal-content</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-modal'] }}</pre>
                        </div>

                        {{-- live preview: semua varian badge --}}
                        <div class="ds-frame mt-4">
                            <div class="ds-frame-label">Tampilan — 8 varian x-badge</div>
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                @foreach (['brand', 'alternative', 'gray', 'danger', 'success', 'warning', 'info', 'purple'] as $variantName)
                                    <x-badge :variant="$variantName">{{ $variantName }}</x-badge>
                                @endforeach
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Di modal master hanya dua yang dipakai: <strong>success</strong> (Mode: Tambah) dan <strong>warning</strong> (Mode: Edit).
                            </p>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">x-badge (Mode: Tambah / Edit)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['c-badge'] }}</pre>
                        </div>

                        {{-- D. tombol --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Tombol</h2>

                        {{-- live preview: tombol asli, aksi no-op --}}
                        <div class="ds-frame mt-2 mb-4">
                            <div class="ds-frame-label">Tampilan — silakan diklik (aksi demo)</div>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <x-primary-button type="button" wire:click="demoAksi" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="demoAksi">Simpan</span>
                                    <span wire:loading wire:target="demoAksi">Saving...</span>
                                </x-primary-button>
                                <x-secondary-button type="button">Batal</x-secondary-button>
                                <x-icon-button color="gray" type="button" title="Close X standar header modal">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </x-icon-button>
                                <x-confirm-button variant="danger" :action="'demoAksi'"
                                    title="Hapus Data"
                                    message="Ini dialog konfirmasi asli x-confirm-button — aman diklik."
                                    confirmText="Ya, hapus" cancelText="Batal">
                                    Hapus
                                </x-confirm-button>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Komponen</th><th>Kegunaan di modul master</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-class">x-primary-button</td><td class="ds-body-sm">Simpan (SATU per modal) + tombol Tambah di toolbar — selalu dgn <span class="ds-code">wire:loading.attr="disabled"</span></td></tr>
                                    <tr><td class="ds-td-class">x-secondary-button</td><td class="ds-body-sm">Batal (via <span class="ds-code">tryClose()</span>)</td></tr>
                                    <tr><td class="ds-td-class">x-icon-button color="gray"</td><td class="ds-body-sm">close X di pojok header modal</td></tr>
                                    <tr><td class="ds-td-class">x-confirm-button</td><td class="ds-body-sm">aksi berbahaya non-hapus-baris (hapus baris tabel pakai x-action-delete)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Varian, ukuran, dan warna lengkap semua tombol:
                                <span class="ds-code">docs/standar-komponen-tombol.md</span>.
                                Katalog visual seluruh komponen (dgn demo interaktif): halaman
                                <a href="{{ route('standarisasi-ui') }}" wire:navigate class="hover:underline" style="color:var(--primary)">Standarisasi UI</a>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 LOV ====== --}}
                    <section x-show="section === 'lov'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Komponen</div>
                        <h1 class="ds-display-md mb-4">LOV (List of Values)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            LOV adalah <strong>child Livewire component siap-pakai</strong> untuk field
                            yang merujuk ke master lain (FK): cari sambil ketik → pilih → parent menerima
                            payload. Tersedia <strong>34 LOV</strong> di
                            <span class="ds-code">resources/views/livewire/lov/&lt;entitas&gt;/lov-&lt;entitas&gt;.blade.php</span> —
                            obat, dokter, poli, pasien, diagnosa, kamar, akun, supplier, dan lainnya.
                            <strong>Jangan membangun dropdown pencarian manual</strong> — pakai LOV yang ada,
                            atau salin LOV yang paling mirip lalu ganti query &amp; payload-nya.
                        </p>

                        <div class="ds-card-outline mt-2" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kontrak 3 langkah:</strong>
                                (1) mount LOV dgn <span class="ds-code">target</span> unik +
                                <span class="ds-code">wire:key</span> ber-renderVersions,
                                (2) LOV dispatch <span class="ds-code">lov.selected.&lt;target&gt;</span> saat user memilih,
                                (3) parent menangkap via <span class="ds-code">#[On]</span> lalu mengisi
                                <span class="ds-code">$form</span>.
                            </span>
                        </div>

                        {{-- live preview: LOV product asli + payload yang tertangkap listener demo --}}
                        <div class="ds-frame mt-6">
                            <div class="ds-frame-label">Tampilan — LOV asli (lov-product), mencari data obat sungguhan</div>
                            <div class="grid grid-cols-1 gap-4 mt-3 lg:grid-cols-2">
                                <div>
                                    <livewire:lov.product.lov-product
                                        target="demo-koding-master"
                                        label="Obat (cari dari master obat)"
                                        placeholder="Ketik nama/kode/kandungan obat..."
                                        wire:key="lov-demo-koding-master" />
                                    <p class="ds-caption mt-2" style="color:var(--muted)">
                                        Ketik ≥ 2 huruf (mis. "para") · ↓ ↑ navigasi · Enter ambil · Esc tutup.
                                    </p>
                                </div>
                                <div class="ds-card-outline" style="padding:16px">
                                    <div class="ds-caption-up mb-2">Payload yang diterima parent (langkah 3)</div>
                                    @if ($demoLovId !== '')
                                        <p class="ds-body-sm">
                                            <span class="ds-code" style="color:var(--primary)">lov.selected.demo-koding-master</span> tertangkap:<br>
                                            <span class="ds-td-token">product_id&nbsp;&nbsp;= {{ $demoLovId }}</span><br>
                                            <span class="ds-td-token">product_name = {{ $demoLovName }}</span>
                                        </p>
                                    @else
                                        <p class="ds-body-sm" style="color:var(--muted)">
                                            Belum ada pilihan — pilih obat di kiri, payload event akan tampil di sini
                                            (persis yang diterima <span class="ds-code">#[On]</span> di form parent).
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah 1 — mount di markup parent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-mount'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Langkah 2 &amp; 3 — listener di kelas parent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-listener'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Props &amp; perilaku bawaan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lov-anatomy'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Wajib diingat</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">target</span> unik per form — kalau dua form memakai LOV sama dgn target sama, keduanya menangkap event yang sama</li>
                                    <li><span class="ds-code">wire:key</span> WAJIB menyertakan <span class="ds-code">renderVersions</span> — tanpa itu state LOV lama nyangkut saat modal dibuka ulang</li>
                                    <li>Simpan <strong>id + nama</strong> ke <span class="ds-code">$form</span> dari payload; validasi <span class="ds-code">Rule::exists</span> tetap di parent</li>
                                    <li>Error validasi ditampilkan parent (<span class="ds-code">x-input-error</span> di bawah LOV), bukan di dalam LOV</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Mode edit &amp; terkunci</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Nilai FK <strong>tidak boleh diubah</strong> saat edit → tampilkan field readonly, LOV hanya di mode create (contoh: master-obat-kronis)</li>
                                    <li>Nilai FK <strong>boleh diubah</strong> saat edit → kirim <span class="ds-code">initial*Id</span>, LOV mount langsung dalam keadaan terpilih</li>
                                    <li>Form terkunci / mode lihat → <span class="ds-code">:readonly="true"</span> (tombol "Ubah" hilang)</li>
                                </ul>
                            </div>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-3">LOV yang tersedia</h2>
                        <div class="ds-card-outline" style="padding:20px">
                            <div class="flex flex-wrap gap-2">
                                @foreach ([
                                    'akun', 'akun-ci', 'akun-co', 'asuhan-keperawatan', 'cat-product', 'clabitem-group',
                                    'desa', 'diag-kep', 'diagnosa', 'dokter', 'group-akun', 'group-product',
                                    'jasa-dokter', 'jasa-karyawan', 'jasa-medis', 'kabupaten', 'kas', 'kasir',
                                    'kelas-kamar', 'lain-lain', 'loinc', 'outs', 'pasien', 'poli',
                                    'procedure', 'product', 'product-non', 'propinsi', 'radiologi', 'room',
                                    'snomed', 'stocklocation', 'supplier', 'uom',
                                ] as $lovName)
                                    <span class="ds-badge-pill">lov-{{ $lovName }}</span>
                                @endforeach
                            </div>
                            <p class="ds-body-sm mt-4" style="color:var(--muted)">
                                Butuh LOV entitas baru? Salin folder LOV yang paling mirip
                                (acuan bersih: <span class="ds-code">lov/product</span>), ganti query +
                                bentuk payload, pertahankan seluruh kontrak <span class="ds-code">target</span>/event
                                dan navigasi keyboard-nya.
                            </p>
                        </div>
                    </section>

                    {{-- ====== 09 ANATOMI VISUAL ====== --}}
                    <section x-show="section === 'anatomi'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Anatomi Visual (UI/UX)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Bab ini untuk yang bekerja di sisi <strong>UI/UX</strong> — tanpa perlu membaca kode.
                            Setiap mockup di bawah dirender dengan token design-system asli
                            (warna &amp; kelas <span class="ds-code">ds-*</span> yang sama dengan aplikasi),
                            dan tiap zona bernomor dipetakan ke nama komponennya di legenda.
                        </p>

                        {{-- ===== A. ANATOMI HALAMAN LIST ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">A · Halaman List</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">

                            {{-- topbar --}}
                            <div class="flex items-center gap-3 px-4 py-2.5" style="position:relative; background:var(--surface-dark)">
                                <span style="width:22px;height:22px;border-radius:6px;background:var(--accent-lime);display:inline-block"></span>
                                <span class="ds-title-sm" style="color:var(--on-dark)">RSI&nbsp;Madinah</span>
                                <span class="px-2.5 py-1 text-xs rounded-full" style="background:var(--surface-dark-elevated); color:var(--on-dark-soft)">
                                    <strong style="color:var(--on-dark)">Master Agama</strong> — Kelola data agama pasien
                                </span>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">1</span>
                            </div>

                            {{-- toolbar --}}
                            <div class="px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div style="{{ $mockInput }}; width:220px">Cari agama...</div>
                                    <div style="{{ $mockInput }}; width:70px; justify-content:space-between">10 <span>▾</span></div>
                                    <span class="ds-btn ds-btn-primary" style="height:36px; padding:8px 14px; font-size:13px">+ Tambah Data</span>
                                    <span class="inline-flex overflow-hidden rounded-lg" style="border:1px solid var(--hairline); background:var(--canvas)">
                                        <span class="px-2.5 py-2 text-sm" style="color:var(--info)">⟳</span>
                                        <span class="px-2.5 py-2 text-sm" style="border-left:1px solid var(--hairline); color:var(--muted)">↩</span>
                                    </span>
                                </div>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">2</span>
                            </div>

                            {{-- tabel --}}
                            <div style="position:relative">
                                <table class="ds-table">
                                    <thead>
                                        <tr><th>ID</th><th>Agama</th><th class="ds-c">Aksi</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([[1, 'ISLAM'], [2, 'KRISTEN'], [3, 'KATOLIK']] as [$mockId, $mockNama])
                                            <tr>
                                                <td class="ds-td-token">{{ $mockId }}</td>
                                                <td class="ds-td-strong">{{ $mockNama }}</td>
                                                <td class="ds-c">
                                                    <span class="inline-flex items-center gap-2">
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg"
                                                            style="border:1px solid var(--hairline); background:var(--canvas); color:var(--body)">✎ Edit</span>
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg"
                                                            style="background:var(--error); color:#fff">🗑 Hapus</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">3</span>
                                <span style="{{ $badge }};position:absolute;top:64px;right:8px;background:var(--info)">4</span>
                            </div>

                            {{-- pagination --}}
                            <div class="flex items-center justify-between px-4 py-2.5" style="position:relative; border-top:1px solid var(--hairline); background:var(--canvas)">
                                <span class="ds-caption" style="color:var(--muted)">Menampilkan 1–3 dari 6 data</span>
                                <span class="inline-flex items-center gap-1.5">
                                    <button type="button" class="ds-page-btn" disabled>‹</button>
                                    <button type="button" class="ds-page-btn ds-page-btn-active">1</button>
                                    <button type="button" class="ds-page-btn">2</button>
                                    <button type="button" class="ds-page-btn">›</button>
                                </span>
                                <span style="{{ $badge }};position:absolute;top:8px;right:8px">5</span>
                            </div>
                        </div>

                        {{-- legenda list --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'x-page-title — judul halaman jadi chip di topbar global (bukan header lokal)'],
                                ['2', 'Toolbar sticky — x-text-input pencarian (debounce 300ms) · x-select-input per-halaman · x-primary-button Tambah · x-toolbar-refresh-reset'],
                                ['3', 'Card tabel ds-table — thead sticky, card flex-fill sampai bawah viewport'],
                                ['4', 'Kolom Aksi — x-action-edit + x-action-delete (Hapus selalu lewat dialog konfirmasi)'],
                                ['5', 'Pagination sticky bottom — nempel di dasar card, bukan ikut scroll'],
                            ] as [$num, $ket])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $num === '4' ? 'background:var(--info)' : '' }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== B. ANATOMI MODAL FORM ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">B · Modal Form (Tambah / Edit)</h2>
                        <div class="ds-card-outline" style="padding:24px; background:var(--surface-soft)">
                            <div class="mx-auto" style="max-width:560px; border-radius:14px; overflow:hidden; border:1px solid var(--hairline); box-shadow:0 18px 40px rgba(0,0,0,.14)">

                                {{-- header modal --}}
                                <div class="px-5 py-4" style="position:relative; background:var(--surface-soft)">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex items-center justify-center" style="width:38px;height:38px;border-radius:12px;background:var(--primary-disabled)">
                                                <span style="width:16px;height:16px;border-radius:4px;background:var(--primary);display:inline-block"></span>
                                            </span>
                                            <span>
                                                <span class="block ds-title-md">Tambah Data Agama</span>
                                                <span class="block ds-caption" style="color:var(--muted)">Lengkapi informasi agama pasien.</span>
                                            </span>
                                        </div>
                                        <span class="flex items-center justify-center" style="width:28px;height:28px;border-radius:8px;border:1px solid var(--hairline);color:var(--muted)">✕</span>
                                    </div>
                                    <span class="inline-flex px-2 py-0.5 mt-2 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Mode: Tambah</span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:44px">1</span>
                                    <span style="{{ $badge }};position:absolute;bottom:8px;left:8px;background:var(--info)">2</span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px;background:var(--muted)">3</span>
                                </div>

                                {{-- body modal --}}
                                <div class="px-4 py-4" style="position:relative; background:var(--canvas); border-top:1px solid var(--hairline)">
                                    <div style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden">
                                        <div class="px-4 py-2 ds-caption-up" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline)">Data Agama</div>
                                        <div class="grid grid-cols-3 gap-3 p-4">
                                            <div>
                                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">ID Agama</span>
                                                <div style="{{ $mockInput }}">7</div>
                                            </div>
                                            <div class="col-span-2">
                                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Nama Agama</span>
                                                <div style="{{ $mockInput }}; border-color:var(--error); color:var(--ink)"></div>
                                                <span class="block mt-1 text-xs" style="color:var(--error)">Nama Agama wajib diisi.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">4</span>
                                    <span style="{{ $badge }};position:absolute;bottom:8px;right:8px;background:var(--error)">5</span>
                                </div>

                                {{-- footer modal --}}
                                <div class="flex items-center justify-between px-5 py-3.5" style="position:relative; background:var(--surface-soft); border-top:1px solid var(--hairline)">
                                    <span class="ds-caption" style="color:var(--muted)">
                                        <kbd class="px-1.5 py-0.5 text-xs font-semibold rounded" style="background:var(--canvas); border:1px solid var(--hairline)">Enter</kbd>
                                        di field terakhir untuk simpan
                                    </span>
                                    <span class="inline-flex gap-2">
                                        <span class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 14px; font-size:13px">Batal</span>
                                        <span class="ds-btn ds-btn-primary" style="height:34px; padding:6px 14px; font-size:13px">Simpan</span>
                                    </span>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">6</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda modal --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header — ikon modul + judul "Tambah/Ubah Data ..." + deskripsi singkat', ''],
                                ['2', 'x-badge Mode — hijau (success) saat Tambah, kuning (warning) saat Edit', 'background:var(--info)'],
                                ['3', 'Close X — x-icon-button gray; menutup lewat tryClose() (konfirmasi bila ada perubahan belum disimpan)', 'background:var(--muted)'],
                                ['4', 'Body — x-border-form mengelompokkan field; latar surface-soft', ''],
                                ['5', 'Error state — border merah + pesan Indonesia di bawah field (x-input-error)', 'background:var(--error)'],
                                ['6', 'Footer sticky — hint keyboard · Batal · SATU tombol Simpan hijau', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== C. ALUR EVENT ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">C · Alur List ↔ Form (event)</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            List dan Form adalah dua komponen terpisah yang <strong>hanya berbicara lewat event</strong>.
                            Bagi UI, artinya: klik apa pun di list tidak pernah menulis data — modal form-lah
                            satu-satunya pintu ke database.
                        </p>
                        <div class="grid items-center grid-cols-1 gap-4 lg:grid-cols-[1fr_auto_1fr]">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2">LIST — tampilan</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Menampilkan tabel + pencarian</li>
                                    <li>Tombol Tambah / Edit / Hapus → <em>hanya mengirim event</em></li>
                                    <li>Refresh otomatis saat menerima <span class="ds-code">saved</span></li>
                                </ul>
                            </div>
                            <div class="text-center">
                                <div class="ds-code mb-2" style="color:var(--primary); white-space:nowrap">── openCreate / openEdit / requestDelete ──▶</div>
                                <div class="ds-code" style="color:var(--info); white-space:nowrap">◀── master.&lt;x&gt;.saved ──</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2">FORM — modal</div>
                                <ul class="ds-body-sm space-y-1" style="list-style:disc; padding-left:18px">
                                    <li>Terbuka saat menerima event open*</li>
                                    <li>Validasi → simpan/hapus ke <strong>database</strong></li>
                                    <li>Toast sukses → tutup → kirim <span class="ds-code">saved</span></li>
                                </ul>
                            </div>
                        </div>

                        {{-- ===== D. LOV DUA KEADAAN ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">D · LOV — dua keadaan</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-3">Keadaan 1 — mode cari</div>
                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Obat (cari dari master obat)</span>
                                <div style="{{ $mockInput }}; color:var(--ink)">amox<span style="opacity:.4">|</span></div>
                                <div class="mt-2 overflow-hidden" style="border:1px solid var(--hairline); border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,.08)">
                                    <div class="px-3 py-2" style="background:var(--surface-card); border-left:3px solid var(--primary)">
                                        <span class="block text-sm font-semibold" style="color:var(--ink)">AMOXICILLIN 500 MG TABLET</span>
                                        <span class="block text-xs" style="color:var(--muted)">ID: 12345 • Harga: Rp 1.500</span>
                                    </div>
                                    <div class="px-3 py-2" style="border-top:1px solid var(--hairline-soft)">
                                        <span class="block text-sm font-semibold" style="color:var(--ink)">AMOXSAN SIRUP 60 ML</span>
                                        <span class="block text-xs" style="color:var(--muted)">ID: 12377 • Harga: Rp 28.000</span>
                                    </div>
                                </div>
                                <p class="ds-caption mt-3" style="color:var(--muted)">Ketik ≥ 2 huruf · navigasi ↓ ↑ · Enter ambil · Esc tutup</p>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-3">Keadaan 2 — mode terpilih</div>
                                <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Obat (cari dari master obat)</span>
                                <div class="flex items-center gap-2">
                                    <div style="{{ $mockInput }}; flex:1; color:var(--ink); background:var(--surface-soft)">AMOXICILLIN 500 MG TABLET</div>
                                    <span class="ds-btn ds-btn-secondary" style="height:36px; padding:8px 14px; font-size:13px; white-space:nowrap">Ubah</span>
                                </div>
                                <p class="ds-caption mt-3" style="color:var(--muted)">
                                    Pilihan terkirim ke form induk (id + nama). Tombol "Ubah" mengosongkan pilihan;
                                    hilang bila form terkunci (readonly).
                                </p>
                            </div>
                        </div>

                        {{-- ===== E. HIERARKI LEVEL 3 ===== --}}
                        <h2 class="ds-title-lg mt-12 mb-3">E · Master hierarkis (Level 3 — contoh: kamar)</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu halaman, dua panel: <strong>induk</strong> (bangsal) di kiri dan
                            <strong>anak</strong> (kamar + bed) di kanan. Panel kanan kosong sampai
                            satu baris bangsal diklik — dari situ kamar terfilter, dan tiap kamar
                            membuka panel detail berisi tarif &amp; daftar bed.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">

                            {{-- topbar --}}
                            <div class="flex items-center gap-3 px-4 py-2.5" style="background:var(--surface-dark)">
                                <span style="width:22px;height:22px;border-radius:6px;background:var(--accent-lime);display:inline-block"></span>
                                <span class="ds-title-sm" style="color:var(--on-dark)">RSI&nbsp;Madinah</span>
                                <span class="px-2.5 py-1 text-xs rounded-full" style="background:var(--surface-dark-elevated); color:var(--on-dark-soft)">
                                    <strong style="color:var(--on-dark)">Master Kamar</strong> — Bangsal, kamar &amp; bed rawat inap
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-[2fr_3fr]" style="background:var(--canvas)">

                                {{-- KIRI: LIST BANGSAL (induk) --}}
                                <div class="lg:border-r" style="position:relative; border-color:var(--hairline)">
                                    <table class="ds-table">
                                        <thead>
                                            <tr><th>Bangsal</th><th class="ds-c">Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ds-td-strong">AN-NISA</td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                            <tr style="background:var(--success-tint)">
                                                <td>
                                                    <span class="inline-flex items-center gap-2">
                                                        <span style="width:5px;height:20px;border-radius:9999px;background:var(--primary);display:inline-block"></span>
                                                        <span class="text-sm font-semibold" style="color:var(--ink)">SHOFA</span>
                                                    </span>
                                                </td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">MARWAH</td>
                                                <td class="ds-c"><span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <span style="{{ $badge }};position:absolute;top:8px;right:8px">1</span>
                                    <span style="{{ $badge }};position:absolute;top:64px;right:8px;background:var(--info)">2</span>
                                </div>

                                {{-- KANAN: KAMAR + DETAIL (anak) --}}
                                <div style="position:relative">

                                    {{-- toolbar kamar --}}
                                    <div class="px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div style="{{ $mockInput }}; width:160px">Cari kamar...</div>
                                            <span class="ds-btn ds-btn-primary" style="height:36px; padding:8px 14px; font-size:13px">+ Tambah Data Kamar Baru</span>
                                        </div>
                                        <span style="{{ $badge }};position:absolute;top:8px;right:8px">3</span>
                                    </div>

                                    {{-- rekap --}}
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 ds-caption" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline); color:var(--muted)">
                                        <span><strong style="color:var(--body)">KAMAR</strong> Total 4 ·
                                            <span style="color:var(--success-deep)">● Aktif 3</span> ·
                                            <span style="color:var(--error)">● Non-Aktif 1</span></span>
                                        <span><strong style="color:var(--body)">TEMPAT TIDUR</strong>
                                            <span style="color:var(--success-deep)">● Aktif 8</span> ·
                                            <span style="color:var(--error)">● Non-Aktif 2</span></span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2">

                                        {{-- tabel kamar terfilter --}}
                                        <div style="position:relative; border:1px solid var(--hairline); border-radius:12px; overflow:hidden">
                                            <table class="ds-table">
                                                <thead>
                                                    <tr><th>Kamar — <span style="color:var(--primary); text-transform:none">SHOFA</span></th><th class="ds-c">Status</th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr style="background:var(--success-tint)">
                                                        <td>
                                                            <span class="flex items-center gap-2">
                                                                <span style="width:5px;height:28px;border-radius:9999px;background:var(--primary);display:inline-block;flex:none"></span>
                                                                <span>
                                                                    <span class="block text-sm font-semibold" style="color:var(--ink)">SHOFA 101</span>
                                                                    <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S101</span> · KELAS 1</span>
                                                                </span>
                                                            </span>
                                                        </td>
                                                        <td class="ds-c">
                                                            <span class="block px-2 py-0.5 mx-auto text-xs font-medium rounded-full w-max" style="background:var(--success-tint); color:var(--success-deep)">Aktif</span>
                                                            <span class="block px-2 py-0.5 mx-auto mt-1 text-xs font-medium rounded-full w-max" style="background:var(--info-tint); color:var(--info-deep)">2 Bed</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <span class="block text-sm font-semibold" style="color:var(--ink)">SHOFA 102</span>
                                                            <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S102</span> · KELAS 2</span>
                                                        </td>
                                                        <td class="ds-c">
                                                            <span class="block px-2 py-0.5 mx-auto text-xs font-medium rounded-full w-max" style="background:var(--success-tint); color:var(--success-deep)">Aktif</span>
                                                            <span class="block px-2 py-0.5 mx-auto mt-1 text-xs font-medium rounded-full w-max" style="background:var(--info-tint); color:var(--info-deep)">3 Bed</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <span style="{{ $badge }};position:absolute;top:8px;right:8px">4</span>
                                        </div>

                                        {{-- panel detail kamar --}}
                                        <div style="position:relative; border:1px solid var(--hairline); border-radius:12px; overflow:hidden">
                                            <div class="flex items-start justify-between gap-2 px-4 py-3" style="border-bottom:1px solid var(--hairline)">
                                                <span>
                                                    <span class="block ds-title-sm">SHOFA 101</span>
                                                    <span class="block text-xs" style="color:var(--muted)"><span style="font-family:var(--mono)">S101</span> · KELAS 1</span>
                                                </span>
                                                <span class="text-xs" style="color:var(--muted)">✎ &nbsp;🗑</span>
                                            </div>
                                            <div class="px-4 py-3" style="border-bottom:1px solid var(--hairline)">
                                                <div class="ds-caption-up mb-2">Tarif Kamar</div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <span class="block mb-0.5 text-xs" style="color:var(--muted)">Kamar</span>
                                                        <div style="{{ $mockInput }}; height:30px; font-size:12px; color:var(--ink)">250.000</div>
                                                    </div>
                                                    <div>
                                                        <span class="block mb-0.5 text-xs" style="color:var(--muted)">Askep</span>
                                                        <div style="{{ $mockInput }}; height:30px; font-size:12px; color:var(--ink)">50.000</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="px-4 py-3">
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="ds-caption-up">Tempat Tidur (2)</span>
                                                    <span class="ds-btn ds-btn-secondary" style="height:28px; padding:4px 10px; font-size:12px">+ Tambah Bed</span>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach (['S101-A', 'S101-B'] as $mockBed)
                                                        <span class="inline-flex items-center gap-2 px-2.5 py-1.5 text-xs rounded-lg" style="border:1px solid var(--hairline); background:var(--surface-soft)">
                                                            <span class="font-bold" style="font-family:var(--mono); color:var(--ink)">{{ $mockBed }}</span>
                                                            <span style="color:var(--muted)">✎ ✕</span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <span style="{{ $badge }};position:absolute;top:8px;right:8px">5</span>
                                            <span style="{{ $badge }};position:absolute;bottom:8px;right:8px;background:var(--info)">6</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- legenda hierarki --}}
                        <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Panel INDUK — list bangsal (komponen master-bangsal); klik baris = memilih konteks', ''],
                                ['2', 'Baris terpilih — highlight hijau + bar kiri; mengirim event bangsal.selected ke panel kanan', 'background:var(--info)'],
                                ['3', 'Toolbar kamar — baru muncul setelah bangsal dipilih; pencarian & Tambah hanya berlaku utk bangsal aktif', ''],
                                ['4', 'List ANAK — kamar terfilter bangsal aktif; klik baris membuka panel detail di sebelahnya', ''],
                                ['5', 'Panel detail kamar — tarif + daftar bed; Edit/Hapus kamar dari sini', ''],
                                ['6', 'Tambah/Edit Bed — kirim openCreateBed(roomId); event saved membawa entity + roomId → refresh presisi', 'background:var(--info)'],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $badge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="ds-body-md mt-8 mb-3" style="max-width:62ch">
                            Di balik layar, ketiganya tetap memakai pola event yang sama dengan bab C —
                            hanya rantainya lebih panjang:
                        </p>
                        <div class="grid items-center grid-cols-1 gap-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr]">
                            <div class="ds-card-outline" style="padding:16px">
                                <div class="ds-caption-up mb-1">Induk</div>
                                <div class="ds-title-sm">List Bangsal</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">/master/kamar — pilih bangsal</p>
                            </div>
                            <div class="ds-code text-center" style="color:var(--primary); white-space:nowrap">── bangsal.selected ──▶</div>
                            <div class="ds-card-outline" style="padding:16px; border-color:var(--primary)">
                                <div class="ds-caption-up mb-1">Anak</div>
                                <div class="ds-title-sm">List Kamar + panel detail</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">kamar terfilter bangsal aktif; panel bed per kamar</p>
                            </div>
                            <div class="ds-code text-center" style="color:var(--primary); white-space:nowrap">── openCreateBed(roomId) ──▶</div>
                            <div class="ds-card-outline" style="padding:16px">
                                <div class="ds-caption-up mb-1">Modal</div>
                                <div class="ds-title-sm">Form Kamar / Bed</div>
                                <p class="ds-caption mt-1" style="color:var(--muted)">saved membawa entity + roomId → refresh presisi</p>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Ingin melihat komponen aslinya hidup (bisa diklik &amp; diketik)? Buka
                                <a href="{{ route('standarisasi-ui') }}" wire:navigate class="hover:underline" style="color:var(--primary)">halaman Standarisasi UI</a>
                                — katalog interaktif seluruh komponen; halaman ini fokus ke <em>peta</em> penempatannya.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 10 VALIDASI ====== --}}
                    <section x-show="section === 'validasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Validasi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua aturan mati: pesan <strong>selalu Bahasa Indonesia</strong>, dan
                            <span class="ds-code">validate()</span> dipanggil <strong>sebelum logika lain</strong>
                            di save() — early-return sebelum validate membuat border merah field wajib
                            tidak pernah muncul.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Form kecil — array inline</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['validasi-inline'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Form besar — method terpisah</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['validasi-method'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rule <span class="ds-code">unique</span> hanya saat create:
                                <span class="ds-code">$this->formMode === 'create' ? 'required|...|unique:tabel,kolom' : 'required|...'</span>
                                — dan field PK di-<span class="ds-code">:disabled</span> saat edit.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 DELETE ====== --}}
                    <section x-show="section === 'delete'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Delete &amp; ORA-02292</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Delete selalu <strong>dua lapis pengaman</strong>. Lapis 1 di UI:
                            konfirmasi lewat <span class="ds-code">x-action-delete</span> (dialog konfirmasi,
                            bukan <span class="ds-code">wire:confirm</span> browser-native). Lapis 2 di server:
                            tangkap <span class="ds-code">ORA-02292</span> (child record found) dan ubah jadi
                            toast yang manusiawi — tanpa ini user melihat error 500.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Handler delete standar</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['delete'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Opsional (dianjurkan utk master bervolume tinggi):</strong> cek eksplisit
                                tabel pemakai sebelum delete supaya pesannya lebih spesifik — contoh
                                <span class="ds-code">master-pasien-actions</span> mengecek
                                rstxn_rjhdrs / ugdhdrs / rihdrs lebih dulu.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 12 PARTIAL ====== --}}
                    <section x-show="section === 'partial'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Aturan</div>
                        <h1 class="ds-display-md mb-4">Ukuran File &amp; Partial</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Batas kewajaran: LIST ≤ ±300 baris, FORM ≤ ±400 baris. Lewat dari itu —
                            atau form punya lebih dari satu section logis — pecah markup jadi
                            <strong>partial per section</strong>. Partial adalah markup murni
                            (tanpa kelas Volt); seluruh state tetap di file induk.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Contoh nyata — master-pasien (10 partial)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pasien-tree'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Penamaan partial: <span class="ds-code">master-&lt;x&gt;-actions-&lt;section&gt;.blade.php</span>,
                                tanpa prefix ⚡ (bukan komponen Livewire). Jangan memindahkan state atau
                                method ke partial — hanya markup.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 13 VARIAN ====== --}}
                    <section x-show="section === 'varian'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Lanjutan</div>
                        <h1 class="ds-display-md mb-4">Varian &amp; Level Kompleksitas</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tidak semua master sama beratnya: ada yang <strong>biasa</strong> (poli, agama),
                            ada yang <strong>expert</strong> (kamar hierarkis, jasa medis dengan paket &amp; tarif).
                            Prinsipnya: <strong>selalu mulai dari Level 1</strong> — naikkan level hanya kalau
                            domainnya memang menuntut, dan teknik tambahannya pun tetap terstandar.
                        </p>

                        {{-- tabel level --}}
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Level</th><th>Ciri</th><th>Teknik tambahan yang dipakai</th><th>Contoh modul</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">1 · Dasar</td>
                                        <td class="ds-body-sm">Satu tabel, CRUD murni</td>
                                        <td class="ds-body-sm">Pola bab 01–11 persis, tanpa tambahan</td>
                                        <td class="ds-td-class">agama · poli · kelas-rawat · stocklocations · signa-catatan</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">2 · Menengah</td>
                                        <td class="ds-body-sm">CRUD + FK / status / query berat</td>
                                        <td class="ds-body-sm">LOV (bab 08) · <span class="ds-code">baseQuery()</span> terpisah · <span class="ds-code">toggleActive</span> · rules/messages sbg method · form bertab + partial</td>
                                        <td class="ds-td-class">obat · obat-kronis · dokter · karyawan · diagnosa · pasien</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">3 · Expert</td>
                                        <td class="ds-body-sm">Hierarki induk-anak / sub-list di dalam form</td>
                                        <td class="ds-body-sm">Verb event spesifik + payload konteks · panel detail · sub-form dgn validasi bertahap · tarif per kelas</td>
                                        <td class="ds-td-class">kamar (bangsal→kamar→bed) · laborat (clab→clabitem) · interaksi-obat · jasa-medis / jasa-dokter</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mb-3">Tiga varian struktur resmi</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Varian</th><th>Kapan dipakai</th><th>Contoh acuan</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-strong">Master-detail hierarkis</td>
                                        <td class="ds-body-sm">Data induk-anak dikelola satu layar. Namespace event bersama + verb spesifik (mis. <span class="ds-code">master.kamar.openCreateBangsal</span>); child list embedded tanpa page-title/frame penuh.</td>
                                        <td class="ds-td-class">master-kamar (bangsal→kamar→bed)<br>master-laborat (clab→clabitem)<br>master-interaksi-obat (hdr→dtl)</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">Form bertab</td>
                                        <td class="ds-body-sm">Field sangat banyak / multi-section. Tab via Alpine <span class="ds-code">activeTab</span> + partial per tab.</td>
                                        <td class="ds-td-class">master-pasien</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-strong">Single-file integrasi</td>
                                        <td class="ds-body-sm">Bukan CRUD murni — sinkronisasi API eksternal. Trait API ikut pola <span class="ds-code">docs/trait-template-api-eksternal.md</span>.</td>
                                        <td class="ds-td-class">setup-jadwal-bpjs<br>registrasi-aplicares-sirs</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Varian ≠ bebas aturan: kontrak penamaan state, validasi Indonesia,
                                guard ORA-02292, dan komponen UI standar tetap berlaku di ketiganya.
                            </span>
                        </div>

                        {{-- deep-dive expert A: hierarki --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Expert A — Hierarki induk-anak (master-kamar)</h2>
                        <p class="ds-body-md mb-2" style="max-width:62ch">
                            Saat satu layar mengelola beberapa entitas bertingkat, verb event generik
                            (<span class="ds-code">openCreate</span>) jadi ambigu — buka form apa?
                            Solusinya: <strong>verb spesifik per entitas</strong> dalam satu namespace,
                            dan event <span class="ds-code">saved</span> membawa payload konteks
                            supaya list tahu bagian mana yang perlu di-refresh.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola hierarki — ⚡master-kamar.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-6 mb-2" style="max-width:62ch">
                            CRUD per entitasnya sendiri <strong>tidak berubah</strong> dari bab 05–11:
                            tiap entitas (bangsal, kamar, bed) punya file actions + modal sendiri.
                            Yang benar-benar baru di Level 3 hanya <strong>tiga hal</strong> berikut —
                            ditunjukkan lewat kode asli modul kamar:
                        </p>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">1 · CRUD entitas anak — konteks induk ikut ke mana-mana</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-bed-actions'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">2 · Delete induk — cek anak dulu, baru jaring ORA-02292</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-delete-guard'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">3 · Refresh presisi — satu listener saved, payload yang menentukan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-kamar-refresh'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rantai lengkapnya: klik "Tambah Bed" di panel detail →
                                <span class="ds-code">openCreateBed(roomId)</span> → modal bed simpan →
                                <span class="ds-code">saved(entity: 'bed', roomId)</span> →
                                list me-refresh <em>hanya</em> panel bed kamar itu. Kode acuan utuh:
                                <span class="ds-code">pages/master/master-kamar/</span> — header file
                                utamanya memuat diagram struktur folder &amp; alur event selengkapnya.
                            </span>
                        </div>

                        {{-- deep-dive expert B: sub-list dalam form --}}
                        <h2 class="ds-title-lg mt-10 mb-2">Expert B — Sub-list di dalam form (master-jasa-medis)</h2>
                        <p class="ds-body-md mb-2" style="max-width:62ch">
                            Form yang menyimpan header + baris detail (paket obat, komponen tarif)
                            memakai <strong>validasi bertahap</strong>: tiap tombol "Tambah" pada sub-form
                            memvalidasi field sub-form itu saja, barisnya masuk array di
                            <span class="ds-code">$form</span>, dan <span class="ds-code">save()</span>
                            memvalidasi form utama lalu menyimpan header + loop detail.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola sub-list — ⚡master-jasa-medis-actions.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['level-jm'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Teknik expert lain yang sudah terstandar</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>Tarif per kelas</strong>: baris tarif per kelas kamar (ACTP/ACTD-CLASSES) dikelola lewat <strong>modal tersendiri</strong> — acuan: modal Tarif V&amp;K di master-dokter, LOV jasa per kelas</li>
                                    <li><strong>toggleActive</strong>: aktif/nonaktif baris tanpa hapus (kolom <span class="ds-code">active_status '1'/'0'</span>) — dokter, kamar, jasa-medis</li>
                                    <li><strong>Panel detail</strong> di list (computed <span class="ds-code">selectedRoom()</span>) utk data anak yang sering dilihat</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Batas yang tidak boleh dilewati</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Level 3 <strong>bukan izin</strong> menaruh <span class="ds-code">validate()</span>/simpan di komponen LIST — panel tarif inline di list master-dokter adalah <strong>backlog perbaikan</strong>, bukan contoh</li>
                                    <li>Verb spesifik tetap berpola <span class="ds-code">openCreate&lt;Entitas&gt;</span> / <span class="ds-code">requestDelete&lt;Entitas&gt;</span> — jangan mengarang verb baru</li>
                                    <li>Kalau ragu modulmu Level berapa: mulai Level 1; kompleksitas ditambah belakangan jauh lebih murah daripada dibongkar</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 14 CHECKLIST ====== --}}
                    <section x-show="section === 'checklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Lanjutan</div>
                        <h1 class="ds-display-md mb-4">Checklist &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sebelum modul master baru di-merge, semua butir ini harus terpenuhi:
                        </p>

                        <div class="ds-card-outline" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Folder + 2 file ⚡ (list & actions), route Route::livewire + ->name(\'master.*\')',
                                    'Kontrak penamaan: searchKeyword, itemsPerPage, rows(), event master.<folder>.* verb standar',
                                    'LIST: page-title → frame flex-fill → toolbar sticky → ds-table → x-action-edit/delete → empty state → pagination sticky',
                                    'LIST tanpa validasi/DB-write — semua mutasi di file -actions',
                                    'FORM: WithRenderVersioningTrait + x-modal + x-dirty-modal-content + header/body/footer standar',
                                    'validate() sebelum logika lain; pesan Indonesia + attributes',
                                    'Delete: x-action-delete + catch ORA-02292',
                                    'x-enter-chain + Enter di field terakhir = simpan; fokus otomatis saat modal buka',
                                    'Toast sukses/gagal via dispatch toast; refresh list via event saved',
                                    'LIST ≤ ±300 baris, FORM ≤ ±400 baris; pecah partial bila lebih',
                                ] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Referensi</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Apa</th><th>Di mana</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Template kanonik</td><td class="ds-td-class">resources/views/pages/master/master-agama/</td></tr>
                                    <tr><td class="ds-td-strong">Dokumen sumber</td><td class="ds-td-class">docs/standar-master-module.md</td></tr>
                                    <tr><td class="ds-td-strong">Token &amp; kelas ds-*</td><td class="ds-td-class">resources/css/app.css (warna: tailwind.config.cjs)</td></tr>
                                    <tr><td class="ds-td-strong">Komponen aksi tabel</td><td class="ds-td-class">resources/views/components/action-{edit,delete}.blade.php</td></tr>
                                    <tr><td class="ds-td-strong">Toolbar refresh/reset</td><td class="ds-td-class">resources/views/components/toolbar-refresh-reset.blade.php</td></tr>
                                    <tr><td class="ds-td-strong">Render versioning</td><td class="ds-td-class">app/Http/Traits/WithRenderVersioning/WithRenderVersioningTrait.php</td></tr>
                                    <tr><td class="ds-td-strong">Frame halaman &amp; empty state</td><td class="ds-td-class">docs/page-frame-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Modal dirty-guard</td><td class="ds-td-class">docs/dirty-modal-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Tombol &amp; UI umum</td><td class="ds-td-class">docs/standar-komponen-tombol.md · docs/standar-ui-komponen.md</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Lanjutan:</strong> sudah khatam modul master? Lanjut ke
                                <a href="{{ route('standarisasi-ui.koding-transaksi') }}" wire:navigate
                                    class="hover:underline font-semibold" style="color:var(--primary)">Tutorial Koding Transaksi</a>
                                — pendaftaran → pelayanan → kasir (RJ/UGD/RI) + EMR, modul dokumen, administrasi.
                            </span>
                        </div>
                    </section>

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
