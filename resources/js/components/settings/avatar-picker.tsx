import { router } from '@inertiajs/react';
import { Camera, ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import { destroy, update } from '@/routes/avatar';

const SIZE = 512;

/**
 * Recorta o centro da imagem em quadrado e reduz para 512px, para enviar um
 * arquivo pequeno (a foto do celular costuma ter vários MB).
 */
async function squareImage(file: File): Promise<File> {
    const bitmap = await createImageBitmap(file);
    const side = Math.min(bitmap.width, bitmap.height);
    const canvas = document.createElement('canvas');
    canvas.width = SIZE;
    canvas.height = SIZE;
    canvas
        .getContext('2d')
        ?.drawImage(
            bitmap,
            (bitmap.width - side) / 2,
            (bitmap.height - side) / 2,
            side,
            side,
            0,
            0,
            SIZE,
            SIZE,
        );
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.85),
    );

    return blob ? new File([blob], 'foto.jpg', { type: 'image/jpeg' }) : file;
}

/**
 * Foto do perfil com o botão de câmera para trocar ou remover.
 */
export function AvatarPicker({
    name,
    src,
}: {
    name: string;
    src: string | null;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [sending, setSending] = useState(false);

    const choose = () => input.current?.click();

    const upload = async (file: File | undefined) => {
        if (!file) return;

        setSending(true);

        let avatar = file;
        try {
            avatar = await squareImage(file);
        } catch {
            // Navegador sem suporte ao formato: envia o original.
        }

        router.post(
            update.url(),
            { avatar },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        errors.avatar ?? 'Não foi possível enviar a foto.',
                    ),
                onFinish: () => {
                    setSending(false);
                    if (input.current) input.current.value = '';
                },
            },
        );
    };

    const badge = (
        <span className="absolute -right-0.5 -bottom-0.5 flex size-8 items-center justify-center rounded-full border-2 border-background bg-primary text-primary-foreground shadow-sm">
            <Camera className="size-4" />
        </span>
    );

    const avatar = (
        <UserAvatar
            name={name}
            src={src}
            className={cn('size-20 text-2xl', sending && 'animate-pulse')}
        />
    );

    return (
        <>
            <input
                ref={input}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(event) => upload(event.target.files?.[0])}
            />
            {src ? (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        className="relative rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Alterar foto"
                        disabled={sending}
                    >
                        {avatar}
                        {badge}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem onSelect={choose}>
                            <ImageUp /> Trocar foto
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() =>
                                router.delete(destroy.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 /> Remover foto
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <button
                    type="button"
                    onClick={choose}
                    disabled={sending}
                    className="relative rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Adicionar foto"
                >
                    {avatar}
                    {badge}
                </button>
            )}
        </>
    );
}
