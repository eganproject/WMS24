<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {Schema::create('stock_api_allowed_ips',function(Blueprint $t){$t->id();$t->string('ip_address',45)->unique();$t->string('label',150)->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});} public function down(): void {Schema::dropIfExists('stock_api_allowed_ips');} };
