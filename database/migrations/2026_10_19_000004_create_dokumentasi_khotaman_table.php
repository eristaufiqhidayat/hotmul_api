<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repository dokumentasi khotaman per grup dan periode (BRD FR-08).
     */
    public function up(): void
    {
        Schema::create('dokumentasi_khotaman', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->integer('period_index')->nullable();
            $table->unsignedTinyInteger('juz_number')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('jenis', ['foto', 'video', 'audio']);
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->date('tanggal')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'period_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumentasi_khotaman');
    }
};
