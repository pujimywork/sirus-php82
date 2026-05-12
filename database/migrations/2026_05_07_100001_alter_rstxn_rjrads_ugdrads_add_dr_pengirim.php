<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Dokter pengirim radiologi — disimpan sebagai nama (string) seperti pola
        // dr_radiologi & rstxn_riradiologs.dr_pengirim. Tabel rstxn_riradiologs (RI)
        // sudah punya kolom DR_PENGIRIM dari skema legacy; RJ/UGD belum.
        Schema::table('rstxn_rjrads', function (Blueprint $table) {
            $table->string('dr_pengirim', 1000)->nullable();
        });

        Schema::table('rstxn_ugdrads', function (Blueprint $table) {
            $table->string('dr_pengirim', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rstxn_rjrads', function (Blueprint $table) {
            $table->dropColumn('dr_pengirim');
        });

        Schema::table('rstxn_ugdrads', function (Blueprint $table) {
            $table->dropColumn('dr_pengirim');
        });
    }
};
