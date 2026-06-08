<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_tracks', function (Blueprint $table) {
            $table->boolean('is_top')->default(false)->after('rank');
        });
    }

    public function down(): void
    {
        Schema::table('theme_tracks', function (Blueprint $table) {
            $table->dropColumn('is_top');
        });
    }
};
