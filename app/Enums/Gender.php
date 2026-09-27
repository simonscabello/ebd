<?php

namespace App\Enums;

/**
 * Gênero no cadastro do aluno, usado no relatório da classe.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Feminino',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $gender) => ['value' => $gender->value, 'label' => $gender->label()], self::cases());
    }
}
