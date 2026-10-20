<?php

namespace App\Console\Commands;

use App\Models\Khotmulperiode;
use App\Services\KhotmulService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus periode & juz assignment yang dibuat bug cron lama: ribuan
 * periode bertanggal jauh ke depan (sampai tahun 3641) yang membuat
 * menu Badal, Anggota, dll. lambat sampai timeout.
 */
class BersihkanJadwalRusak extends Command
{
    protected $signature = 'hotmul:bersihkan-jadwal-rusak
                            {--force : Benar-benar menghapus (tanpa ini hanya menghitung)}
                            {--batch=5000 : Jumlah baris per hapus}';

    protected $description = 'Hapus periode dan juz assignment bertanggal jauh di masa depan (sisa bug cron lama)';

    public function handle(): int
    {
        // satu periode ke depan masih wajar (periode berikutnya sudah dibuat)
        $batas = Carbon::today()->addDays(KhotmulService::HARI_PER_PERIODE)->toDateString();

        $juz = fn () => DB::table('juz_assignments')
            ->where('start_date', '>', $batas)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('hafalan_logs')
                ->whereColumn('hafalan_logs.assignment_id', 'juz_assignments.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('badal_assignments')
                ->whereColumn('badal_assignments.assignment_id', 'juz_assignments.id'));
        $periode = fn () => Khotmulperiode::where('tanggal', '>', $batas);

        $this->info("Batas tanggal: setelah {$batas}");
        $this->line('Juz assignment yang akan dihapus : ' . number_format($juz()->count()));
        $this->line('Khotmul periode yang akan dihapus: ' . number_format($periode()->count()));

        if (!$this->option('force')) {
            $this->warn('Belum ada yang dihapus. Backup database dulu, lalu jalankan lagi dengan --force.');
            return self::SUCCESS;
        }

        $batch = max(100, (int) $this->option('batch'));
        $total = 0;
        do {
            $ids = $juz()->limit($batch)->pluck('id');
            $total += DB::table('juz_assignments')->whereIn('id', $ids)->delete();
            $this->line('  juz assignment terhapus: ' . number_format($total));
        } while ($ids->count() === $batch);

        $hapusPeriode = $periode()->delete();

        $this->info('Selesai: ' . number_format($total) . ' juz assignment dan '
            . number_format($hapusPeriode) . ' periode dihapus.');

        return self::SUCCESS;
    }
}
