<?php

namespace Tests\Feature\Admin;

use App\Models\QcResiScan;
use App\Models\QcResiScanItem;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStoreFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_out_report_filters_and_breaks_down_by_store(): void
    {
        $operator = User::factory()->create(['name' => 'Operator Scan']);
        $storeA = Store::create(['name' => 'Toko Alpha']);
        $storeB = Store::create(['name' => 'Toko Beta']);

        $alphaScanned = $this->resi($operator, 'SO-A1', $storeA);
        $this->resi($operator, 'SO-A2', $storeA);
        $betaScanned = $this->resi($operator, 'SO-B1', $storeB);
        $this->resi($operator, 'SO-N1', null);
        $this->resi($operator, 'SO-A3', $storeA, 'canceled');
        $this->scanOut($alphaScanned, $operator);
        $this->scanOut($betaScanned, $operator);

        $this->withoutMiddleware()->get(route('admin.reports.scan-out-reports.index'))
            ->assertOk()
            ->assertSee('Toko Alpha')
            ->assertSee('Rekap per Toko');

        $all = $this->withoutMiddleware()->getJson(route('admin.reports.scan-out-reports.data', [
            'date_from' => '2026-09-15',
        ]))->assertOk();
        $all->assertJsonPath('comparison.import_total', 4)
            ->assertJsonPath('comparison.scanned_total', 2)
            ->assertJsonPath('comparison.missing_total', 2)
            ->assertJsonPath('data.0.total_scan', 2)
            ->assertJsonCount(3, 'comparison.stores');
        $stores = collect($all->json('comparison.stores'))->keyBy('store');
        $this->assertSame(2, $stores['Toko Alpha']['import_total']);
        $this->assertSame(1, $stores['Toko Alpha']['scanned_total']);
        $this->assertSame(1, $stores['Toko Alpha']['canceled_total']);
        $this->assertSame(1, $stores['Tanpa Toko']['missing_total']);

        $this->withoutMiddleware()->getJson(route('admin.reports.scan-out-reports.data', [
            'date_from' => '2026-09-15',
            'store_id' => $storeA->id,
        ]))->assertOk()
            ->assertJsonPath('comparison.import_total', 2)
            ->assertJsonPath('comparison.scanned_total', 1)
            ->assertJsonPath('comparison.canceled_total', 1)
            ->assertJsonPath('comparison.missing_samples.0.no_resi', 'SO-A2')
            ->assertJsonPath('comparison.missing_samples.0.store', 'Toko Alpha')
            ->assertJsonPath('data.0.total_scan', 1)
            ->assertJsonCount(1, 'comparison.stores');

        $this->withoutMiddleware()->getJson(route('admin.reports.scan-out-reports.data', [
            'date_from' => '2026-09-15',
            'store_id' => 'none',
        ]))->assertOk()
            ->assertJsonPath('comparison.import_total', 1)
            ->assertJsonPath('comparison.missing_samples.0.store', 'Tanpa Toko')
            ->assertJsonCount(0, 'data');

        $this->withoutMiddleware()->getJson(route('admin.reports.scan-out-reports.data', [
            'store_id' => 'abc',
        ]))->assertUnprocessable()->assertJsonValidationErrors('store_id');

        $this->withoutMiddleware()->get(route('admin.reports.scan-out-reports.export', [
            'date_from' => '2026-09-15',
            'store_id' => $storeA->id,
        ]))->assertOk()->assertDownload();
    }

    public function test_qc_scan_report_filters_and_breaks_down_by_store(): void
    {
        $operator = User::factory()->create(['name' => 'QC Toko']);
        $storeA = Store::create(['name' => 'Toko Alpha']);
        $storeB = Store::create(['name' => 'Toko Beta']);

        $this->completedQc($operator, $this->resi($operator, 'QC-A1', $storeA), 2);
        $this->completedQc($operator, $this->resi($operator, 'QC-A2', $storeA), 3);
        $this->completedQc($operator, $this->resi($operator, 'QC-B1', $storeB), 4);
        $this->completedQc($operator, $this->resi($operator, 'QC-N1', null), 1);

        $this->withoutMiddleware()->get(route('admin.reports.qc-scan.index'))
            ->assertOk()
            ->assertSee('Toko Beta');

        $all = $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 4)
            ->assertJsonCount(3, 'stores')
            ->assertJsonPath('stores.0.store', 'Toko Alpha')
            ->assertJsonPath('stores.0.total_resi', 2)
            ->assertJsonPath('stores.0.total_qty', 5)
            ->assertJsonPath('stores.0.percentage', 50);
        $this->assertEqualsCanonicalizing(
            ['Toko Alpha', 'Toko Beta', 'Tanpa Toko'],
            collect($all->json('details'))->pluck('store')->unique()->values()->all()
        );

        $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'store_id' => $storeB->id,
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 1)
            ->assertJsonPath('summary.total_qty', 4)
            ->assertJsonPath('period.store_name', 'Toko Beta')
            ->assertJsonPath('details.0.no_resi', 'QC-B1');

        $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'store_id' => 'none',
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 1)
            ->assertJsonPath('details.0.store', 'Tanpa Toko');

        $this->withoutMiddleware()->get(route('admin.reports.qc-scan.export', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'store_id' => $storeA->id,
        ]))->assertOk()->assertDownload();
    }

    private function resi(User $uploader, string $code, ?Store $store, ?string $status = null): Resi
    {
        return Resi::create([
            'id_pesanan' => 'ORDER-'.$code,
            'tanggal_pesanan' => '2026-09-15',
            'tanggal_upload' => '2026-09-15',
            'no_resi' => $code,
            'store_id' => $store?->id,
            'status' => $status ?? 'active',
            'uploader_id' => $uploader->id,
        ]);
    }

    private function scanOut(Resi $resi, User $operator): void
    {
        ShipmentScanOut::create([
            'resi_id' => $resi->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => '2026-09-15',
            'scanned_at' => '2026-09-15 10:00:00',
            'scanned_by' => $operator->id,
        ]);
    }

    private function completedQc(User $operator, Resi $resi, int $qty): void
    {
        $qc = QcResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'resi',
            'scan_code' => $resi->no_resi,
            'status' => 'passed',
            'started_at' => '2026-09-15 09:00:00',
            'completed_at' => '2026-09-15 09:10:00',
            'scanned_by' => $operator->id,
            'completed_by' => $operator->id,
        ]);
        QcResiScanItem::create([
            'qc_resi_scan_id' => $qc->id,
            'sku' => 'SKU-'.$resi->no_resi,
            'expected_qty' => $qty,
            'scanned_qty' => $qty,
        ]);
    }
}
