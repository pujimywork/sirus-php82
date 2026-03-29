<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithFileUploads, WithRenderVersioningTrait;

    public string $formMode = 'create'; // create | edit
    public bool $isFormLocked = false;
    public ?int $userId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public string $myuser_code = '';
    public string $myuser_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $user_code = '';
    public string $myuser_sip = '';
    public $myuser_ttd_image = null;
    public ?string $existing_ttd_image = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

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
        $this->user_code = $user->user_code ?? '';
        $this->myuser_sip = $user->myuser_sip ?? '';
        $this->existing_ttd_image = $user->myuser_ttd_image ?? null;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'user-control-actions');
        $this->dispatch('focus-myuser-code');
    }

    // 👇 TAMBAHKAN LISTENER INI UNTUK MENANGANI HAPUS DARI LIST
    #[On('userControl.requestDelete')]
    public function deleteFromGrid(int $userId): void
    {
        $this->userId = $userId;
        $this->hapus();
    }

    protected function rules(): array
    {
        $rules = [
            'myuser_code' => 'required|string|max:50|unique:users,myuser_code',
            'myuser_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'user_code' => 'nullable|string|max:50',
            'myuser_sip' => 'nullable|string|max:50',
            'myuser_ttd_image' => 'nullable|image|max:1024',
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
            'user_code' => 'User Code',
            'myuser_sip' => 'SIP',
            'myuser_ttd_image' => 'Gambar TTD',
        ];
    }

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
                    'user_code' => $this->user_code,
                    'myuser_sip' => $this->myuser_sip,
                    'updated_at' => DB::raw('SYSDATE'),
                ];

                if ($this->formMode === 'create' && !empty($this->password)) {
                    $data['password'] = Hash::make($this->password);
                } elseif ($this->formMode === 'edit' && !empty($this->password)) {
                    $data['password'] = Hash::make($this->password);
                }

                if ($this->myuser_ttd_image) {
                    $fileName = 'ttd_' . $this->myuser_code . '_' . time() . '.' . $this->myuser_ttd_image->getClientOriginalExtension();
                    $path = $this->myuser_ttd_image->storeAs('ttd', $fileName, 'public');
                    $data['myuser_ttd_image'] = $path;
                    if ($this->formMode === 'edit' && $this->existing_ttd_image) {
                        \Storage::disk('public')->delete($this->existing_ttd_image);
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
                DB::table('users')->where('id', $this->userId)->delete();
            });
            $this->dispatch('toast', type: 'success', message: 'User berhasil dihapus.');
            $this->closeModal();
            $this->dispatch('refresh-after-user-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $e->getMessage());
        }
    }

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
        $this->reset(['myuser_code', 'myuser_name', 'email', 'password', 'password_confirmation', 'user_code', 'myuser_sip', 'myuser_ttd_image']);
        $this->existing_ttd_image = null;
    }
};

?>


