<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Onde paramos" do domingo, salvo sozinho enquanto o professor digita.
 * Sem toast: o próprio campo mostra "Salvando…" e "Salvo".
 */
class MeetingNoteController extends Controller
{
    public function update(Request $request, ClassMeeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $notes = trim((string) $request->validate(['notes' => ['nullable', 'string', 'max:5000']])['notes']);

        $meeting->update(['notes' => $notes === '' ? null : $notes]);

        return back();
    }
}
