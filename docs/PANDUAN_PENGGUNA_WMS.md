# Panduan Pengguna WMS

**Versi dokumen:** 1.0  
**Bahasa:** Indonesia  
**Sasaran pengguna:** Administrator, supervisor gudang, inbound, picker, QC, scanner, HR, dan karyawan.

Dokumen ini menjelaskan penggunaan aplikasi Warehouse Management System (WMS), dari pengaturan awal sampai proses penerimaan, persediaan, pengiriman, absensi, dan pelaporan. Menu yang tampil pada akun dapat berbeda karena mengikuti hak akses per peran.

## 1. Gambaran proses utama

```text
Master data → Inbound → Scan inbound → Stok gudang
                                  ↓
                        Transfer / Opname / Penyesuaian

Import resi → Picking list → QC scan → Scan out → Laporan pengiriman
```

Stok berubah hanya melalui transaksi yang telah diproses sesuai alurnya. Gunakan menu **Mutasi Stok** untuk menelusuri penyebab setiap perubahan stok.

## 2. Masuk, navigasi, dan hak akses

1. Buka alamat aplikasi, lalu masuk menggunakan email dan kata sandi yang diberikan administrator.
2. Setelah masuk, pilih menu pada sidebar kiri. Gunakan kolom pencarian dan filter tanggal pada halaman daftar untuk mempersempit data.
3. Klik nama/profil di kanan atas untuk mengubah profil atau kata sandi, kemudian klik **Logout** setelah selesai memakai perangkat bersama.

Semua menu admin memerlukan akun terautentikasi dan hak akses menu. Administrator mengatur empat jenis izin pada setiap menu: lihat, tambah, ubah, dan hapus. Jangan berbagi akun; aktivitas perubahan dicatat pada **Laporan > Aktivitas User**.

Peran bawaan yang tersedia adalah Administrator, User, Picker, QC, Inbound Scan, Admin Scan, dan Attendance Performance. Akun yang terhubung dengan data karyawan juga dapat melihat performa absensi dan mengajukan cuti/izin dari halaman mobile.

## 3. Persiapan awal oleh administrator

Lakukan pengaturan berikut sebelum transaksi operasional dimulai.

1. **Master Data > Users**: buat akun pengguna, pilih peran, hubungkan dengan karyawan bila diperlukan, aktifkan akun, lalu berikan kata sandi awal. Data pengguna juga dapat diimpor.
2. **Master Data > Roles, Menus, Permissions**: buat peran khusus bila diperlukan; atur menu yang boleh dilihat dan tindakan yang boleh dilakukan pada tiap peran.
3. **Master Data > Categories, Areas, Locations**: buat struktur kategori dan lokasi fisik penyimpanan. Area menunjukkan zona, sedangkan lokasi adalah kode/alamat tempat barang disimpan.
4. **Master Data > Items**: daftarkan SKU, nama, kategori, area/lokasi, satuan/deskripsi, stok pengaman, dan kuantitas per koli. Item dapat berupa **single** atau **bundle**. Bundle memakai komponen SKU lain dan stoknya mengikuti ketersediaan komponen.
5. Tambahkan alias barcode SKU melalui impor barcode bila label barang berbeda dengan SKU utama. Tinjau **barcode misses** untuk scan yang belum dikenali dan tandai selesai setelah master datanya diperbaiki.
6. Lengkapi **Suppliers**, **Stores**, dan **Kurir** sebelum membuat dokumen yang memakai data tersebut.
7. Pastikan gudang yang diperlukan telah tersedia, termasuk gudang rusak. Akses API stok, bila digunakan, dibatasi melalui **Master Data > Stock API Access**.

Praktik yang disarankan: gunakan satu SKU unik untuk satu barang fisik; isi lokasi dan koli sejak awal; nonaktifkan item yang tidak lagi dipakai alih-alih menghapus riwayat transaksi.

## 4. Dashboard

**Dashboard** menampilkan ringkasan operasional berdasarkan tanggal pilihan: jumlah resi aktif dan dibatalkan, QC yang lulus, scan out, selisih scan out, resi duplikat, progres per kurir, outbound manual, stok kosong, serta dokumen yang masih menunggu persetujuan.

Gunakan dashboard sebagai pemeriksaan awal dan akhir shift:

- pilih tanggal kerja;
- selesaikan selisih scan out dan resi duplikat;
- tindak lanjuti stok kosong/di bawah stok pengaman;
- proses dokumen yang menunggu persetujuan.

