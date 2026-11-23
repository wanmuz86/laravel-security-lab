<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'original_name' => fake()->word().'.pdf',
            'storage_path' => 'documents/'.Str::uuid()->toString(),
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
            'sha256' => hash('sha256', Str::random(32)),
            'status' => 'clean',
            'internal_notes' => 'Internal: '.fake()->sentence(),
            'is_approved' => false,
        ];
    }

    public function quarantined(): static
    {
        return $this->state(fn () => ['status' => 'quarantined']);
    }
}
