<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Jovens', 'Adultos', 'Adolescentes', 'Casais', 'Novos Convertidos', 'Senhoras', 'Homens']);

        return [
            // As classes de um teste ficam na mesma igreja, como em produção.
            'church_id' => fn () => Church::primary()->id ?? Church::factory(),
            // O programa padrão da igreja da turma (a EBD), como em produção.
            'program_id' => fn (array $attributes) => Church::query()->whereKey($attributes['church_id'])->firstOrFail()->defaultProgram()->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
