<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://push.example/'.fake()->unique()->sha1(),
            'public_key' => fake()->sha256(),
            'auth_token' => fake()->sha1(),
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130',
        ];
    }
}
