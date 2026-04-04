<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Laporan\LaporanStokController;
use App\Http\Controllers\Admin\Laporan\LaporanController;
use App\Http\Controllers\Admin\ManajemenStok\InventoryController;
use App\Http\Controllers\Admin\ManajemenStok\KartuStokController;
use App\Http\Controllers\Admin\ManajemenStok\WarehouseStokController;
use App\Http\Controllers\Admin\ManajemenStok\StockOpnameController;
use App\Http\Controllers\Admin\ManajemenStok\AdjustmentController;
use App\Http\Controllers\Admin\Masterdata\ItemCategoryController;
use App\Http\Controllers\Admin\Masterdata\ItemController;
use App\Http\Controllers\Admin\Masterdata\JabatanController;
use App\Http\Controllers\Admin\Masterdata\MenuController;
use App\Http\Controllers\Admin\Masterdata\PermissionController;
use App\Http\Controllers\Admin\Masterdata\UomController;
use App\Http\Controllers\Admin\Masterdata\UserController;
use App\Http\Controllers\Admin\Masterdata\WarehouseController;
use App\Http\Controllers\Admin\Masterdata\AssemblyRecipeController;
use App\Http\Controllers\Admin\StokKeluar\PengeluaranBarangController;
use App\Http\Controllers\Admin\StokKeluar\PermintaanBarangController;
use App\Http\Controllers\Admin\StokMasuk\PengadaanController;
use App\Http\Controllers\Admin\StokMasuk\PenerimaanBarangController;
use App\Http\Controllers\Admin\StokMasuk\RequestTransferController;
use App\Http\Controllers\Admin\RiwayatPengirimanController;

