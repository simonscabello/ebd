{{--
    Tela de abertura: cobre a página enquanto o JavaScript do app carrega e
    some quando o React termina de montar (resources/js/lib/splash.ts).

    Na primeira abertura da sessão ("full") mostra o versículo e segura o
    tempo de uma leitura rápida; tocar dispensa. Nas recargas seguintes
    ("quick") o conteúdo só aparece se o carregamento demorar.
--}}
@php($splash = \App\Support\SplashVerse::pick())

<style>
    .splash {
        position: fixed;
        inset: 0;
        z-index: 100;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2rem;
        padding: max(2rem, env(safe-area-inset-top)) 1.5rem max(2rem, env(safe-area-inset-bottom));
        background: var(--background, #faf7f2);
        color: var(--foreground, #2a2521);
        text-align: center;
        transition: opacity 280ms ease-out;
        -webkit-user-select: none;
        user-select: none;
    }

    .splash[data-state='leaving'] {
        opacity: 0;
        pointer-events: none;
    }

    .splash__main,
    .splash__footer {
        animation: splash-in 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .splash__main {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-top: auto;
    }

    .splash__footer {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.875rem;
        animation-delay: 220ms;
    }

    .splash__icon {
        width: 76px;
        height: 76px;
        border-radius: 22%;
        box-shadow: 0 10px 30px -12px rgb(28 50 44 / 0.45);
    }

    .splash__verse {
        margin: 2.25rem 0 0;
        max-width: 21rem;
        animation: splash-in 620ms cubic-bezier(0.22, 1, 0.36, 1) 120ms both;
    }

    .splash__verse p {
        margin: 0;
        font-family: 'Literata Variable', Georgia, 'Times New Roman', serif;
        font-size: clamp(1.2rem, 5vw, 1.4rem);
        line-height: 1.5;
        text-wrap: balance;
    }

    .splash__verse cite {
        display: block;
        margin-top: 1rem;
        font-style: normal;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--primary, #2f5f53);
    }

    .splash__bar {
        position: relative;
        width: 7rem;
        height: 3px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--border, #e7e1d8);
    }

    .splash__bar::after {
        content: '';
        position: absolute;
        inset: 0;
        width: 40%;
        border-radius: inherit;
        background: var(--primary, #2f5f53);
        animation: splash-bar 1.3s ease-in-out infinite;
    }

    .splash__name {
        font-size: 0.8125rem;
        color: var(--muted-foreground, #6b635a);
    }

    .splash__sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    /* Recarga na mesma sessão: nada aparece se o app montar logo. */
    .splash[data-mode='quick'] .splash__main,
    .splash[data-mode='quick'] .splash__verse,
    .splash[data-mode='quick'] .splash__footer {
        animation-delay: 400ms;
    }

    @keyframes splash-in {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
    }

    @keyframes splash-bar {
        from {
            transform: translateX(-100%);
        }
        to {
            transform: translateX(250%);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .splash,
        .splash__main,
        .splash__verse,
        .splash__footer,
        .splash__bar::after {
            animation: none;
            transition: none;
        }

        .splash[data-mode='quick'] .splash__main,
        .splash[data-mode='quick'] .splash__footer {
            animation: splash-reveal 1ms 400ms both;
        }

        @keyframes splash-reveal {
            from {
                opacity: 0;
            }
        }
    }
</style>

<div id="splash" class="splash" role="status">
    <div class="splash__main">
        <img class="splash__icon" src="/icons/icon.svg" width="76" height="76" alt="">
        <blockquote class="splash__verse">
            <p>{{ $splash['text'] }}</p>
            @if ($splash['reference'])
                <cite>{{ $splash['reference'] }}</cite>
            @endif
        </blockquote>
    </div>
    <div class="splash__footer">
        <span class="splash__bar" aria-hidden="true"></span>
        <span class="splash__name">Escola Bíblica Dominical</span>
        <span class="splash__sr">Carregando…</span>
    </div>
</div>

<script>
    (function () {
        var splash = document.getElementById('splash');
        var seen = false;

        try {
            seen = sessionStorage.getItem('ebd.splash') === '1';
            sessionStorage.setItem('ebd.splash', '1');
        } catch (e) {
            // Sem sessionStorage (modo privado antigo): trata como primeira abertura.
        }

        splash.dataset.mode = seen ? 'quick' : 'full';
        splash.dataset.shownAt = String(performance.now());

        // Tocou antes de o app montar: não segura depois.
        splash.addEventListener('click', function () {
            splash.dataset.skipped = '1';
        });
    })();
</script>
