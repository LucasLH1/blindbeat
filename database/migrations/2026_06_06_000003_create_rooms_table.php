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
            $table->foreignUuid('playlist_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6)->unique();
            $table->string('status')->default('waiting');
            $table->unsignedInteger('current_round_number')->nullable();
            $table->unsignedInteger('max_players')->default(8);
            $table->unsignedInteger('round_duration')->default(30);
            $table->unsignedInteger('total_rounds')->default(10);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
