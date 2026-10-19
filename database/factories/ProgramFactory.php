<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Discipulado', 'Pequenos grupos', 'Escola de líderes', 'Estudos temáticos']);

        return [
            'church_id' => fn () => Church::primary()->id ?? Church::factory()->owner(),
            'name' => $name,
            'short_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ];
    }
}