## 5. Inbound: penerimaan barang

Menu utama: **Inbound > Penerimaan Barang**. Sistem juga menyediakan jenis dokumen retur inbound dan inbound manual bila hak akses/menu tersebut diaktifkan.

### 5.1 Membuat dokumen penerimaan

1. Klik tombol tambah dokumen.
2. Isi nomor referensi, supplier, nomor dan tanggal surat jalan, tanggal transaksi, catatan, serta foto surat jalan bila tersedia.
3. Tambahkan baris SKU, jumlah, jumlah koli, satuan input, dan catatan item. Untuk banyak data, unduh template atau gunakan impor dokumen/item jika tersedia di halaman.
4. Simpan. Status awal dokumen adalah **Menunggu Scan**.
5. Bila diperlukan, buka detail lalu cetak/unduh QR penerimaan per koli sebagai label penelusuran.

Data dapat diubah atau dihapus selama belum terkunci oleh tahap proses berikutnya. Pastikan jumlah dokumen sesuai surat jalan sebelum diteruskan ke scanner.

### 5.2 Scan inbound (desktop atau mobile)

1. Buka **Inbound > Scan Inbound** pada desktop atau **Mobile > Inbound Scan**.
2. Pilih/buka dokumen penerimaan yang masih menunggu scan.
3. Scan SKU atau barcode item satu per satu. Sistem mencocokkan hasil scan dengan jumlah pada dokumen.
4. Periksa progres setiap item. Jika semua jumlah sesuai, klik **Selesaikan**.

Status berubah dari **Menunggu Scan** → **Sedang Scan** → **Selesai**. Gunakan **Reset** hanya bila proses scan harus diulang sesuai kewenangan. Setelah scan selesai, lakukan approval dokumen dari halaman penerimaan agar stok masuk tercatat.

### 5.3 Approval inbound

Supervisor/admin membuka detail dokumen yang telah selesai dipindai, memeriksa SKU dan kuantitas, lalu memilih **Approve**. Approval mencatat stok masuk dan membuat riwayat mutasi. Jangan approve bila scan atau dokumen fisik belum benar.

## 6. Inventory dan pengendalian stok

### 6.1 Item Stocks dan Mutasi Stok

**Inventory > Item Stocks** menunjukkan stok per gudang, stok pengaman, dan status pemantauan. Gunakan filter serta ekspor untuk stok fisik/analisis. Stok pengaman dapat diperbarui satuan atau massal; aktifkan pemantauan hanya untuk SKU yang harus dipantau.

**Inventory > Stock Mutations** adalah audit trail semua stok masuk/keluar. Buka detail mutasi untuk melihat sumber dokumen, gudang, arah perubahan, jumlah sebelum/sesudah (bila tersedia), waktu, dan pengguna yang memprosesnya.

### 6.2 Transfer gudang

Alur transfer adalah: **buat transfer → stok keluar dari gudang asal → QC tujuan → stok OK masuk gudang tujuan / reject masuk gudang rusak**.

1. Buka **Inventory > Transfer Gudang**, lalu buat dokumen transfer.
2. Pilih gudang asal dan tujuan yang berbeda, tanggal, serta item dan jumlah. Sistem mengurangi stok dari gudang asal saat transfer dibuat.
3. Bila barang memiliki jejak koli QR, scan koli yang dikirim pada detail transfer.
4. Petugas tujuan melakukan **QC**: masukkan jumlah OK, reject, dan kurang. Total ketiganya harus sama dengan jumlah transfer.
5. Selesaikan QC. Jumlah OK menambah stok tujuan; reject dicatat sebagai barang rusak; kekurangan tetap tercatat sebagai selisih QC.

Untuk transfer berbasis koli yang tidak memiliki QR, pilih mode legacy dan isi alasan wajib. Transfer hanya dapat dibatalkan sebelum QC; pembatalan mengembalikan stok asal dan melepas reservasi koli.

### 6.3 Stock opname

Stock opname dapat dibuat dari desktop atau antarmuka mobile.

1. Buat batch opname, pilih gudang dan tanggal, serta isi catatan.
2. Di mobile, cari/scan SKU lalu masukkan jumlah fisik dan koli. Koreksi atau hapus baris jika masih dalam batch terbuka.
3. Klik **Selesaikan Batch** setelah semua area dihitung.
4. Supervisor memeriksa selisih sistem vs fisik melalui **Inventory > Stock Opname** dan memilih **Approve**.

