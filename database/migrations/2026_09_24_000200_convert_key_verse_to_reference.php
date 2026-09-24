<?php

use App\Support\Bible\Reference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O versículo-chave passa a ser só a referência ("Jo 9.4-5"): o texto vem
     * da Bíblia importada. Lições antigas guardavam a citação com a referência
     * entre parênteses no fim; aqui fica só a referência, quando reconhecida.
     * O que não é reconhecido continua como está (a tela mostra o texto livre).
     */
    public function up(): void
    {
        $rows = DB::table('lessons')->whereNotNull('key_verse')->get(['id', 'key_verse']);

        foreach ($rows as $row) {
            $value = trim((string) $row->key_verse);

            if ($value === '' || Reference::parse($value) !== null) {
                continue;
            }

            if (preg_match('/\(([^()]+)\)\s*\.?\s*$/u', $value, $match) && Reference::parse($match[1]) !== null) {
                DB::table('lessons')->where('id', $row->id)->update(['key_verse' => trim($match[1])]);
            }
        }
    }

    public function down(): void
    {
        // A citação original não é recuperável; a referência continua válida.
    }
};
