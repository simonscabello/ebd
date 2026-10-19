/**
 * Texto amigável para a distância até a aula.
 */
export function countdownLabel(
    daysUntil: number | null,
    program = 'EBD',
): string | null {
    if (daysUntil === null) {
        return null;
    }

    if (daysUntil < 0) {
        return 'Aula realizada';
    }

    if (daysUntil === 0) {
        return `Hoje é dia de ${program}!`;
    }

    if (daysUntil === 1) {
        return `Amanhã tem ${program}`;
    }

    return `Faltam ${daysUntil} dias`;
}
