<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // PDF hasil dari lab luar — disimpan per detail (1 dtl = 1 order = 1 PDF).
        // Konsep: hasil lab luar tidak di-entry per-item ke labout_result/labout_normal,
        // melainkan upload PDF langsung. Path file disimpan di kolom pdf_path.
        Schema::table('lbtxn_checkupoutdtls', function (Blueprint $table) {
            $table->string('pdf_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lbtxn_checkupoutdtls', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};
