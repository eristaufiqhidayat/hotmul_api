<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AyahController extends Controller
{
    public function range(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');

        if (!$start || !$end || $start > $end) {
            return response()->json(['error' => 'Invalid start or end'], 400);
        }

        $ayatList = [];

        for ($id = $start; $id <= $end; $id++) {
            try {
                $response = Http::get("https://api.alquran.cloud/v1/ayah/$id/quran-uthmani");

                if ($response->successful()) {
                    $data = $response->json()['data'];

                    $ayatList[] = [
                        'global_id' => $data['number'],
                        'surah_no' => $data['surah']['number'],
                        'surah_name' => $data['surah']['name'],
                        'ayah_no' => $data['numberInSurah'],
                        'text' => $data['text'],
                    ];
                }
            } catch (\Exception $e) {
                return response()->json(['error' => "Failed fetching ayah $id: ".$e->getMessage()], 500);
            }
        }

        return response()->json($ayatList);
    }
}
