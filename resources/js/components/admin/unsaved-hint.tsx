/** Aviso discreto ao lado do botão de salvar enquanto há alteração pendente. */
export function UnsavedHint() {
    return (
        <span className="mr-auto text-sm text-muted-foreground sm:mr-0">
            Alterações não salvas
        </span>
    );
}
