<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\KhotmulRec; // Model untuk menyimpan info file

class KhotmulRecController extends Controller
{
    /**
     * Tampilkan form upload
     */
  public function index(Request $request,$id)
{
    $query = KhotmulRec::query();
    $query->where('khotmul_rec.khotmul_id', $id);
    // Search filter (contoh: cari berdasarkan group_name atau keterangan)
    if ($request->has('search') && $request->search != '') {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('khotmul_rec.id', 'like', '%' . $search . '%')
              ->orWhere('khotmul_rec.anggota_id', 'like', '%' . $search . '%');
        });
    }

    $data = $query->orderBy('khotmul_rec.id', 'desc')->paginate(30);

    return response()->json($data);
}
    public function create()
    {
        return view('upload'); // Blade view untuk form
    }

    /**
     * Proses upload file
     */
    public function store(Request $request)
    {

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // Simpan file ke storage/app/public/files
            $path = $file->store('files', 'public');

            // Simpan informasi file ke database
            $fileRecord = KhotmulRec::create([
                'khotmul_id' => $request->khotmul_id,
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            return response()->json(['message' => 'Data berhasil disimpan']);
        }
        //var_dump($request->headers->all());
        return response()->json(['message' => 'Tidak ada file yang diupload.']);
    }
}
