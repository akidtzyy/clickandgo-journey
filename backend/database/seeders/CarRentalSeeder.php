<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarRental;

class CarRentalSeeder extends Seeder
{
    public function run(): void
    {
        // Kosongkan tabel dulu agar tidak duplikat ID
        CarRental::truncate();

        $cars = [
            [
                'id' => 1,
                'name' => 'Honda Brio',
                'type' => 'self_drive',
                'price' => 300000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 5,
                'image_url' => '/images/cars/brio.jpg',
                'category' => 'City Car',
                'features' => ["Manual/AT", "AC Dingin", "Audio"],
                'is_available' => true,
            ],
            [
                'id' => 2,
                'name' => 'Daihatsu Xenia',
                'type' => 'self_drive',
                'price' => 350000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 7,
                'image_url' => '/images/cars/xenia.jpg',
                'category' => 'MPV',
                'features' => ["Manual/AT", "AC Double", "7 Seater"],
                'is_available' => true,
            ],
            [
                'id' => 3,
                'name' => 'Mitsubishi Xpander',
                'type' => 'self_drive',
                'price' => 350000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 7,
                'image_url' => '/images/cars/xpander.jpg',
                'category' => 'MPV',
                'features' => ["AT", "AC Double", "Luas"],
                'is_available' => true,
            ],
            [
                'id' => 4,
                'name' => 'Hyundai Stargazer',
                'type' => 'self_drive',
                'price' => 350000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 7,
                'image_url' => '/images/cars/stargazer.jpg',
                'category' => 'MPV',
                'features' => ["AT", "AC Double", "Modern"],
                'is_available' => true,
            ],
            [
                'id' => 5,
                'name' => 'Toyota Reborn',
                'type' => 'self_drive',
                'price' => 500000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 7,
                'image_url' => '/images/cars/reborn.jpg',
                'category' => 'Premium MPV',
                'features' => ["AT", "Captain Seat", "AC Double"],
                'is_available' => true,
            ],
            [
                'id' => 6,
                'name' => 'Toyota Zenix',
                'type' => 'self_drive',
                'price' => 600000,
                'duration_desc' => 'Per Hari (24 Jam)',
                'capacity' => 7,
                'image_url' => '/images/cars/zenix.jpg',
                'category' => 'Premium MPV',
                'features' => ["Hybrid AT", "Captain Seat", "Sunroof"],
                'is_available' => true,
            ],
            [
                'id' => 7,
                'name' => 'Xenia / Xpander / Stargazer',
                'type' => 'with_driver',
                'price' => 650000,
                'duration_desc' => '12 Jam',
                'capacity' => 7,
                'image_url' => '/images/cars/xpander-driver.jpg',
                'category' => 'MPV + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "AC Dingin"],
                'is_available' => true,
            ],
            [
                'id' => 8,
                'name' => 'Toyota Reborn',
                'type' => 'with_driver',
                'price' => 800000,
                'duration_desc' => '12 Jam',
                'capacity' => 7,
                'image_url' => '/images/cars/reborn-driver.jpg',
                'category' => 'Premium MPV + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "Captain Seat"],
                'is_available' => true,
            ],
            [
                'id' => 9,
                'name' => 'Elf Short',
                'type' => 'with_driver',
                'price' => 900000,
                'duration_desc' => '12 Jam',
                'capacity' => 12,
                'image_url' => '/images/cars/elf-short.jpg',
                'category' => 'Mini Bus + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "12 Seat"],
                'is_available' => true,
            ],
            [
                'id' => 10,
                'name' => 'Elf Long',
                'type' => 'with_driver',
                'price' => 1200000,
                'duration_desc' => '12 Jam',
                'capacity' => 18,
                'image_url' => '/images/cars/elf-long.jpg',
                'category' => 'Mini Bus + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "18 Seat"],
                'is_available' => true,
            ],
            [
                'id' => 11,
                'name' => 'Bus 35 Seat',
                'type' => 'with_driver',
                'price' => 1900000,
                'duration_desc' => '12 Jam',
                'capacity' => 35,
                'image_url' => '/images/cars/bus-35.jpg',
                'category' => 'Bus + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "35 Seat"],
                'is_available' => true,
            ],
            [
                'id' => 12,
                'name' => 'Bus 45 Seat',
                'type' => 'with_driver',
                'price' => 2900000,
                'duration_desc' => '12 Jam',
                'capacity' => 45,
                'image_url' => '/images/cars/bus-45.jpg',
                'category' => 'Bus + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "45 Seat"],
                'is_available' => true,
            ],
            [
                'id' => 13,
                'name' => 'Toyota Hiace',
                'type' => 'with_driver',
                'price' => 1200000,
                'duration_desc' => '12 Jam',
                'capacity' => 15,
                'image_url' => '/images/cars/hiace.jpg',
                'category' => 'Van + Driver',
                'features' => ["Driver Pro", "BBM Termasuk", "15 Seat"],
                'is_available' => true,
            ],
        ];

        foreach ($cars as $car) {
            CarRental::create($car);
        }
    }
}