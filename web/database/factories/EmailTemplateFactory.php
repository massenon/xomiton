<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TemplateCategory;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(TemplateCategory::cases()),
            'is_system' => false,
            'subject' => '{{site_name}} : '.fake()->sentence(4),
            'preheader' => fake()->sentence(6),
            'body' => '<p>Bonjour {{user_first_name}},</p><p>'.fake()->paragraph().'</p>',
            'is_active' => true,
            'updated_by' => User::factory(),
        ];
    }

    /** Template système : slug immuable, référencé en dur par l'application (RG-10). */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function inCategory(TemplateCategory $category): static
    {
        return $this->state(fn (array $attributes): array => ['category' => $category]);
    }
}
