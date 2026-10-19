<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IA ("Tirar dúvida" e áudio do estudo) e texto bíblico passam a ser por
 * igreja. Igreja nova nasce com tudo desligado: só o dono do sistema liga.
 * A primeira igreja (a que já usa o app) continua com tudo ligado, no limite
 * diário de hoje (EBD_HELPER_DAILY_LIMIT), para nada mudar para quem usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->boolean('helper_enabled')->default(false);
            $table->unsignedSmallInteger('helper_daily_limit')->default(10);
            $table->boolean('audio_enabled')->default(false);
            $table->unsignedSmallInteger('audio_monthly_limit')->default(4);
            $table->boolean('shows_bible_text')->default(false);
        });

        $first = DB::table('churches')->orderBy('id')->value('id');

        if ($first !== null) {
            DB::table('churches')->where('id', $first)->update([
                'helper_enabled' => true,
                'helper_daily_limit' => max(1, (int) config('ebd.helper.daily_limit')),
                'audio_enabled' => true,
                'audio_monthly_limit' => 30,
                'shows_bible_text' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn(['helper_enabled', 'helper_daily_limit', 'audio_enabled', 'audio_monthly_limit', 'shows_bible_text']);
        });
    }
};
