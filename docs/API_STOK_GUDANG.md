# API Stok Gudang
```env
STOCK_API_ENABLED=true
STOCK_API_WAREHOUSE_CODE=WMS24
STOCK_API_SOURCE_WAREHOUSE_CODE=GUDANG_BESAR
STOCK_API_TOKEN=<token-rahasia>
STOCK_API_RATE_LIMIT_PER_MINUTE=60
```
Jalankan `php artisan migrate`, `php artisan db:seed --class=MenuSeeder`, `php artisan stock-api:backfill`, lalu `php artisan optimize:clear`. Tambahkan IP server pusat melalui **Master Data → Akses API Stok**.

Endpoint: `GET /api/v1/health`; `GET /api/v1/stocks?updated_since=&updated_until=&page=1&per_page=100`; `GET /api/v1/stocks?as_of=YYYY-MM-DD&page=1&per_page=100`.
