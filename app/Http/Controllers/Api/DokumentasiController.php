<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DokumentasiKhotaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Repository dokumentasi khotaman per grup dan periode (BRD FR-08).
 */
class DokumentasiController extends Controller
{
    /** Batas ukuran file: 50 MB (dalam KB untuk aturan `max`). */
    public const MAKS_KB = 51200;

    /**
     * GET /dokumentasi?group_id=&periode=
     */
    public function index(Request $request)
    {
        $groupId = $this->groupId($request);
        $aktif = DB::table('khotmul_periode')->where('status', 'active')->first();

        $query = DokumentasiKhotaman::query()
            ->leftJoin('users', 'dokumentasi_khotaman.user_id', '=', 'users.id')
            ->select('dokumentasi_khotaman.*', 'users.name as uploader');

        if ($groupId) {
            $query->where('dokumentasi_khotaman.group_id', $groupId);
        } elseif ($request->user()->role !== 'admin') {
            $query->where('dokumentasi_khotaman.user_id', $request->user()->id);
        }
        if ($request->filled('periode')) {
            $query->where('dokumentasi_khotaman.period_index', (int) $request->periode);
        }

        $data = $query->orderByDesc('dokumentasi_khotaman.id')
            ->limit(200)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'group_id' => $d->group_id,
                'period_index' => $d->period_index,
                'juz' => $d->juz_number,
                'jenis' => $d->jenis,
                'name' => $d->name,
                'url' => Storage::disk('public')->url($d->path),
                'mime_type' => $d->mime_type,
                'size' => (int) $d->size,
                'tanggal' => $d->tanggal,
                'deskripsi' => $d->deskripsi,
                'user_id' => $d->user_id,
                'uploader' => $d->uploader,
                'created_at' => $d->created_at,
            ]);

        return response()->json([
            'group_id' => $groupId,
            'periode_aktif' => $aktif ? [
                'periode' => $aktif->periode,
                'tanggal_selesai' => $aktif->tanggal_selesai,
            ] : null,
            'maks_mb' => self::MAKS_KB / 1024,
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * POST /dokumentasi (multipart: file, deskripsi, tanggal, juz, periode)
     */
    public function store(Request $request)
    {
        $me = $request->user();

        $request->validate([
            'file' => 'required|file|max:' . self::MAKS_KB
                . '|mimes:jpg,jpeg,png,webp,heic,gif,mp4,mov,3gp,webm,m4a,mp3,aac,wav,ogg',
            'deskripsi' => 'nullable|string|max:1000',
            'tanggal' => 'nullable|date',
            'juz' => 'nullable|integer|min:1|max:30',
            'periode' => 'nullable|integer|min:1',
        ], [
            'file.max' => 'Ukuran file maksimal 50 MB',
            'file.mimes' => 'File harus foto, video, atau audio',
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $jenis = str_starts_with($mime, 'image/') ? 'foto'
            : (str_starts_with($mime, 'audio/') ? 'audio' : 'video');

        // m4a sering terbaca sebagai video/mp4
        if (in_array(strtolower($file->getClientOriginalExtension()), ['m4a', 'mp3', 'aac', 'wav', 'ogg'])) {
            $jenis = 'audio';
        }

        $periode = $request->periode
            ?? DB::table('khotmul_periode')->where('status', 'active')->value('periode');

        $dok = DokumentasiKhotaman::create([
            'group_id' => $me->group_id,
            'period_index' => $periode,
            'juz_number' => $request->juz,
            'user_id' => $me->id,
            'jenis' => $jenis,
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('dokumentasi', 'public'),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'tanggal' => $request->tanggal ?? now()->toDateString(),
            'deskripsi' => $request->deskripsi,
        ]);

        return response()->json([
            'message' => 'Dokumentasi tersimpan',
            'data' => $dok,
            'url' => Storage::disk('public')->url($dok->path),
        ], 201);
    }

    /**
     * DELETE /dokumentasi/{id} → pengunggah atau admin.
     */
    public function destroy(Request $request, $id)
    {
        $me = $request->user();
        $dok = DokumentasiKhotaman::findOrFail($id);

        if ($me->role !== 'admin' && (int) $dok->user_id !== (int) $me->id) {
            return response()->json(['message' => 'Tidak boleh menghapus dokumentasi ini'], 403);
        }

        Storage::disk('public')->delete($dok->path);
        $dok->delete();

        return response()->json(['message' => 'Dokumentasi dihapus']);
    }

    private function groupId(Request $request): ?int
    {
        $me = $request->user();

        if ($me->role === 'admin' && $request->filled('group_id')) {
            return (int) $request->group_id;
        }

        return $me->group_id ? (int) $me->group_id : null;
    }
}
