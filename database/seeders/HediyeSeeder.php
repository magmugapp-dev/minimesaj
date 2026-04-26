<?php

// namespace Database\Seeders;

// use App\Models\Hediye;
// use Illuminate\Database\Seeder;

// class HediyeSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $hediyeler = [
//             ['kod' => 'gul', 'ad' => 'Gul', 'ikon' => '🌹', 'puan_bedeli' => 5, 'sira' => 10],
//             ['kod' => 'kalp_kutu', 'ad' => 'Kalp Kutu', 'ikon' => '💝', 'puan_bedeli' => 10, 'sira' => 20],
//             ['kod' => 'ayi', 'ad' => 'Ayi', 'ikon' => '🧸', 'puan_bedeli' => 15, 'sira' => 30],
//             ['kod' => 'cikolata', 'ad' => 'Cikolata', 'ikon' => '🍫', 'puan_bedeli' => 8, 'sira' => 40],
//             ['kod' => 'yuzuk', 'ad' => 'Yuzuk', 'ikon' => '💍', 'puan_bedeli' => 50, 'sira' => 50],
//             ['kod' => 'kahve', 'ad' => 'Kahve', 'ikon' => '☕', 'puan_bedeli' => 3, 'sira' => 60],
//             ['kod' => 'buket', 'ad' => 'Buket', 'ikon' => '💐', 'puan_bedeli' => 20, 'sira' => 70],
//             ['kod' => 'yildiz', 'ad' => 'Yildiz', 'ikon' => '⭐', 'puan_bedeli' => 7, 'sira' => 80],
//             ['kod' => 'tac', 'ad' => 'Tac', 'ikon' => '👑', 'puan_bedeli' => 30, 'sira' => 90],
//         ];

//         foreach ($hediyeler as $hediye) {
//             Hediye::query()->updateOrCreate(
//                 ['kod' => $hediye['kod']],
//                 $hediye + ['aktif' => true],
//             );
//         }
//     }
// }
