<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_item_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->unsignedBigInteger('reference_id');
            $table->enum('reference_type', ['stock_in_order_items', 'transfer_request_items']);
            $table->decimal('quantity_shipped', 20, 2)->default(0);
            $table->decimal('koli_shipped', 20, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // If an old table exists in DB (in case of prior runs), migrate its data
        if (Schema::hasTable('shipment_transfer_details')) {
            $rows = DB::table('shipment_transfer_details')->get();
            foreach ($rows as $row) {
                DB::table('shipment_item_details')->insert([
                    'shipment_id' => $row->shipment_id,
                    'item_id' => $row->item_id,
                    'reference_id' => $row->transfer_request_item_id,
                    'reference_type' => 'transfer_request_items',
                    'quantity_shipped' => $row->quantity_shipped,
                    'koli_shipped' => $row->koli_shipped,
                    'description' => $row->description ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::dropIfExists('shipment_transfer_details');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_item_details');
    }
};

