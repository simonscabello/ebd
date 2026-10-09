<?php

namespace App\Actions\Access;

use App\Models\AccessLink;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gera o link pessoal de acesso de um aluno (revogando o anterior).
 *
 * Regras:
 * - só para alunos da classe; nunca para professores ou administradores;
 * - vale também para quem já tem senha: é assim que o aluno recupera o acesso
 *   (o e-mail de "esqueci minha senha" não está ligado). Ao entrar pelo link,
 *   a senha antiga é apagada e a pessoa cria outra (ver LoginWithAccessLink);
 *   quem gerou o link fica registrado em created_by.
 *
 * Devolve a URL completa. O token fica no fragmento (#), que o navegador não
 * envia ao servidor: não aparece em logs nem no preview do WhatsApp.
 */
class IssueAccessLink
{
    public function handle(User $student, Classroom $classroom, User $issuer): string
    {
        if (! $student->isMemberOf($classroom) || $student->isTeacherOf($classroom)) {
            throw ValidationException::withMessages(['user' => 'O link de acesso é só para alunos desta classe.']);
        }

        if ($student->isAdmin() || $student->canAccessAdmin()) {
            throw ValidationException::withMessages(['user' => 'Professores e administradores entram com e-mail e senha.']);
        }

        $token = Str::random(48);
        $ttl = config('ebd.access_links.ttl_days');

        DB::transaction(function () use ($student, $classroom, $issuer, $token, $ttl) {
            $student->accessLinks()->active()->update(['revoked_at' => now()]);

            $link = new AccessLink;
            $link->user_id = $student->id;
            $link->classroom_id = $classroom->id;
            $link->token_hash = AccessLink::hashToken($token);
            $link->created_by = $issuer->id;
            $link->expires_at = is_numeric($ttl) ? now()->addDays((int) $ttl) : null;
            $link->save();
        });

        return route('access-link.show').'#'.$token;
    }
}
