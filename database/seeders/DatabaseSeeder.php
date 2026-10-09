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
            EngagementSeeder::class,
        ]);

        // O texto bíblico não fica no repositório. Se o JSON estiver na raiz
        // do projeto, importa junto para as leituras já aparecerem com texto.
        $json = base_path('pt_naa.json');

        if (is_file($json)) {
            $this->command->call('bible:import', ['path' => $json, '--force' => true]);
        }
    }
}
