/**
 * Datas Y-m-d vindas do servidor, formatadas em pt-BR. Tudo em UTC: a data já
 * é o dia da igreja, e o fuso do aparelho não pode trocá-la de dia.
 */
function utc(date: string): Date {
    return new Date(`${date.slice(0, 10)}T00:00:00Z`);
}

function format(date: string, options: Intl.DateTimeFormatOptions): string {
    return utc(date).toLocaleDateString('pt-BR', {
        timeZone: 'UTC',
        ...options,
    });
}

/** "27 de set." */
export function dayMonth(date: string): string {
    return format(date, { day: 'numeric', month: 'short' });
}

/** "set." */
export function monthShort(date: string): string {
    return format(date, { month: 'short' });
}

/** "domingo, 27 de setembro" */
export function longDate(date: string): string {
    return format(date, { weekday: 'long', day: 'numeric', month: 'long' });
}

/** "27/09" */
export function shortDate(date: string): string {
    return format(date, { day: '2-digit', month: '2-digit' });
}

/** "setembro de 2026" */
export function monthYear(date: string): string {
    return format(date, { month: 'long', year: 'numeric' });
}

/** Dia do mês e dia da semana curto, para os blocos de data das listas. */
export function dateTile(date: string): { weekday: string; day: number } {
    return {
        weekday: format(date, { weekday: 'short' }).replace('.', ''),
        day: utc(date).getUTCDate(),
    };
}

/** Dias de calendário de $from até $to (negativo se $to vem antes). */
export function daysBetween(from: string, to: string): number {
    return Math.round((utc(to).getTime() - utc(from).getTime()) / 86_400_000);
}

/** "hoje", "ontem", "há 3 dias", "amanhã", "em 5 dias". */
export function relativeDay(date: string, today: string): string {
    const days = daysBetween(today, date);

    return new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' }).format(
        days,
        'day',
    );
}

/** Hoje no aparelho (Y-m-d), para limitar campos de data como o nascimento. */
export function todayOnDevice(): string {
    return new Date().toLocaleDateString('en-CA');
}
