<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rsmst_snomed_codes', function (Blueprint $table) {
            $table->string('snomed_code', 20);
            $table->primary('snomed_code', 'pk_snomed_codes');
            $table->string('display_en', 500);
            $table->string('display_id', 500)->nullable();
            $table->string('value_set', 50)->default('condition-code');
            $table->timestamp('created_at')->useCurrent();
        });

        // Index untuk pencarian
        Schema::table('rsmst_snomed_codes', function (Blueprint $table) {
            $table->index('value_set', 'idx_snomed_vs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsmst_snomed_codes');
    }
};
