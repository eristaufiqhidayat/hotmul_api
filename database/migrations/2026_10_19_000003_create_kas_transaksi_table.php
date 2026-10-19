<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kas grup: sumbangan masuk dan pengeluaran (BRD FR-09).
     * Data lama di tabel `donasi` disalin sebagai pemasukan terverifikasi.
     */
    public function up(): void
    {
        Schema::create('kas_transaksi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('jenis', ['masuk', 'keluar']);
            $table->date('tanggal');
            $table->decimal('nominal', 14, 0);
            $table->string('metode', 20)->nullable();
            $table->string('keterangan')->nullable();
            $table->string('bukti_path')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('donasi_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['group_id', 'tanggal']);
        });

        if (Schema::hasTable('donasi')) {
            $rows = DB::table('donasi')
                ->leftJoin('users', 'donasi.user_id', '=', 'users.id')
                ->select('donasi.id', 'donasi.user_id', 'donasi.tanggal', 'donasi.rp', 'users.group_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('kas_transaksi')->insert([
                    'group_id' => $row->group_id ?: null,
                    'user_id' => $row->user_id,
                    'jenis' => 'masuk',
                    'tanggal' => $row->tanggal,
                    'nominal' => $row->rp,
                    'metode' => 'Manual',
                    'keterangan' => 'Dari menu Donasi lama',
                    'is_verified' => true,
                    'donasi_id' => $row->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_transaksi');
    }
};
