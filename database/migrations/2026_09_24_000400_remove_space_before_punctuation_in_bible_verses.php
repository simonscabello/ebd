<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A NAA importada trazia um espaço antes da pontuação depois das palavras
     * em versalete da edição impressa ("o Senhor , porém", "Eu Sou ."). O
     * `bible:import` já grava sem ele; aqui corrige o texto já importado.
     */
    public function up(): void
    {
        DB::update(
            "update bible_verses set text = regexp_replace(text, ?, ?, 'g') where text ~ ?",
            ['\s+([,;:.!?])', '\1', '\s[,;:.!?]'],
        );
    }

    public function down(): void
    {
        // O espaço era um defeito do arquivo de origem; não há o que restaurar.
    }
};
