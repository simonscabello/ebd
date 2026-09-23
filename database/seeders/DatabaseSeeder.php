<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de DESENVOLVIMENTO. Cria usuários com senha conhecida,
     * por isso é bloqueado em produção.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Os seeders de desenvolvimento não rodam em produção.');

            return;
        }

        $this->call([
            ClassroomSeeder::class,
            UserSeeder::class,
            LessonContentSeeder::class,
        ]);
    }
}
