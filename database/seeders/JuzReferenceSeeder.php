<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JuzReferenceSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['juz_number' => 1, 'total_ayat' => 148],
            ['juz_number' => 2, 'total_ayat' => 111],
            ['juz_number' => 3, 'total_ayat' => 126],
            ['juz_number' => 4, 'total_ayat' => 131],
            ['juz_number' => 5, 'total_ayat' => 124],
            ['juz_number' => 6, 'total_ayat' => 110],
            ['juz_number' => 7, 'total_ayat' => 149],
            ['juz_number' => 8, 'total_ayat' => 142],
            ['juz_number' => 9, 'total_ayat' => 159],
            ['juz_number' => 10, 'total_ayat' => 127],
            ['juz_number' => 11, 'total_ayat' => 151],
            ['juz_number' => 12, 'total_ayat' => 170],
            ['juz_number' => 13, 'total_ayat' => 154],
            ['juz_number' => 14, 'total_ayat' => 227],
            ['juz_number' => 15, 'total_ayat' => 185],
            ['juz_number' => 16, 'total_ayat' => 269],
            ['juz_number' => 17, 'total_ayat' => 190],
            ['juz_number' => 18, 'total_ayat' => 202],
            ['juz_number' => 19, 'total_ayat' => 339],
            ['juz_number' => 20, 'total_ayat' => 171],
            ['juz_number' => 21, 'total_ayat' => 178],
            ['juz_number' => 22, 'total_ayat' => 169],
            ['juz_number' => 23, 'total_ayat' => 181],
            ['juz_number' => 24, 'total_ayat' => 175],
            ['juz_number' => 25, 'total_ayat' => 246],
            ['juz_number' => 26, 'total_ayat' => 195],
            ['juz_number' => 27, 'total_ayat' => 399],
            ['juz_number' => 28, 'total_ayat' => 137],
            ['juz_number' => 29, 'total_ayat' => 431],
            ['juz_number' => 30, 'total_ayat' => 564],
        ];

        DB::table('juz_references')->insert($data);
    }
}