import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

export type GenderOption = { value: string; label: string };

/**
 * Gênero em duas opções lado a lado (rádios nativos, enviados pelo <Form>).
 */
export function GenderField({
    options,
    defaultValue,
    required,
    error,
    className,
}: {
    options: GenderOption[];
    defaultValue?: string | null;
    required?: boolean;
    error?: string;
    className?: string;
}) {
    return (
        <fieldset className={cn('grid gap-2', className)}>
            <legend className="mb-2 text-sm leading-none font-medium">
                Gênero
            </legend>
            <div className="grid grid-cols-2 gap-1 rounded-2xl border bg-card p-1">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-10 cursor-pointer items-center justify-center rounded-xl px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:ring-2 has-focus-visible:ring-ring"
                    >
                        <input
                            type="radio"
                            name="gender"
                            value={option.value}
                            defaultChecked={defaultValue === option.value}
                            required={required}
                            className="sr-only"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
