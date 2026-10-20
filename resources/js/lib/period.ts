/** Período dos números da classe, como vem do servidor (Period). */
export type StatsPeriod = {
    label: string;
    series_id: number | null;
    /** Ainda é a série anterior: a da próxima lição não começou. */
    previous?: boolean;
};

/**
 * "na série Milagres de Jesus", "na série anterior, Milagres de Jesus" ou
 * "nos últimos 3 meses". Diz de qual série são os números quando a classe já
 * mostra a próxima lição de outra.
 */
export function periodPhrase(period: StatsPeriod): string {
    if (!period.series_id) {
        return `nos ${period.label.toLowerCase()}`;
    }

    return period.previous
        ? `na série anterior, ${period.label}`
        : `na série ${period.label}`;
}
