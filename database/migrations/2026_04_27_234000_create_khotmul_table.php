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
        Schema::create('khotmul', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('anggota_id')->nullable(); // user_id

            $table->integer('juz');       // 1 - 30
            $table->integer('periode');   // periode ke berapa
            $table->date('tanggal');      // tanggal generate

            $table->string('status')->nullable(); // optional

            $table->timestamps();

            // FK (optional tapi bagus)
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('anggota_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khotmul');
    }
};
