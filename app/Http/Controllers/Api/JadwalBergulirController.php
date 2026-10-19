<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use App\Services\KhotmulService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Jadwal bergulir anggota: juz periode berjalan, riwayat, dan perkiraan
 * juz berikutnya (BRD FR-02; juz bergeser +1 tiap 14 hari, 30 → 1).
 */
class JadwalBergulirController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        $riwayat = JuzAssignment::where('user_id', $me->id)
            ->orderByDesc('period_index')
            ->limit(4)
            ->get()
            ->sortBy('period_index')
            ->values();

        $sekarang = $riwayat->firstWhere('status', 'active') ?? $riwayat->last();

        $hari = [];
        $berikutnya = [];

        if ($sekarang) {
            $mulai = Carbon::parse($sekarang->start_date)->startOfDay();
            $selesai = Carbon::parse($sekarang->end_date)->startOfDay();

            $tanggalLapor = HafalanLog::where('user_id', $me->id)
                ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
                ->pluck('tanggal')
                ->map(fn ($t) => substr((string) $t, 0, 10))
                ->flip();

            for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
                $tgl = $d->toDateString();
                $sisa = (int) $d->diffInDays($selesai, false);
                $hari[] = [
                    'tanggal' => $tgl,
                    'sudah_lapor' => isset($tanggalLapor[$tgl]),
                    'hari_ini' => $d->isToday(),
                    'tenggat' => in_array($sisa, [1, 3]),
                ];
            }

            $juz = $sekarang->juz_number;
            $start = $selesai->copy()->addDay();
            for ($i = 1; $i <= 2; $i++) {
                $juz = $juz % 30 + 1;
                $end = $start->copy()->addDays(KhotmulService::HARI_PER_PERIODE - 1);
                $berikutnya[] = [
                    'period_index' => $sekarang->period_index + $i,
                    'juz' => $juz,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => 'terjadwal',
                ];
                $start = $end->copy()->addDay();
            }
        }

        return response()->json([
            'sekarang' => $sekarang ? $this->format($sekarang) : null,
            'hari' => $hari,
            'riwayat' => $riwayat->map(fn ($a) => $this->format($a))->values(),
            'berikutnya' => $berikutnya,
        ]);
    }

    private function format(JuzAssignment $a): array
    {
        return [
            'assignment_id' => $a->id,
            'period_index' => $a->period_index,
            'juz' => $a->juz_number,
            'start_date' => substr((string) $a->start_date, 0, 10),
            'end_date' => substr((string) $a->end_date, 0, 10),
            'status' => $a->status,
        ];
    }
}