use App\Http\Controllers\Admin\UserActivityController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'permission'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/aktivitas-user', [UserActivityController::class, 'index'])->name('admin.user-activities.index');

    Route::prefix('admin/masterdata')->name('admin.masterdata.')->group(function () {
        Route::resource('jabatans', JabatanController::class);
        Route::resource('users', UserController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('menus', MenuController::class);
        Route::resource('itemcategories', ItemCategoryController::class);
        Route::resource('uoms', UomController::class);
        Route::resource('items', ItemController::class);
        Route::resource('assemblyrecipes', AssemblyRecipeController::class)->parameters([
            'assemblyrecipes' => 'assemblyrecipe'
        ]);
        Route::post('items/check-sku', [ItemController::class, 'checkSkuUniqueness'])->name('items.checkSkuUniqueness');

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions/update', [PermissionController::class, 'update'])->name('permissions.update');
        Route::get('permissions/get-by-jabatan', [PermissionController::class, 'getPermissionsByJabatan'])->name('permissions.get_by_jabatan');
    });

    Route::prefix('admin/stok-masuk')->name('admin.stok-masuk.')->group(function () {
        Route::get('pengadaan/status-counts', [PengadaanController::class, 'getStatusCounts'])->name('pengadaan.status-counts');
        Route::get('pengadaan/{stockInOrder}/details', [PengadaanController::class, 'details'])->name('pengadaan.details');
        Route::get('pengadaan/{stockInOrder}/distributions', [PengadaanController::class, 'distributions'])->name('pengadaan.distributions');
        Route::post('pengadaan/distributions/{distribution}/approve', [PengadaanController::class, 'approveDistribution'])->name('pengadaan.distributions.approve');
        Route::get('pengadaan/distributions/{distribution}', [PengadaanController::class, 'showDistribution'])->name('pengadaan.distributions.show');
        Route::post('pengadaan/distributions/{distribution}/update', [PengadaanController::class, 'updateDistribution'])->name('pengadaan.distributions.update');
        Route::post('pengadaan/{stockInOrder}/save-distributions', [PengadaanController::class, 'saveDistributions'])->name('pengadaan.save-distributions');
        Route::resource('pengadaan', PengadaanController::class)->parameter('pengadaan', 'stockInOrder');
        Route::post('pengadaan/{stockInOrder}/update-status', [PengadaanController::class, 'updateStatus'])->name('pengadaan.updateStatus');
        // Kirim Barang feature removed for Pengadaan: routes removed

        Route::get('penerimaan-barang/status-counts', [PenerimaanBarangController::class, 'getStatusCounts'])->name('penerimaan-barang.status-counts');
        Route::get('penerimaan-barang/get-references', [PenerimaanBarangController::class, 'getReferences'])->name('penerimaan-barang.get-references');
        Route::get('penerimaan-barang/get-reference-details', [PenerimaanBarangController::class, 'getReferenceDetails'])->name('penerimaan-barang.get-reference-details');
        Route::get('penerimaan-barang/get-shipment-details', [PenerimaanBarangController::class, 'getShipmentDetails'])->name('penerimaan-barang.get-shipment-details');
        Route::get('penerimaan-barang/get-shipments', [PenerimaanBarangController::class, 'getShipments'])->name('penerimaan-barang.get-shipments');
        Route::get('penerimaan-barang/get-next-shipment-code', [PenerimaanBarangController::class, 'getNextShipmentCode'])->name('penerimaan-barang.get-next-shipment-code');
        Route::get('penerimaan-barang/get-stock-in-orders', [PenerimaanBarangController::class, 'getStockInOrders'])->name('penerimaan-barang.get-stock-in-orders');
        Route::resource('penerimaan-barang', PenerimaanBarangController::class)->parameter('penerimaan-barang', 'goodsReceipt');
        Route::post('penerimaan-barang/{goodsReceipt}/complete', [PenerimaanBarangController::class, 'complete'])->name('penerimaan-barang.complete');
        Route::get('penerimaan-barang/bukti/{goodsReceipt}', [PenerimaanBarangController::class, 'bukti'])->name('penerimaan-barang.bukti');

        // Penerimaan Retur
        Route::get('penerimaan-retur/status-counts', [\App\Http\Controllers\Admin\StokMasuk\ReturnReceiptController::class, 'getStatusCounts'])->name('penerimaan-retur.status-counts');
        Route::resource('penerimaan-retur', \App\Http\Controllers\Admin\StokMasuk\ReturnReceiptController::class)->parameter('penerimaan-retur', 'penerimaan_retur');
        Route::post('penerimaan-retur/{penerimaan_retur}/complete', [\App\Http\Controllers\Admin\StokMasuk\ReturnReceiptController::class, 'complete'])->name('penerimaan-retur.complete');

        Route::get('request-transfer/status-counts', [RequestTransferController::class, 'getStatusCounts'])->name('request-transfer.status-counts');
        Route::resource('request-transfer', RequestTransferController::class)->parameter('request-transfer', 'transferRequest');
        Route::post('request-transfer/calculate-item-values', [RequestTransferController::class, 'calculateItemValues'])->name('request-transfer.calculate-item-values');
        Route::get('request-transfer/get-items-by-warehouse/{warehouse_id}', [RequestTransferController::class, 'getItemsByWarehouse'])->name('request-transfer.get-items-by-warehouse');
    });

    Route::prefix('admin/stok-keluar')->name('admin.stok-keluar.')->group(function () {
        Route::get('pengeluaran-barang/status-counts', [PengeluaranBarangController::class, 'getStatusCounts'])->name('pengeluaran-barang.status-counts');
        Route::resource('pengeluaran-barang', PengeluaranBarangController::class)->parameter('pengeluaran-barang', 'pengeluaranBarang');

        Route::get('permintaan-barang/status-counts', [PermintaanBarangController::class, 'getStatusCounts'])->name('permintaan-barang.status-counts');
        Route::resource('permintaan-barang', PermintaanBarangController::class)->only(['index', 'show'])->parameter('permintaan-barang', 'transferRequest');
        Route::post('permintaan-barang/{transferRequest}/update-status', [PermintaanBarangController::class, 'updateStatus'])->name('permintaan-barang.updateStatus');
        Route::post('permintaan-barang/{transferRequest}/create-shipment', [PermintaanBarangController::class, 'createShipment'])->name('permintaan-barang.createShipment');
        Route::get('permintaan-barang/{transferRequest}/items-to-ship', [PermintaanBarangController::class, 'getItemsToShip'])->name('permintaan-barang.items-to-ship');

        // Retur Out
        Route::get('retur-out/status-counts', [\App\Http\Controllers\Admin\StokKeluar\ReturnOutController::class, 'getStatusCounts'])->name('retur-out.status-counts');
        Route::resource('retur-out', \App\Http\Controllers\Admin\StokKeluar\ReturnOutController::class)->parameter('retur-out', 'retur_out');
        Route::post('retur-out/{retur_out}/complete', [\App\Http\Controllers\Admin\StokKeluar\ReturnOutController::class, 'complete'])->name('retur-out.complete');
    });

    Route::prefix('admin/manajemen-stok')->name('admin.manajemenstok.')->group(function () {
        Route::get('kartu-stok', [KartuStokController::class, 'index'])->name('kartustok.index');
        Route::get('kartu-stok/export', [KartuStokController::class, 'export'])->name('kartustok.export');
        Route::get('warehouse-stok', [WarehouseStokController::class, 'index'])->name('warehousestok.index');
        Route::get('warehouse-stok/export', [WarehouseStokController::class, 'export'])->name('warehousestok.export');
        Route::get('warehouse-stok/{warehouse}/{item}', [WarehouseStokController::class, 'show'])->name('warehousestok.show');
        Route::get('warehouse-stok/{warehouse}/{item}/data', [WarehouseStokController::class, 'data'])->name('warehousestok.data');
        Route::get('master-stok', [InventoryController::class, 'index'])->name('masterstok.index');
        Route::get('stok-opname/system-stock', [StockOpnameController::class, 'getSystemStock'])->name('stok-opname.system-stock');
        Route::post('stok-opname/{stok_opname}/update-status', [StockOpnameController::class, 'updateStatus'])->name('stok-opname.updateStatus');
        Route::resource('stok-opname', StockOpnameController::class);
        Route::resource('adjustment', AdjustmentController::class);
        Route::post('adjustment/{adjustment}/update-status', [AdjustmentController::class, 'updateStatus'])->name('adjustment.updateStatus');
    });

    Route::prefix('admin/transfer-gudang')->name('admin.transfergudang.')->group(function () {
        Route::get('surat-jalan/{transferRequest}', [\App\Http\Controllers\Admin\TransferGudang\SuratJalanController::class, 'show'])->name('surat-jalan.show');
    });

    // Riwayat Pengiriman
    Route::get('/admin/riwayat-pengiriman', [RiwayatPengirimanController::class, 'index'])->name('admin.riwayat-pengiriman.index');
    Route::get('/admin/riwayat-pengiriman/status-counts', [RiwayatPengirimanController::class, 'getStatusCounts'])->name('admin.riwayat-pengiriman.status-counts');
    Route::get('/admin/riwayat-pengiriman/{shipment}', [RiwayatPengirimanController::class, 'show'])->name('admin.riwayat-pengiriman.show');

    Route::prefix('admin/laporan')->name('admin.laporan.')->group(function () {
        Route::get('laporan-stok', [LaporanStokController::class, 'index'])->name('laporanstok.index');
        Route::get('laporan-stok/menipis', [LaporanStokController::class, 'lowStockReport'])->name('laporanstok.menipis');
        Route::get('laporan-pergerakan-barang', [LaporanController::class, 'laporanPergerakanBarang'])->name('pergerakanBarang');
        Route::get('laporan-pergerakan-barang/data', [LaporanController::class, 'pergerakanBarangData'])->name('pergerakanBarang.data');
        Route::get('laporan-transfer-gudang', [\App\Http\Controllers\Admin\Laporan\LaporanTransferGudangController::class, 'index'])->name('laporan-transfer-gudang');
        Route::get('laporan-transfer-gudang/data', [\App\Http\Controllers\Admin\Laporan\LaporanTransferGudangController::class, 'transferGudangData'])->name('laporan-transfer-gudang.data');
    });
});
