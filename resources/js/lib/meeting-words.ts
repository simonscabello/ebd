import type { Classroom } from '@/types/ebd';

/**
 * Palavras do encontro de uma classe. Quem se reúne no domingo continua lendo
 * "domingo" e "EBD"; as outras classes, "encontro" e o nome do programa.
 */
export function meetingWords(
    classroom?: Pick<
        Classroom,
        'meeting_weekday' | 'program_short_name'
    > | null,
) {
    const sunday = (classroom?.meeting_weekday ?? 7) === 7;

    return {
        /** "domingo" / "encontro" */
        one: sunday ? 'domingo' : 'encontro',
        /** "domingos" / "encontros" */
        many: sunday ? 'domingos' : 'encontros',
        /** "Domingo" / "Encontro" */
        One: sunday ? 'Domingo' : 'Encontro',
        /** "Domingos" / "Encontros" */
        Many: sunday ? 'Domingos' : 'Encontros',
        /** "EBD", "Disc." */
        program: classroom?.program_short_name ?? 'EBD',
    };
}
