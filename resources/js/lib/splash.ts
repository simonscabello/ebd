/**
 * Tela de abertura (resources/views/partials/splash.blade.php).
 *
 * Sai quando o app termina de montar. Na primeira abertura da sessão fica o
 * tempo de ler o versículo (proporcional ao tamanho, com teto); tocar nela
 * dispensa a espera. Nas recargas seguintes sai assim que o app está pronto.
 */

/** Mesmo atraso do CSS antes de o conteúdo aparecer no modo "quick". */
const QUICK_REVEAL_MS = 400;
const HOLD_MIN_MS = 1600;
const HOLD_MAX_MS = 2800;
const HOLD_PER_CHAR_MS = 14;
/** Um pouco mais que a transição de opacidade do CSS. */
const FADE_MS = 320;

let dismissed = false;

export function dismissSplash(): void {
    const splash = document.getElementById('splash');

    if (!splash || dismissed) {
        return;
    }

    dismissed = true;

    const elapsed = performance.now() - Number(splash.dataset.shownAt ?? 0);

    if (splash.dataset.mode !== 'full') {
        // Nada apareceu ainda: sai sem transição.
        if (elapsed < QUICK_REVEAL_MS) {
            splash.remove();
        } else {
            fadeOut(splash);
        }

        return;
    }

    const verse = splash.querySelector('blockquote p')?.textContent ?? '';
    const hold = Math.min(
        HOLD_MAX_MS,
        Math.max(HOLD_MIN_MS, 1000 + verse.length * HOLD_PER_CHAR_MS),
    );
    const remaining = splash.dataset.skipped ? 0 : hold - elapsed;

    if (remaining <= 0) {
        fadeOut(splash);

        return;
    }

    const timer = window.setTimeout(() => fadeOut(splash), remaining);

    splash.addEventListener(
        'click',
        () => {
            window.clearTimeout(timer);
            fadeOut(splash);
        },
        { once: true },
    );
}

function fadeOut(splash: HTMLElement): void {
    splash.dataset.state = 'leaving';
    window.setTimeout(() => splash.remove(), FADE_MS);
}
