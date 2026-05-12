<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Laporan Diagnosa
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Statistik 10 besar diagnosa, tindakan, mortalitas &mdash; bulanan & tahunan
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 py-10">
            <div class="max-w-2xl p-6 mx-auto text-center bg-white border border-dashed border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Halaman dalam pengembangan</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Modul ini akan menampilkan 10 besar diagnosa (ICD-10), 10 besar tindakan,
                    serta laporan mortalitas & morbiditas per periode bulanan / tahunan, dengan filter per poli.
                </p>
            </div>
        </div>
    </div>
</div>
