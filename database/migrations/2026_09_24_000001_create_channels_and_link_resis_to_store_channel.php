<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('channels')) {
            Schema::create('channels', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->timestamps();
            });
        }

        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'store_id')) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->after('kurir_id')
                    ->constrained('stores')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('resis', 'channel_id')) {
                $table->foreignId('channel_id')
                    ->nullable()
                    ->after('store_id')
                    ->constrained('channels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'channel_id')) {
                $table->dropConstrainedForeignId('channel_id');
            }
            if (Schema::hasColumn('resis', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
        });

        Schema::dropIfExists('channels');
    }
};
