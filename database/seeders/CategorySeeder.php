<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Dispositivos electrónicos, computadoras y accesorios tecnológicos.',
            ],
            [
                'name' => 'Home',
                'description' => 'Artículos para el hogar, muebles y decoración.',
            ],
            [
                'name' => 'Clothing',
                'description' => 'Ropa y accesorios para hombre, mujer y niños.',
            ],
            [
                'name' => 'Sports',
                'description' => 'Equipamiento deportivo y artículos para actividades recreativas.',
            ],
            [
                'name' => 'Books',
                'description' => 'Libros, revistas y materiales de lectura.',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
