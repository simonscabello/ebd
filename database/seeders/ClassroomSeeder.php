<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Classroom;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $church = Church::primaryOrCreate();

        Classroom::query()->updateOrCreate(['slug' => 'jovens'], [
            'church_id' => $church->id,
            'name' => 'Jovens',
            'description' => 'Classe de jovens e jovens adultos. Domingo, às 10h, na sala 2.',
            'is_active' => true,
            'position' => 1,
        ]);

        Classroom::query()->updateOrCreate(['slug' => 'adultos'], [
            'church_id' => $church->id,
            'name' => 'Adultos',
            'description' => 'Classe de adultos. Domingo, às 10h, no templo.',
            'is_active' => true,
            'position' => 2,
        ]);
    }
}
