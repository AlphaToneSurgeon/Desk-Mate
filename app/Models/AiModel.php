<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'slug',
        'display_name',
        'provider',
        'input_price_per_mtok',
        'output_price_per_mtok',
        'is_active',
    ];

    protected $casts = [
        'input_price_per_mtok' => 'decimal:4',
        'output_price_per_mtok' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'model_id');
    }
}
