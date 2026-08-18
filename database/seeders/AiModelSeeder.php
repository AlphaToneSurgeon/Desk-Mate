<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AiModel;

class AiModelSeeder extends Seeder
{
    // database/seeders/AiModelSeeder.php

public function run(): void
{
    $models = [
        [
            'slug' => 'anthropic/claude-sonnet-4.5',
            'display_name' => 'Claude Sonnet 4.5',
            'provider' => 'anthropic',
            'input_price_per_mtok' => 3.00,
            'output_price_per_mtok' => 15.00,
            'is_active' => true,
        ],
        [
        'slug' => 'openai/gpt-4o',
        'display_name' => 'GPT-4o',
        'provider' => 'openai',
        'input_price_per_mtok' => 2.50,
        'output_price_per_mtok' => 10.00,
        'is_active' => true,
        ],
        [
        'slug' => 'google/gemini-2.5-flash',
        'display_name' => 'Gemini 2.5 Flash',
        'provider' => 'google',
        'input_price_per_mtok' => 0.30,
        'output_price_per_mtok' => 2.50,
        'is_active' => true,
        ],
        [
        'slug' => 'x-ai/grok-4.5',
        'display_name' => 'Grok 4.5',
        'provider' => 'x-ai',
        'input_price_per_mtok' => 2.00,
        'output_price_per_mtok' => 6.00,
        'is_active' => true,
        ],
    ];

    foreach ($models as $model) {
        AiModel::updateOrCreate(['slug' => $model['slug']], $model);
    }
}
  
}
