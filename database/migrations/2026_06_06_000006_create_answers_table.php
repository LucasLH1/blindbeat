<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
