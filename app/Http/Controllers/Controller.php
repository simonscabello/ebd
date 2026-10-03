<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Mensagem exibida como toast na próxima página.
     *
     * @param  'success'|'info'|'warning'|'error'  $type
     * @param  array{label: string, url: string}|null  $action  botão no toast (ex.: Desfazer), enviado por POST
     */
    protected function toast(string $message, string $type = 'success', ?array $action = null): void
    {
        Inertia::flash('toast', array_filter([
            'type' => $type,
            'message' => $message,
            'action' => $action,
        ]));
    }
}
