<?php

namespace Modules\TitanCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TitanCore\Entities\AiPrompt;

class AiPromptFactory extends Factory
{
    protected $model = AiPrompt::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(),
            'content' => $this->faker->paragraph(3),
            'tenant_id' => null,
        ];
    }
}