Approval menerapkan koreksi stok dan menghasilkan mutasi. Gunakan laporan stock opname untuk melihat selisih per SKU. Jangan membuat beberapa batch untuk SKU/gudang yang sama pada periode penghitungan yang sama tanpa prosedur rekonsiliasi.

### 6.4 Penyesuaian stok

Gunakan **Inventory > Penyesuaian Stok** hanya untuk koreksi yang bukan hasil proses normal, misalnya pembetulan administrasi. Buat dokumen atau impor dari template, isi gudang, tanggal, alasan/catatan, SKU, jumlah dan koli, lalu ajukan approval. Setelah disetujui, sistem membuat mutasi penyesuaian. Dokumen pending masih dapat diperbarui atau dihapus sesuai izin.

### 6.5 Barang rusak dan alokasi

Pada **Barang Rusak**, buat dokumen dari sumber stok gudang, retur inbound, atau input manual. Pilih sumber, gudang asal, SKU, jumlah, kode alasan, dan catatan; lalu approve setelah barang fisik diverifikasi. Sistem memisahkan stok tersebut ke proses barang rusak.

Pada **Alokasi Barang Rusak**, tentukan tindak lanjut barang rusak, misalnya pengiriman ke supplier, penempatan ke gudang tujuan, atau pengolahan ulang. Jika memakai **Resep Rework**, pilih resep dan pengali; sistem menghitung komponen rusak yang dipakai serta output rework. Approval alokasi membentuk dampak stok dan dokumen outbound terkait bila berlaku.

Gunakan ringkasan per SKU, aging, dan ekspor pada halaman barang rusak untuk menentukan prioritas penyelesaian.

## 7. Outbound dan pengiriman

### 7.1 Resi dan picking list

1. Buka **Inventory > Import Resi**, unduh/ikuti format impor, lalu unggah file resi dan detail pesanannya.
2. Pastikan nomor resi, order reference, kurir, tanggal unggah, SKU, jumlah, serta catatan pembeli benar.
3. Periksa ringkasan, catatan pembeli, status impor, dan resi ganda sebelum proses picking.
4. Jika ada pembatalan, gunakan aksi cancel yang sesuai kondisi: sebelum QC, saat siap kirim, atau setelah kirim. Gunakan uncancel hanya bila memang diperbaiki dan diizinkan.
5. Buka **Inventory > Picking List**, pilih tanggal/filter, lalu lakukan hitung ulang bila data resi berubah. Cetak atau ekspor daftar untuk picker.

Picker dapat membuka **Mobile > Picking List** untuk melihat kebutuhan ambil barang. Bila ada kekurangan/exception, catat melalui proses exception dan kembalikan bila barang sudah tersedia sesuai SOP.

### 7.2 QC scan resi

QC dapat memakai **Outbound > QC Scan Desktop** atau **Mobile > QC**.

1. Scan nomor resi untuk membuka order.
2. Scan semua SKU/barcode isi paket sampai kuantitas cocok.
3. Jika SKU perlu diganti, gunakan fitur substitusi hanya berdasarkan persetujuan operasional dan alasan yang jelas.
4. Jika ada masalah, gunakan **Hold** dan tulis alasan; jangan memaksakan penyelesaian.
5. Setelah semua item sesuai, klik **Selesaikan**. Resi menjadi **Siap Scan Out**.

Riwayat QC menyimpan status, scanner, waktu, percobaan duplikat, dan reset. Atur SKU yang dikecualikan melalui **SKU Exception QC** hanya bila memang tidak perlu dicocokkan dalam QC.

Status operasional resi:

| Status | Arti |
|---|---|
| Menunggu QC | Belum ada proses QC |
| QC Berjalan | QC sudah dibuka tetapi belum selesai |
| Siap Scan Out | QC lulus dan paket dapat diserahkan ke kurir |
| Scan Out Selesai | Paket sudah dipindai keluar |
| Cancel / Cancel Ready to Ship / Cancel After Ship | Pesanan dibatalkan pada tahap terkait |

### 7.3 Scan out dan serah terima kurir

1. Buka **Outbound > Scan Out Desktop** atau **Mobile > Scan Out**.
2. Pilih/scan kurir dan scan nomor resi yang berstatus siap scan out.
3. Konfirmasi data packing bila diminta, lalu simpan scan.
4. Periksa riwayat scan out dan percobaan yang gagal. Gunakan dashboard untuk membandingkan total resi aktif dengan total scan out per kurir.

