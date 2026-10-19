<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badal / ambil alih juz anggota yang tidak aktif (BRD FR-06).
     */
    public function up(): void
    {
        Schema::create('badal_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('juz_assignments')->cascadeOnDelete();
            $table->unsignedBigInteger('original_user_id')->nullable();
            $table->foreignId('pengganti_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->index();
            $table->unsignedTinyInteger('juz_number');
            $table->integer('period_index');
            $table->enum('status', ['active', 'done', 'cancelled'])->default('active');
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'status']);
            $table->index(['pengganti_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badal_assignments');
    }
};
