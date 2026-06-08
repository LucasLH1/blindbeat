<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 6)->unique();
            $table->string('status')->default('waiting');
            $table->unsignedInteger('current_round_number')->nullable();
            $table->unsignedInteger('max_players')->default(8);
            $table->unsignedInteger('round_duration')->default(30);
            $table->unsignedInteger('total_rounds')->default(10);
            $table->unsignedTinyInteger('max_attempts')->nullable()->default(null);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('room_themes', function (Blueprint $table) {
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('theme_id')->constrained()->cascadeOnDelete();
            $table->primary(['room_id', 'theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_themes');
        Schema::dropIfExists('rooms');
    }
};
