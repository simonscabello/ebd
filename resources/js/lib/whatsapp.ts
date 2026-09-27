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

export function missYouMessage(name: string, classroom: string): string {
    return `Oi, ${firstName(name)}! Sentimos sua falta na EBD da classe ${classroom}. Está tudo bem com você? Conte com a gente. 🙏`;
}

export function birthdayMessage(name: string): string {
    return `Feliz aniversário, ${firstName(name)}! 🎉 Que Deus abençoe o seu novo ano de vida. Um abraço da sua classe da EBD!`;
}
