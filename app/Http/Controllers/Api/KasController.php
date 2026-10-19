<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasTransaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kas grup: sumbangan, pengeluaran, saldo, verifikasi, ekspor (BRD FR-09).
 */
class KasController extends Controller
{
    /**
     * GET /kas?group_id=&periode= → ringkasan saldo + riwayat transaksi.
     */
    public function index(Request $request)
    {
        $groupId = $this->groupId($request);
        [$mulai, $selesai] = $this->rentangPeriode($request->periode);

        $semua = $this->scope($request, $groupId);

        $saldo = (clone $semua)->where('is_verified', true)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE -nominal END), 0) as saldo")
            ->value('saldo');

        $periodeQuery = clone $semua;
        if ($mulai) {
            $periodeQuery->whereBetween('tanggal', [$mulai, $selesai]);
        }

        $masuk = (clone $periodeQuery)->where('is_verified', true)->where('jenis', 'masuk')->sum('nominal');
        $keluar = (clone $periodeQuery)->where('is_verified', true)->where('jenis', 'keluar')->sum('nominal');
        $menunggu = (clone $semua)->where('is_verified', false)->count();

        $data = $this->withNama($periodeQuery)
            ->orderByDesc('kas_transaksi.tanggal')
            ->orderByDesc('kas_transaksi.id')
            ->limit(300)
            ->get()
            ->map(fn ($t) => $this->format($t));

        return response()->json([
            'group_id' => $groupId,
            'group_name' => $groupId ? DB::table('groups')->where('id', $groupId)->value('name') : null,
            'periode' => $request->periode ? (int) $request->periode : null,
            'saldo' => (int) $saldo,
            'masuk' => (int) $masuk,
            'keluar' => (int) $keluar,
            'menunggu_verifikasi' => $menunggu,
            'data' => $data,
        ]);
    }

    /**
     * POST /kas → anggota menambah sumbangan (menunggu verifikasi);
     * admin bisa mencatat pemasukan atau pengeluaran (langsung terverifikasi).
     */
    public function store(Request $request)
    {
        $me = $request->user();
        $isAdmin = $me->role === 'admin';

        $data = $request->validate([
            'jenis' => 'nullable|in:masuk,keluar',
            'tanggal' => 'required|date',
            'nominal' => 'required|numeric|min:1',
            'metode' => 'nullable|string|max:20',
            'keterangan' => 'nullable|string|max:255',
            'group_id' => 'nullable|integer',
            'bukti' => 'nullable|image|max:5120',
        ]);

        $jenis = $data['jenis'] ?? 'masuk';
        if ($jenis === 'keluar' && !$isAdmin) {
            return response()->json(['message' => 'Pengeluaran hanya bisa dicatat admin'], 403);
        }

        $kas = new KasTransaksi([
            'group_id' => $isAdmin && !empty($data['group_id']) ? $data['group_id'] : $me->group_id,
            'user_id' => $me->id,
            'jenis' => $jenis,
            'tanggal' => $data['tanggal'],
            'nominal' => $data['nominal'],
            'metode' => $data['metode'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'is_verified' => $isAdmin,
            'verified_by' => $isAdmin ? $me->id : null,
            'verified_at' => $isAdmin ? now() : null,
        ]);

        if ($request->hasFile('bukti')) {
            $kas->bukti_path = $request->file('bukti')->store('kas', 'public');
        }

        $kas->save();

        return response()->json([
            'message' => $isAdmin ? 'Transaksi tersimpan' : 'Sumbangan tersimpan, menunggu verifikasi admin',
            'data' => $kas,
        ], 201);
    }

    /**
     * POST /kas/{id}/verifikasi (admin)
     */
    public function verifikasi(Request $request, $id)
    {
        $kas = KasTransaksi::findOrFail($id);
        $kas->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return response()->json(['message' => 'Sumbangan diverifikasi', 'data' => $kas]);
    }

    /**
     * DELETE /kas/{id} → admin, atau pemilik selama belum diverifikasi.
     */
    public function destroy(Request $request, $id)
    {
        $me = $request->user();
        $kas = KasTransaksi::findOrFail($id);

        $bolehHapus = $me->role === 'admin' || ((int) $kas->user_id === (int) $me->id && !$kas->is_verified);
        if (!$bolehHapus) {
            return response()->json(['message' => 'Tidak boleh menghapus transaksi ini'], 403);
        }

        if ($kas->bukti_path) {
            Storage::disk('public')->delete($kas->bukti_path);
        }
        $kas->delete();

        return response()->json(['message' => 'Transaksi dihapus']);
    }

    /**
     * GET /kas/export?group_id=&periode= → CSV (dibuka di Excel/Sheets).
     */
    public function export(Request $request)
    {
        $groupId = $this->groupId($request);
        [$mulai, $selesai] = $this->rentangPeriode($request->periode);

        $query = $this->scope($request, $groupId);
        if ($mulai) {
            $query->whereBetween('tanggal', [$mulai, $selesai]);
        }

        $rows = $this->withNama($query)
            ->orderBy('kas_transaksi.tanggal')
            ->orderBy('kas_transaksi.id')
            ->get();

        $nama = 'kas-grup' . ($groupId ? "-{$groupId}" : '') . ($request->periode ? "-periode-{$request->periode}" : '') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, ['Tanggal', 'Nama', 'Jenis', 'Metode', 'Keterangan', 'Masuk', 'Keluar', 'Status'], ';');

            $saldo = 0;
            foreach ($rows as $t) {
                if ($t->is_verified) {
                    $saldo += $t->jenis === 'masuk' ? $t->nominal : -$t->nominal;
                }
                fputcsv($out, [
                    $t->tanggal,
                    $t->name,
                    $t->jenis,
                    $t->metode,
                    $t->keterangan,
                    $t->jenis === 'masuk' ? (int) $t->nominal : '',
                    $t->jenis === 'keluar' ? (int) $t->nominal : '',
                    $t->is_verified ? 'Terverifikasi' : 'Menunggu verifikasi',
                ], ';');
            }

            fputcsv($out, ['', '', '', '', 'Saldo (terverifikasi)', $saldo, '', ''], ';');
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function groupId(Request $request): ?int
    {
        $me = $request->user();

        if ($me->role === 'admin' && $request->filled('group_id')) {
            return (int) $request->group_id;
        }

        return $me->group_id ? (int) $me->group_id : null;
    }

    /**
     * Transaksi yang boleh dilihat: satu grup; admin tanpa grup melihat
     * semua; anggota tanpa grup hanya melihat transaksinya sendiri.
     */
    private function scope(Request $request, ?int $groupId)
    {
        $query = KasTransaksi::query();

        if ($groupId) {
            return $query->where('kas_transaksi.group_id', $groupId);
        }

        if ($request->user()->role !== 'admin') {
            $query->where('kas_transaksi.user_id', $request->user()->id);
        }

        return $query;
    }

    private function withNama($query)
    {
        return $query->leftJoin('users', 'kas_transaksi.user_id', '=', 'users.id')
            ->select('kas_transaksi.*', 'users.name');
    }

    private function rentangPeriode($periode): array
    {
        if (!$periode) {
            return [null, null];
        }

        $p = DB::table('khotmul_periode')->where('periode', $periode)->first();

        return $p && $p->tanggal_mulai
            ? [substr($p->tanggal_mulai, 0, 10), substr($p->tanggal_selesai ?? $p->tanggal_mulai, 0, 10)]
            : [null, null];
    }

    private function format($t): array
    {
        return [
            'id' => $t->id,
            'user_id' => $t->user_id,
            'name' => $t->name,
            'jenis' => $t->jenis,
            'tanggal' => substr((string) $t->tanggal, 0, 10),
            'nominal' => (int) $t->nominal,
            'metode' => $t->metode,
            'keterangan' => $t->keterangan,
            'bukti_url' => $t->bukti_path ? Storage::disk('public')->url($t->bukti_path) : null,
            'is_verified' => (bool) $t->is_verified,
        ];
    }
}
