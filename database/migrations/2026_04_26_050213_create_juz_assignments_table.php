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
        Schema::create('juz_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('juz_number'); // 1–30

            $table->date('start_date');
            $table->date('end_date');

            $table->integer('period_index'); // periode ke-1,2,3,...

            $table->enum('status', ['active', 'done', 'late'])->default('active');

            $table->timestamps();

            $table->index(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juz_assignments');
    }
};
