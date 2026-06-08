<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// max_attempts is now included in the main rooms migration — this is a no-op.
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
