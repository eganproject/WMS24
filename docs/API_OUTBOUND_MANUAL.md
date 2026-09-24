# API Outbound Manual

API ini membuat transaksi **Outbound Manual** dengan status awal `pending_qc`. Pembuatan transaksi belum memotong stok; stok akan tetap mengikuti proses QC dan approval yang sudah berjalan di WMS.

## Konfigurasi dan keamanan

Endpoint memakai Bearer token dan rate limit khusus untuk operasi tulis. Whitelist IP tetap memakai daftar yang sama dengan API stok agar dapat dikelola dari halaman yang sudah tersedia tanpa mengubah akses API stok lama.

```env
OUTBOUND_MANUAL_API_ENABLED=true
OUTBOUND_MANUAL_API_TOKEN=<token-tulis-rahasia-yang-panjang>
OUTBOUND_MANUAL_API_RATE_LIMIT_PER_MINUTE=30

# Opsional, tetapi disarankan agar kolom pembuat terisi.
OUTBOUND_MANUAL_API_CREATED_BY_USER_ID=1
```

Tambahkan IP pemanggil melalui **Master Data → Akses API Stok**, lalu jalankan:

```bash
php artisan migrate
php artisan optimize:clear
```

## Membuat outbound manual

`POST /api/v1/outbound/manuals`

Header:

```http
Authorization: Bearer <token-tulis-rahasia>
Accept: application/json
Content-Type: application/json
```

Contoh body:

```json
{
  "external_id": "OMS-OUT-20260924-0001",
  "warehouse_code": "GUDANG_DISPLAY",
  "transacted_at": "2026-09-24T09:30:00+07:00",
  "ref_no": "ORDER-0001",
  "surat_jalan_no": "SJ-ORDER-0001",
  "surat_jalan_at": "2026-09-24",
  "recipient_name": "Budi",
  "recipient_phone": "081234567890",
  "recipient_address": "Jl. Contoh No. 1, Jakarta",
  "note": "Kirim sebelum pukul 17.00",
  "items": [
    {
      "sku": "SKU-001",
      "qty": 2,
      "note": "Fragile"
    },
    {
      "sku": "SKU-002",
      "qty": 1
    }
  ]
}
```

Contoh cURL:

```bash
curl --request POST 'https://domain-wms.example/api/v1/outbound/manuals' \
  --header 'Authorization: Bearer <token-tulis-rahasia>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "external_id": "OMS-OUT-20260924-0001",
    "warehouse_code": "GUDANG_DISPLAY",
    "transacted_at": "2026-09-24T09:30:00+07:00",
    "ref_no": "ORDER-0001",
    "recipient_name": "Budi",
    "recipient_phone": "081234567890",
    "recipient_address": "Jl. Contoh No. 1, Jakarta",
    "items": [
      {"sku": "SKU-001", "qty": 2}
    ]
  }'
```

Field wajib:

| Field | Keterangan |
|---|---|
| `external_id` | ID unik dari sistem pengirim, maksimal 100 karakter. Dipakai sebagai kunci idempotensi. |
| `warehouse_code` | Kode gudang yang sudah ada di WMS. |
| `transacted_at` | Tanggal/waktu transaksi. Disarankan ISO-8601 dengan offset zona waktu. |
| `items` | Array berisi 1–500 item. |
| `items.*.sku` | SKU yang sudah ada di master item dan tidak boleh duplikat. |
| `items.*.qty` | Qty satuan, integer minimal 1. |

Field lainnya opsional. Jika `surat_jalan_no` tidak dikirim, WMS membuat nomor surat jalan otomatis.

Untuk `GUDANG_BESAR` (sesuai `inventory.default_warehouse_code`), setiap item wajib mengirim `koli`. Nilai `qty` harus sama dengan `koli × isi per koli` pada master item, misalnya:

```json
{
  "sku": "SKU-KOLI-001",
  "qty": 24,
  "koli": 2
}
```

## Respons

Request baru menghasilkan HTTP `201`:

```json
{
  "success": true,
  "message": "Outbound manual berhasil dibuat dan masuk tahap QC.",
  "meta": {
    "idempotent_replay": false
  },
  "data": {
    "id": 123,
    "external_id": "OMS-OUT-20260924-0001",
    "code": "OUT-MNL-20260924093000-A1B2",
    "type": "manual",
    "status": "pending_qc",
    "warehouse": {
      "code": "GUDANG_DISPLAY",
      "name": "Gudang Display"
    },
    "items": [
      {
        "sku": "SKU-001",
        "name": "Nama Barang",
        "qty": 2,
        "note": null
      }
    ]
  }
}
```

Mengirim ulang `external_id` dengan payload yang sama tidak membuat transaksi baru. API mengembalikan data lama dengan HTTP `200` dan `meta.idempotent_replay: true`. Jika `external_id` yang sama dikirim dengan payload berbeda, API mengembalikan HTTP `409` dengan kode `IDEMPOTENCY_CONFLICT`.

Kode respons utama:

| HTTP | Kode | Arti |
|---|---|---|
| `201` | - | Transaksi berhasil dibuat. |
| `200` | - | Request identik sudah pernah diproses. |
| `401` | `UNAUTHORIZED` | Bearer token salah atau belum dikonfigurasi. |
| `403` | `IP_NOT_ALLOWED` | IP pemanggil belum diizinkan. |
| `409` | `IDEMPOTENCY_CONFLICT` | `external_id` sudah dipakai untuk payload berbeda. |
| `422` | `VALIDATION_ERROR` | Payload, SKU, koli, bundle, atau stok tidak valid. |
| `429` | - | Rate limit terlampaui. |
| `503` | `API_DISABLED` / `API_CONFIGURATION_ERROR` | API nonaktif atau user pembuat dari konfigurasi tidak valid. |

Contoh error validasi:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Data outbound manual tidak valid.",
    "details": {
      "qty": [
        "Stok tidak mencukupi untuk SKU SKU-001. Tersedia 1, dibutuhkan 2."
      ]
    }
  }
}
```
