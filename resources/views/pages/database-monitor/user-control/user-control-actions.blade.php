<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithFileUploads, WithRenderVersioningTrait;

    public string $formMode = 'create';
    public bool $isFormLocked = false;
    public ?int $userId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public string $myuser_code = '';
    public string $myuser_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $myuser_sip = '';

    /* ── Profesi klinis tetap (TTD CPPT/SBAR) — utk user multi-role; '' = otomatis ikut role pertama ── */
    public string $myuser_profesi = '';

    /* ── Status aktif: '1' = aktif (boleh login), '0' = nonaktif (diblokir login) ── */
    public string $active_status = '1';

    /* ── EMP ID — diisi via LOV employer ── */
    public ?string $emp_id = null;
    public ?string $emp_name = null; // tampilan saja, tidak disimpan ke users

    public $myuser_ttd_image = null;
    public ?string $existing_ttd_image = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Open create ── */

    #[On('userControl.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'user-control-actions');
        $this->dispatch('focus-myuser-code');
    }

    /* ── Open edit ── */

    #[On('userControl.openEdit')]
    public function openEdit(?int $userId = null): void
    {
        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'ID user tidak valid.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }

        $this->userId = $user->id;
        $this->myuser_code = $user->myuser_code ?? '';
        $this->myuser_name = $user->myuser_name ?? '';
        $this->email = $user->email ?? '';
        $this->myuser_sip = $user->myuser_sip ?? '';
        $this->myuser_profesi = $user->myuser_profesi ?? '';
        $this->active_status = (string) ($user->active_status ?? '1');
        $this->existing_ttd_image = $user->myuser_ttd_image ?? null;
        $this->emp_id = $user->emp_id ? (string) $user->emp_id : null;
        $this->emp_name = null;

        if ($this->emp_id) {
            $emp = DB::table('immst_employers')->select('emp_name')->where('emp_id', $this->emp_id)->first();
            $this->emp_name = $emp?->emp_name ?? null;
        }

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'user-control-actions');
        $this->dispatch('focus-myuser-code');
    }

    /* ── Delete dari grid ── */

    #[On('userControl.requestDelete')]
    public function deleteFromGrid(int $userId): void
    {
        $this->userId = $userId;
        $this->hapus();
    }

    /* ── LOV Employer selected ── */

    #[On('lov.selected.kasir-user-control')]
    public function onEmployerSelected(string $target, ?array $payload): void
    {
        if ($payload === null) {
            $this->emp_id = null;
            $this->emp_name = null;
        } else {
            $this->emp_id = $payload['emp_id'] ?? null;
            $this->emp_name = $payload['emp_name'] ?? null;
        }

        $this->resetErrorBag('emp_id');
    }

    /* ── Validasi ── */

    protected function rules(): array
    {
        $rules = [
            'myuser_code' => 'required|string|max:50|unique:users,myuser_code',
            'myuser_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'myuser_sip' => 'nullable|string|max:50',
            'myuser_profesi' => 'nullable|in:Dokter,Perawat,Apoteker,Gizi',
            'active_status' => 'required|in:0,1',
            'emp_id' => 'nullable|string|max:20',
            'myuser_ttd_image' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ];

        if ($this->formMode === 'create') {
            $rules['password'] = 'required|string|min:6|confirmed';
        } else {
            $rules['myuser_code'] = 'required|string|max:50|unique:users,myuser_code,' . $this->userId;
            $rules['email'] = 'required|email|max:100|unique:users,email,' . $this->userId;
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan.',
            'email' => 'Format email tidak valid.',
            'min' => ':attribute minimal :min karakter.',
            'confirmed' => 'Konfirmasi password tidak cocok.',
            'image' => 'File harus berupa gambar (jpg, png, jpeg).',
            'max' => 'Ukuran file maksimal 1MB.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'myuser_code' => 'Kode User',
            'myuser_name' => 'Nama User',
            'email' => 'Email',
            'password' => 'Password',
            'myuser_sip' => 'SIP',
            'myuser_profesi' => 'Profesi Klinis',
            'emp_id' => 'EMP ID Karyawan',
            'myuser_ttd_image' => 'Gambar TTD',
        ];
    }

    /* ── Simpan ── */

    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                $data = [
                    'myuser_code' => $this->myuser_code,
                    'myuser_name' => $this->myuser_name,
                    'name' => $this->myuser_name,
                    'email' => $this->email,
                    'myuser_sip' => $this->myuser_sip,
                    'myuser_profesi' => $this->myuser_profesi ?: null,
                    'active_status' => $this->active_status,
                    'emp_id' => $this->emp_id ?: null,
                    'updated_at' => DB::raw('SYSDATE'),
                ];

                if (!empty($this->password)) {
                    $data['password'] = Hash::make($this->password);
                }

                if ($this->myuser_ttd_image) {
                    // Apply standar: filename only (dmYHis) + DB simpan filename saja.
                    // EXCEPTION: TTD tetap di disk PUBLIC karena dipakai sebagai <img src> di
                    // banyak PDF template (DomPDF resolve relative ke public/). Kalau dipindah
                    // ke private, semua template cetak (RM/laborat/radiologi/general-consent
                    // /eresep) harus diubah pakai Storage::disk('local')->path() — refactor
                    // berisiko tinggi.
                    $folder = 'UserTtd';
                    $ext = $this->myuser_ttd_image->getClientOriginalExtension();
                    $filename = \Carbon\Carbon::now()->format('dmYHis') . '.' . $ext;
                    $this->myuser_ttd_image->storeAs($folder, $filename, 'public');
                    $data['myuser_ttd_image'] = $filename;

                    // Hapus file lama — backward-compat untuk legacy full-path
                    if ($this->formMode === 'edit' && $this->existing_ttd_image) {
                        $oldPath = str_contains($this->existing_ttd_image, '/')
                            ? $this->existing_ttd_image
                            : $folder . '/' . $this->existing_ttd_image;
                        if (\Storage::disk('public')->exists($oldPath)) {
                            \Storage::disk('public')->delete($oldPath);
                        }
                    }
                } elseif ($this->formMode === 'edit') {
                    unset($data['myuser_ttd_image']);
                }

                if ($this->formMode === 'create') {
                    $data['created_at'] = DB::raw('SYSDATE');
                    $data['email_verified_at'] = null;
                    $data['remember_token'] = null;
                    DB::table('users')->insert($data);
                    $message = 'User berhasil ditambahkan.';
                } else {
                    DB::table('users')->where('id', $this->userId)->update($data);
                    $message = 'User berhasil diperbarui.';
                }

                $this->dispatch('toast', type: 'success', message: $message);
            });

            $this->closeModal();
            $this->dispatch('refresh-after-user-control.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Hapus ── */

    public function hapus(): void
    {
        if (!$this->userId) {
            return;
        }

        try {
            DB::transaction(function () {
                $user = DB::table('users')->where('id', $this->userId)->first();
                if ($user && $user->myuser_ttd_image) {
                    \Storage::disk('public')->delete($user->myuser_ttd_image);
                }
                DB::table('user_kas')->where('user_id', $this->userId)->delete();
                DB::table('users')->where('id', $this->userId)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'User berhasil dihapus.');
            $this->closeModal();
            $this->dispatch('refresh-after-user-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $e->getMessage());
        }
    }

    /* ── Close & reset ── */

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'user-control-actions');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->formMode = 'create';
        $this->userId = null;
        $this->existing_ttd_image = null;
        $this->emp_id = null;
        $this->emp_name = null;
        $this->reset(['myuser_code', 'myuser_name', 'email', 'password', 'password_confirmation', 'myuser_sip', 'myuser_profesi', 'active_status', 'myuser_ttd_image']);
    }
};
?>

