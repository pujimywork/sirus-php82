<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // room_uuid = UUID resource Location SATUSEHAT untuk kamar RI.
        // Dipakai sebagai lokasi Encounter Rawat Inap (analog poli_uuid di RJ/UGD).
        // Tiap kamar didaftarkan sebagai Location via LocationTrait::createLocation
        // di master kamar, hasil UUID-nya disimpan di sini.
        if (Schema::hasColumn('rsmst_rooms', 'room_uuid')) {
            return;
        }
        Schema::table('rsmst_rooms', function (Blueprint $table) {
            $table->string('room_uuid', 100)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('rsmst_rooms', 'room_uuid')) {
            return;
        }
        Schema::table('rsmst_rooms', function (Blueprint $table) {
            $table->dropColumn('room_uuid');
        });
    }
};
