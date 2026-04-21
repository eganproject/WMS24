<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void
    {
        // Area assignment now happens in a later migration after the areas table exists.
    }

    public function down(): void
    {
        // no-op
    }
};