<div>
    <x-modal name="user-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $userId ?? 'new']) }}">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" alt="RSI Madinah" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
                                    class="hidden w-6 h-6 dark:block" alt="RSI Madinah" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah User' : 'Tambah User Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Kelola data pengguna dan hak akses sistem.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── BODY ── --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-myuser-code.window="$nextTick(() => setTimeout(() => $refs.inputMyuserCode?.querySelector('input')?.focus(), 150))">
                <div class="max-w-full mx-auto p-1">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                        {{-- ═══ KOLOM KIRI ═══ --}}
                        <div
                            class="p-6 space-y-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                            {{-- Kode User --}}
                            <div x-ref="inputMyuserCode">
                                <x-input-label value="Kode User *" class="mb-1" />
                                <x-text-input wire:model="myuser_code" class="w-full"
                                    placeholder="Contoh: DR001, ADM001, PRW001" :error="$errors->has('myuser_code')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.inputMyuserName?.querySelector('input')?.focus())" />
                                <x-input-error :messages="$errors->get('myuser_code')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                    📌 Jika user adalah <strong>Dokter</strong>, isi sama persis dengan kode dokter di
                                    master dokter.
                                </p>
                            </div>

                            {{-- Nama User --}}
                            <div x-ref="inputMyuserName">
                                <x-input-label value="Nama User *" class="mb-1" />
                                <x-text-input wire:model="myuser_name" class="w-full" placeholder="Nama lengkap"
                                    :error="$errors->has('myuser_name')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.inputEmail?.querySelector('input')?.focus())" />
                                <x-input-error :messages="$errors->get('myuser_name')" class="mt-1" />
                            </div>

                            {{-- Email --}}
                            <div x-ref="inputEmail">
                                <x-input-label value="Email *" class="mb-1" />
                                <x-text-input wire:model="email" type="email" class="w-full"
                                    placeholder="user@rumahsakit.com" :error="$errors->has('email')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.lovEmployer?.querySelector('input')?.focus())" />
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>

                            {{-- LOV Employer (EMP ID) --}}
                            <div x-ref="lovEmployer">
                                <livewire:lov.kasir.lov-kasir target="kasir-user-control" label="Karyawan (EMP ID)"
                                    :initialEmpId="$emp_id"
                                    wire:key="lov-kasir-{{ $userId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('emp_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                    🔑 EMP ID dipakai otomatis saat insert transaksi (kolom <code
                                        class="font-mono">emp_id</code>).
                                    Pilih dari data karyawan aktif di IMMST_EMPLOYERS.
                                </p>
                            </div>

                        </div>

                        {{-- ═══ KOLOM KANAN ═══ --}}
                        <div
                            class="p-6 space-y-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                            {{-- SIP --}}
                            <div x-ref="inputSip">
                                <x-input-label value="SIP (Surat Izin Praktik) — Khusus Dokter" class="mb-1" />
                                <x-text-input wire:model="myuser_sip" class="w-full" placeholder="Nomor SIP dokter"
                                    :error="$errors->has('myuser_sip')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPassword?.querySelector('input')?.focus())" />
                                <x-input-error :messages="$errors->get('myuser_sip')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                    🩺 Kosongkan jika user bukan dokter.
                                </p>
                            </div>

                            {{-- Profesi Klinis — identitas profesi tetap utk CPPT/SBAR --}}
                            <div>
                                <x-input-label value="Profesi Klinis (TTD CPPT/SBAR)" class="mb-1" />
                                <x-select-input wire:model="myuser_profesi" class="w-full"
                                    :error="$errors->has('myuser_profesi')">
                                    <option value="">Otomatis — ikut role pertama</option>
                                    <option value="Dokter">Dokter</option>
                                    <option value="Perawat">Perawat</option>
                                    <option value="Apoteker">Apoteker</option>
                                    <option value="Gizi">Gizi</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('myuser_profesi')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                    ✍️ Untuk user multi-role (mis. Perawat + Dokter/Manager): profesi yang
                                    tercatat saat menulis CPPT/SBAR. Kosongkan jika role-nya cuma satu.
                                </p>
                            </div>

                            {{-- Status Akun — nonaktif = tidak bisa login (mis. dokter yang sudah tidak bekerja) --}}
                            <div>
                                <x-input-label value="Status Akun" class="mb-1.5" />
                                <x-toggle wire:model.live="active_status" trueValue="1" falseValue="0">
                                    {{ $active_status === '1' ? 'Aktif — boleh login' : 'Nonaktif — diblokir login' }}
                                </x-toggle>
                                <p class="mt-1.5 text-xs text-muted dark:text-gray-400">
                                    🔒 Nonaktifkan (bukan hapus) untuk user yang sudah tidak bekerja — datanya tetap
                                    tersimpan tapi tidak bisa masuk ke sistem.
                                </p>
                            </div>

                            {{-- Password --}}
                            <div x-ref="inputPassword">
                                <x-input-label
                                    value="{{ $formMode === 'create' ? 'Password *' : 'Password (kosongkan jika tidak diubah)' }}"
                                    class="mb-1" />
                                <x-text-input wire:model="password" type="password" class="w-full"
                                    placeholder="Minimal 6 karakter" :error="$errors->has('password')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPasswordConfirmation?.querySelector('input')?.focus())" />
                                <x-input-error :messages="$errors->get('password')" class="mt-1" />
                            </div>

                            {{-- Konfirmasi Password --}}
                            <div x-ref="inputPasswordConfirmation">
                                <x-input-label value="Konfirmasi Password" class="mb-1" />
                                <x-text-input wire:model="password_confirmation" type="password" class="w-full"
                                    placeholder="Ulangi password" :error="$errors->has('password_confirmation')"
                                    x-on:keydown.enter.prevent="$nextTick(() => $refs.inputTtd?.focus())" />
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                            </div>

                            {{-- TTD --}}
                            <div x-ref="inputTtd">
                                <x-file-upload
                                    name="myuser_ttd_image"
                                    label="Gambar Tanda Tangan (PDF/JPG, max 5 MB)"
                                    accept="image/jpeg,image/png"
                                    :show-error="false"
                                />
                                @if ($existing_ttd_image)
                                    @php
                                        $existingTtdSrc = str_contains($existing_ttd_image, '/')
                                            ? asset('storage/' . $existing_ttd_image)
                                            : asset('storage/UserTtd/' . $existing_ttd_image);
                                    @endphp
                                    <div class="mt-2">
                                        <img src="{{ $existingTtdSrc }}"
                                            class="h-16 border rounded" alt="TTD existing">
                                        <p class="mt-1 text-xs text-muted">
                                            Gambar saat ini. Upload baru untuk mengganti.
                                        </p>
                                    </div>
                                @endif
                                <x-input-error :messages="$errors->get('myuser_ttd_image')" class="mt-1" />
                            </div>

                            {{-- Card karyawan terpilih --}}
                            @if ($emp_id && $emp_name)
                                <div
                                    class="p-3 border border-emerald-200 rounded-xl bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/10">
                                    <p class="mb-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                        Karyawan terhubung
                                    </p>
                                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                                        {{ $emp_name }}
                                    </p>
                                    <p class="text-xs font-mono text-emerald-600 dark:text-emerald-400">
                                        {{ $emp_id }}
                                    </p>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>

            {{-- ── FOOTER ── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between">
                    <div>
                        @if ($formMode === 'edit' && !$isFormLocked)
                            <x-danger-button wire:click="hapus"
                                wire:confirm="Yakin ingin menghapus user ini? Semua data terkait termasuk akses kas akan dihapus.">
                                Hapus User
                            </x-danger-button>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                                wire:target="save" class="min-w-[100px]">
                                <span wire:loading.remove>{{ $formMode === 'edit' ? 'Perbarui' : 'Simpan' }}</span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
