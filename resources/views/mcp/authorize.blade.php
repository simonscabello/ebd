@php
    /** @var \Laravel\Passport\Client $client */
    /** @var \App\Models\User $user */
    $allowed = $user->canAccessAdmin();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <script>
        (function () {
            if ('{{ $appearance ?? 'system' }}' === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        html { background-color: oklch(0.982 0.006 85); }
        html.dark { background-color: oklch(0.18 0.008 70); }
    </style>

    <title>Conectar {{ $client->name }} · {{ config('app.name', 'EBD') }}</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @vite(['resources/css/app.css'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
<main class="flex min-h-svh items-center justify-center p-4">
    <div class="w-full max-w-md rounded-xl border border-border bg-card p-6 text-card-foreground">
        <div class="mb-5 flex items-center gap-3">
            <img src="/favicon.svg" alt="" class="size-10 rounded-lg">
            <div>
                <p class="text-sm text-muted-foreground">{{ config('app.name', 'EBD') }}</p>
                <h1 class="text-xl font-semibold leading-tight">Conectar {{ $client->name }}</h1>
            </div>
        </div>

        @if ($allowed)
            <p class="text-sm text-muted-foreground">
                O aplicativo <strong class="font-medium text-foreground">{{ $client->name }}</strong> vai poder usar o EBD em seu nome,
                só nas classes que você gerencia:
            </p>

            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>consultar classes, lições, agenda, alunos e o progresso deles;</li>
                <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>criar e editar lições como rascunho;</li>
                <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>registrar presença e ajustar a agenda dos domingos;</li>
                <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>cadastrar e corrigir alunos.</li>
            </ul>

            <p class="mt-4 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                Publicar lições e apagar dados continuam só com você, pelo app. Tudo o que o aplicativo alterar fica registrado no seu nome.
            </p>
        @else
            <p class="rounded-lg bg-muted p-3 text-sm">
                A conexão com assistentes de IA está disponível só para professores e administradores.
            </p>
        @endif

        <p class="mt-4 text-sm text-muted-foreground">
            Conta: <span class="font-medium text-foreground">{{ $user->name }}</span>@if ($user->email) ({{ $user->email }})@endif
        </p>

        <div class="mt-6 flex gap-3">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="h-10 w-full rounded-lg border border-border bg-background px-4 text-sm font-medium hover:bg-muted">
                    {{ $allowed ? 'Não permitir' : 'Voltar' }}
                </button>
            </form>

            @if ($allowed)
                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="h-10 w-full rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                        Permitir acesso
                    </button>
                </form>
            @endif
        </div>
    </div>
</main>
</body>
</html>
