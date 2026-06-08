<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('theme_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('deezer_track_id');
            $table->string('title');
            $table->string('artist');
            $table->string('album')->nullable();
            $table->string('preview_url');
            $table->string('cover_url')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_tracks');
    }
};
