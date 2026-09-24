<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->string('api_external_id', 100)
                ->nullable()
                ->after('code')
                ->unique('outbound_transactions_api_external_id_unique');
            $table->char('api_request_hash', 64)
                ->nullable()
                ->after('api_external_id');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->dropUnique('outbound_transactions_api_external_id_unique');
            $table->dropColumn(['api_external_id', 'api_request_hash']);
        });
    }
};