<div>
    <x-modal name="user-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$formMode]) }}">

            {{-- HEADER dengan pattern --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah User' : 'Tambah User Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Kelola data pengguna dan hak
                                    akses sistem.</p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge
                                :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}</x-badge>
                            @if ($isFormLocked ?? false)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY Scrollable --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-myuser-code.window="$nextTick(() => setTimeout(() => $refs.inputMyuserCode?.querySelector('input')?.focus(), 150))">
                <div class="max-w-full mx-auto">
                    <div class="p-1 space-y-1">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {{-- KOLOM KIRI --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <div x-ref="inputMyuserCode">
                                    <x-input-label value="Kode User *" class="mb-1" />
                                    <x-text-input wire:model="myuser_code" class="w-full"
                                        placeholder="Contoh: DR001 (samakan dengan kode dokter di master dokter) atau kode unik bebas untuk non-dokter"
                                        :error="$errors->has('myuser_code')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputMyuserName?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('myuser_code')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        📌 <strong>Panduan:</strong>
                                        Jika user ini akan diberi role <strong>Dokter</strong>, isi kode yang
                                        <strong>sama persis</strong> dengan kode dokter di master dokter.
                                        Untuk role lain (Perawat, Admin, TU, dll.), cukup gunakan kode unik internal
                                        (contoh: PRW001, ADM001).
                                    </p>
                                </div>

                                <div x-ref="inputMyuserName">
                                    <x-input-label value="Nama User *" class="mb-1" />
                                    <x-text-input wire:model="myuser_name" class="w-full" placeholder="Nama lengkap"
                                        :error="$errors->has('myuser_name')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputEmail?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('myuser_name')" class="mt-1" />
                                </div>

                                <div x-ref="inputEmail">
                                    <x-input-label value="Email *" class="mb-1" />
                                    <x-text-input wire:model="email" type="email" class="w-full"
                                        placeholder="user@rumahsakit.com" :error="$errors->has('email')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputUserCode?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                                </div>

                                <div x-ref="inputUserCode">
                                    <x-input-label value="User Code (Kas Pembayaran)" class="mb-1" />
                                    <x-text-input wire:model="user_code" class="w-full"
                                        placeholder="Kode unik 6 digit untuk akses role Kasir, contoh: CSH001"
                                        :error="$errors->has('user_code')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputSip?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('user_code')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        💰 <strong>Fungsi:</strong> Digunakan untuk membuka dan mengidentifikasi role
                                        <strong>Kas Pembayaran</strong>.
                                        Isikan kode unik (bisa 6 karakter) yang akan disinkronkan dengan modul kasir.
                                    </p>
                                </div>
                            </div>

                            {{-- KOLOM KANAN --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <div x-ref="inputSip">
                                    <x-input-label value="SIP (Surat Izin Praktik) - Khusus Dokter" class="mb-1" />
                                    <x-text-input wire:model="myuser_sip" class="w-full"
                                        placeholder="Nomor SIP dokter (wajib jika role = Dokter)" :error="$errors->has('myuser_sip')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPassword?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('myuser_sip')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        🩺 <strong>SIP hanya untuk user dengan role Dokter.</strong>
                                        Jika user bukan dokter, biarkan kosong. Nomor SIP harus sesuai dengan data di
                                        master dokter.
                                    </p>
                                </div>
                                <div x-ref="inputPassword">
                                    <x-input-label
                                        value="{{ $formMode === 'create' ? 'Password *' : 'Password (kosongkan jika tidak diubah)' }}"
                                        class="mb-1" />
                                    <x-text-input wire:model="password" type="password" class="w-full"
                                        placeholder="Minimal 6 karakter" :error="$errors->has('password')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPasswordConfirmation?.querySelector('input')?.focus())" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                                </div>

                                <div x-ref="inputPasswordConfirmation">
                                    <x-input-label value="Konfirmasi Password" class="mb-1" />
                                    <x-text-input wire:model="password_confirmation" type="password" class="w-full"
                                        placeholder="Ulangi password" :error="$errors->has('password_confirmation')"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputTtd?.focus())" />
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                                </div>

                                <div x-ref="inputTtd">
                                    <x-input-label value="Gambar Tanda Tangan" class="mb-1" />
                                    <input type="file" wire:model="myuser_ttd_image"
                                        class="w-full text-sm border-gray-300 rounded-lg" accept="image/*" />
                                    @if ($existing_ttd_image)
                                        <div class="mt-2">
                                            <img src="{{ asset('storage/' . $existing_ttd_image) }}"
                                                class="h-16 border rounded" alt="TTD existing">
                                            <p class="text-xs text-gray-500 mt-1">Gambar saat ini. Upload baru untuk
                                                mengganti.</p>
                                        </div>
                                    @endif
                                    <x-input-error :messages="$errors->get('myuser_ttd_image')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER sticky --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between">
                    <div>
                        @if ($formMode === 'edit' && !($isFormLocked ?? false))
                            <x-danger-button wire:click="hapus"
                                wire:confirm="Yakin ingin menghapus user ini? Semua data terkait akan hilang.">Hapus
                                User</x-danger-button>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        @if (!($isFormLocked ?? false))
                            <x-primary-button x-ref="btnSimpan" wire:click.prevent="save"
                                wire:loading.attr="disabled" wire:target="save" class="min-w-[100px]">
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
