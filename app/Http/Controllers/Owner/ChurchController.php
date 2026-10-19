<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ChurchSettingsRequest;
use App\Models\AuditLog;
use App\Models\Church;
use App\Queries\OwnerChurchStats;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel do dono do sistema: as igrejas com os números de uso e as chaves de
 * IA, áudio e texto bíblico de cada uma.
 */
class ChurchController extends Controller
{
    public function index(OwnerChurchStats $stats): Response
    {
        return Inertia::render('owner/churches', [
            'churches' => $stats->all(),
        ]);
    }

    public function update(ChurchSettingsRequest $request, Church $church, AuditLogger $audit): RedirectResponse
    {
        $settings = $request->validated();
        $before = $church->only(array_keys($settings));

        $church->update($settings);

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'source' => 'web',
            'tool' => 'owner.church_settings',
            'subject_type' => $church->getMorphClass(),
            'subject_id' => $church->id,
            'changes' => $audit->diff($before, $church->only(array_keys($settings))),
            'status' => AuditLog::STATUS_OK,
        ]);

        $this->toast("Configurações de {$church->name} salvas.");

        return back();
    }
}
