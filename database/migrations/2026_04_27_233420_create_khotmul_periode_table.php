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
        Schema::create('khotmul_periode', function (Blueprint $table) {
            $table->id();
            $table->unique(['group_id', 'periode']);
            $table->unsignedBigInteger('group_id');
            $table->integer('periode');
            $table->date('tanggal'); // tanggal mulai periode
            $table->timestamps();

            // optional FK
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khotmul_periode');
    }
};
