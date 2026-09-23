/**
 * Texto amigável para a distância até a aula.
 */
export function countdownLabel(daysUntil: number | null): string | null {
    if (daysUntil === null) {
        return null;
    }

    if (daysUntil < 0) {
        return 'Aula realizada';
    }

    if (daysUntil === 0) {
        return 'Hoje é dia de EBD!';
    }

    if (daysUntil === 1) {
        return 'Amanhã tem EBD';
    }

    return `Faltam ${daysUntil} dias`;
}
