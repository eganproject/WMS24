<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void
    {
        // Lane assignment now happens in a later migration after the lanes table exists.
    }

    public function down(): void
    {
        // no-op
    }
};
