<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->enum('provider', ['anthropic', 'openai', 'google', 'xai']);
            $table->decimal('input_price_per_mtok', 10, 4);
            $table->decimal('output_price_per_mtok', 10, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};
