/**
 * Link do WhatsApp com a mensagem pronta. Sem telefone, abre o WhatsApp para
 * escolher o contato.
 */
export function whatsappUrl(phone: string | null | undefined, text: string) {
    const digits = phone?.replace(/\D/g, '') ?? '';

    return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`;
}

function firstName(name: string): string {
    return name.trim().split(/\s+/)[0] ?? name;
}

// "na EBD", "da EBD": os outros programas recebem a frase sem o nome.
export function missYouMessage(
    name: string,
    classroom: string,
    program = 'EBD',
): string {
    const where =
        program === 'EBD'
            ? `na EBD da classe ${classroom}`
            : `nos encontros da classe ${classroom}`;

    return `Oi, ${firstName(name)}! Sentimos sua falta ${where}. Está tudo bem com você? Conte com a gente. 🙏`;
}

export function birthdayMessage(name: string, program = 'EBD'): string {
    const from = program === 'EBD' ? 'da sua classe da EBD' : 'da sua classe';

    return `Feliz aniversário, ${firstName(name)}! 🎉 Que Deus abençoe o seu novo ano de vida. Um abraço ${from}!`;
}
