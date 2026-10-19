<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices nas FKs que o Postgres não indexa sozinho e que as consultas usam:
 * os lembretes procuram os aparelhos de cada membro (push_subscriptions.user_id)
 * e a exclusão ou junção de contas varre anotações e chamadas.
 *
 * CONCURRENTLY não trava escrita nas tabelas em produção; por isso fica fora
 * de transação. IF NOT EXISTS deixa rodar de novo sem erro.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** @var array<string, array{0: string, 1: string}> */
    private const INDEXES = [
        'push_subscriptions_user_id_index' => ['push_subscriptions', 'user_id'],
        'student_notes_user_id_index' => ['student_notes', 'user_id'],
        'lesson_notes_lesson_id_index' => ['lesson_notes', 'lesson_id'],
        'attendances_recorded_by_index' => ['attendances', 'recorded_by'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $column]) {
            // Um CONCURRENTLY interrompido deixa o índice como INVALID, e o IF NOT
            // EXISTS o aceitaria calado: nesse caso, apaga e cria de novo.
            $invalid = DB::scalar(
                'SELECT NOT i.indisvalid FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid WHERE c.relname = ?',
                [$name],
            );

            if ($invalid === true) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
            }

            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$table} ({$column})");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }
    }
};
