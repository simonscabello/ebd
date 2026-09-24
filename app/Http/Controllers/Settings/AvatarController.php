<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AvatarController extends Controller
{
    /**
     * Troca a foto de perfil. O app já recorta e reduz a imagem antes de enviar.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ], attributes: ['avatar' => 'foto']);

        /** @var User $user */
        $user = $request->user();
        $previous = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ])->save();

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada.']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida.']);

        return back();
    }
}
