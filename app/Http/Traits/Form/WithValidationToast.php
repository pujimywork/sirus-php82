<?php

namespace App\Http\Traits\Form;

use Illuminate\Validation\ValidationException;

/**
 * Trait WithValidationToast
 *
 * Wrap Livewire `validate()` supaya saat validation gagal, user
 * langsung dapat toast feedback (bukan silent failure). Field-level
 * error tetap di-render via $errors bag (pakai <x-input-error> di view).
 *
 * Pemakaian:
 *   use App\Http\Traits\Form\WithValidationToast;
 *   ...
 *   public function save(): void
 *   {
 *       $this->validateWithToast();  // ← pengganti $this->validate()
 *       ...
 *   }
 */
trait WithValidationToast
{
    /**
     * Validate dengan auto-dispatch toast saat gagal.
     * Re-throw ValidationException supaya Livewire tetap render $errors di view.
     */
    protected function validateWithToast($rules = null, $messages = [], $attributes = []): array
    {
        try {
            return $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->dispatchValidationToast($e);
            throw $e;
        }
    }

    /**
     * Bisa juga dipakai standalone setelah catch manual di SFC.
     */
    protected function dispatchValidationToast(ValidationException $e): void
    {
        $errors = $e->validator->errors()->all();
        if (empty($errors)) {
            $this->dispatch('toast', type: 'error', message: 'Ada field yang belum/salah diisi.');
            return;
        }

        $first = $errors[0];
        $extra = \count($errors) - 1;
        $msg = $extra > 0 ? "{$first} (+{$extra} error lain)" : $first;
        $this->dispatch('toast', type: 'error', message: $msg);
    }
}
