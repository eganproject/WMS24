<?php

use App\Support\InboundScanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'surat_jalan_no')) {
                $table->string('surat_jalan_no', 100)->nullable()->after('ref_no');
            }

            if (!Schema::hasColumn('inbound_transactions', 'surat_jalan_at')) {
                $table->timestamp('surat_jalan_at')->nullable()->after('surat_jalan_no');
            }
        });

        DB::table('inbound_transactions')
            ->where('status', 'approved')
            ->update(['status' => InboundScanStatus::COMPLETED]);

        DB::table('inbound_transactions')
            ->whereNull('status')
            ->orWhere('status', 'pending')
            ->update([
                'status' => InboundScanStatus::PENDING_SCAN,
                'approved_at' => null,
                'approved_by' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('inbound_transactions')
            ->where('status', InboundScanStatus::COMPLETED)
            ->update(['status' => 'approved']);

        DB::table('inbound_transactions')
            ->where('status', InboundScanStatus::PENDING_SCAN)
            ->update(['status' => 'pending']);

        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'surat_jalan_at')) {
                $table->dropColumn('surat_jalan_at');
            }

            if (Schema::hasColumn('inbound_transactions', 'surat_jalan_no')) {
                $table->dropColumn('surat_jalan_no');
            }
        });
    }
};