Sistem menolak resi yang belum lulus QC, sudah dibatalkan, atau sudah pernah scan out. Jangan menggunakan scan out sebagai pengganti QC.

### 7.4 Outbound manual, retur outbound, dan surat jalan

**Outbound > Manual** dipakai untuk pengeluaran barang di luar alur resi. Isi referensi, penerima, alamat/telepon, gudang, surat jalan, tanggal, item, dan kuantitas.

Alurnya: buat dokumen → **Menunggu QC** → **Sedang QC** → QC manual selesai → approval → **Selesai**. Stok baru dikurangi saat dokumen manual disetujui setelah QC. Setelah approval, cetak dokumen pada **History Surat Jalan**.

**Outbound > Retur** dipakai untuk retur keluar sesuai kebutuhan operasional; buat dokumen, lengkapi item dan referensi, lalu approval agar mutasi stok diterapkan. Histori surat jalan dapat dibuka dan dicetak ulang dari menu khususnya.

## 8. Retur customer

Gunakan **Inventory > Retur Customer** saat barang dikembalikan pelanggan.

1. Buat penerimaan retur dan cari resi/order bila tersedia.
2. Isi tanggal diterima, catatan, foto barang bila diperlukan, serta kondisi setiap item: layak kembali, rusak, kemasan rusak, kuantitas, dan akar masalah.
3. Simpan hasil inspeksi.
4. Finalisasi sebagai **Selesai** bila barang diterima dan hasil pemeriksaan sudah tepat, atau **Tidak Diterima** bila retur tidak diterima.

Finalisasi bersifat mengunci proses dan, untuk retur yang selesai, menerapkan mutasi serta membuat pencatatan barang rusak jika diperlukan. Gunakan detail, ekspor, dan **Laporan > Laporan Retur** untuk evaluasi penyebab retur.

## 9. Laporan dan monitoring

Gunakan filter tanggal, gudang, kurir, SKU, atau status sebelum mengekspor. Menu laporan tersedia sesuai hak akses.

| Menu laporan | Kegunaan |
|---|---|
| Laporan Scan Out | Resi/paket yang telah keluar, kurir, waktu dan status packing |
| Laporan Stok Pengaman | SKU yang mencapai atau di bawah batas stok aman |
| Snapshot Low Stock | Simpan kondisi low stock pada waktu tertentu dan tindak lanjutnya |
| Daily Stock Forecast | Estimasi kebutuhan stok harian |
| Laporan Retur | Analisis retur customer dan alasan/akar masalah |
| Replenishment Display | Kebutuhan pengisian ulang area display |
| Laporan Transfer Gudang | Status, volume, QC dan tren transfer antar gudang |
| Laporan Stock Opname | Hasil opname dan selisih per SKU |
| Aktivitas User | Jejak aktivitas pengguna untuk audit |
| Display Receipts | Monitoring penerimaan untuk display |

Untuk low stock, jangan hanya mengubah angka stok pengaman. Tinjau stok fisik, mutasi terbaru, forecast, dan dokumen inbound/transfer yang masih berjalan sebelum melakukan replenishment.

## 10. Absensi dan HR

### 10.1 Pengaturan absensi oleh HR/admin

Urutan pengaturan yang disarankan:

1. Buat **Posisi** dan **Karyawan Absensi**; impor karyawan bila jumlahnya banyak.
2. Daftarkan **Device Absensi** dan hubungkan **Mapping Fingerprint** dengan karyawan.
3. Buat **Shift Kerja** (jam masuk/pulang dan toleransi keterlambatan).
4. Atur **Jadwal Kerja** per karyawan, atau gunakan/impor **Template Jadwal**.
5. Masukkan **Hari Libur**.
6. Pastikan log perangkat masuk pada **Raw Log Fingerprint/Machine Log** dan rekap absensi terbentuk.

Halaman **Rekap Absensi** menampilkan kehadiran, keterlambatan, jam kerja, pulang awal, dan lembur. **Monitor Harian** membantu melihat karyawan yang belum check-in atau berstatus tidak hadir. **Live Display** dapat dipasang pada layar monitor untuk melihat kejadian absensi terbaru.

### 10.2 Cuti/izin dan lembur

