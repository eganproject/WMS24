<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void
    {
        // Division has been retired in favor of per-user area assignment.
    }

    public function down(): void
    {
        // no-op
    }
};
