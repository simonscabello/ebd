/**
 * Telefone guardado só com dígitos e DDI (ex.: 5527999970035), exibido no
 * formato brasileiro quando for do Brasil.
 */
export function formatPhone(digits: string | null | undefined): string {
    if (!digits) {
        return '';
    }

    const br = /^55(\d{2})(\d{4,5})(\d{4})$/.exec(digits);

    return br ? `+55 (${br[1]}) ${br[2]}-${br[3]}` : `+${digits}`;
}
