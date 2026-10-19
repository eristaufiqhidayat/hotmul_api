<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badge anggota, mis. "pengganti_aktif" (BRD FR-07).
     */
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 50);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('badal_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