Karyawan yang akun penggunaannya terhubung dapat membuka **Mobile > Pengajuan Cuti/Izin**, memilih jenis dan rentang tanggal, menulis alasan, serta mengunggah bukti bila diperlukan. Pengajuan berstatus **Pending** sampai HR/admin memilih setuju atau tolak pada menu **Cuti/Izin**.

Pada **Monitor Lembur**, supervisor dapat memilih satu atau banyak data lembur dan melakukan approval atau penolakan massal. Hanya lembur yang disetujui dihitung sebagai lembur approved pada performa karyawan dan laporan.

### 10.3 Performa absensi karyawan

Menu **Performa Absensi Saya** menampilkan kalender kerja, check-in/out, keterlambatan, total jam kerja, lembur yang disetujui, tingkat kehadiran, dan skor performa pada bulan pilihan. Data mengikuti jadwal, hari libur, cuti/izin yang disetujui, serta log absensi yang tersedia.

## 11. KPI

**Laporan > KPI** digunakan oleh administrator/supervisor untuk:

1. membuat definisi KPI dan targetnya;
2. mengaitkan KPI ke posisi serta penugasan karyawan;
3. membuat snapshot penilaian pada periode tertentu;
4. memeriksa atau mengoreksi item skor bila diperlukan;
5. menghitung ulang snapshot sebelum mengunci hasil.

Setelah snapshot dikunci, gunakan **Laporan KPI Score** untuk melihat ringkasan dan ekspor nilai. Kunci hanya setelah periode, penugasan, data sumber, dan penyesuaian manual telah diverifikasi.

## 12. Batasan penting dan pemecahan masalah

- **Barcode tidak ditemukan:** cek SKU/alias barcode di Master Data > Items, lalu lihat daftar barcode misses. Jangan mengganti SKU transaksi sembarangan.
- **Scan inbound tidak dapat selesai:** pastikan semua SKU dan jumlah scan tepat seperti dokumen. Periksa item yang lebih/kurang scan.
- **QC resi tertahan:** periksa jumlah SKU, status hold, dan aturan SKU exception. Selesaikan penyebabnya atau reset melalui prosedur berwenang.
- **Scan out ditolak:** pastikan resi belum dibatalkan, QC sudah lulus, dan belum pernah di-scan out.
- **Stok tidak sesuai:** lihat Mutasi Stok, lalu periksa dokumen pending approval, transfer yang belum QC, opname/penyesuaian, atau outbound manual yang belum selesai. Jangan langsung membuat penyesuaian tanpa investigasi.
- **Tidak melihat menu:** minta administrator memeriksa role, permission menu, dan status aktif akun.
- **Data absensi tidak masuk:** periksa perangkat, serial/koneksi, mapping fingerprint, jadwal kerja, dan raw/machine log sebelum melakukan koreksi.

## 13. Checklist kerja harian

### Supervisor gudang

- Tinjau dashboard dan dokumen pending approval.
- Pastikan inbound yang selesai scan segera diverifikasi dan di-approve.
- Tindak lanjuti transfer yang menunggu QC dan stok low/empty.
- Bandingkan resi aktif, QC lulus, dan scan out per kurir sebelum tutup shift.
- Periksa aktivitas user bila terdapat selisih atau perubahan tidak biasa.

### Inbound scanner

- Cocokkan barang fisik, surat jalan, dan dokumen sistem.
- Scan seluruh SKU sampai progres lengkap.
- Laporkan selisih sebelum menekan selesai; jangan menambah item yang tidak ada di dokumen tanpa koreksi dokumen.

### QC dan scan out

- QC item paket terlebih dahulu, kemudian scan out.
- Gunakan hold untuk kasus tidak sesuai dan isi alasan.
- Pastikan kurir dan paket benar sebelum serah terima.

### HR

- Perbarui jadwal/shift sebelum periode berjalan.
- Tinjau log mesin dan monitor harian setiap hari.
- Proses pengajuan cuti/izin dan approval lembur tepat waktu.

## 14. Catatan pengendalian

Approval, cancel, reset, dan perubahan master data berdampak pada audit serta stok. Berikan izin tersebut hanya kepada personel yang bertanggung jawab. Untuk perubahan yang memengaruhi stok, selalu simpan alasan yang jelas dan cocokkan dengan bukti fisik/dokumen.

---

Dokumen ini disusun dari modul dan alur yang tersedia pada aplikasi. Jika instalasi memiliki menu yang dinonaktifkan atau aturan internal tambahan, jadikan SOP perusahaan sebagai rujukan utama untuk otorisasi dan keputusan operasional.
