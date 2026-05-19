<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hafalan_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_id')->constrained('juz_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('tanggal');

            $table->integer('ayat_from')->nullable();
            $table->integer('ayat_to')->nullable();

            $table->text('keterangan')->nullable();

            $table->enum('status', ['progress', 'selesai'])->default('progress');

            $table->timestamps();

            $table->index(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hafalan_logs');
    }
};
