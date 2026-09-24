import { Form, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const hasPassword = usePage().props.auth.user?.has_password ?? true;
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Dialog>
            <DialogTrigger
                className="flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition-colors hover:bg-destructive/5"
                data-test="delete-user-button"
            >
                <Trash2 className="size-5 shrink-0 text-destructive" />
                <span className="min-w-0 flex-1">
                    <span className="block font-medium text-destructive">
                        Excluir conta
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        Remove sua conta e seus dados. Não dá para desfazer.
                    </span>
                </span>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Tem certeza de que deseja excluir sua conta?
                </DialogTitle>
                <DialogDescription>
                    Digite sua senha para confirmar a exclusão definitiva da
                    conta.
                </DialogDescription>

                <Form
                    {...ProfileController.destroy.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    onError={() => passwordInput.current?.focus()}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div
                                className={
                                    hasPassword ? 'grid gap-2' : 'hidden'
                                }
                            >
                                <Label htmlFor="password" className="sr-only">
                                    Senha
                                </Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref={passwordInput}
                                    placeholder="Sua senha"
                                    autoComplete="current-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        onClick={() => resetAndClearErrors()}
                                    >
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test="confirm-delete-user-button"
                                    >
                                        Excluir conta
                                    </button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
