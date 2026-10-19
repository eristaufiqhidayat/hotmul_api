## Adalah API untuk applikasi  KHOTMUL Quran

### Dengan Laravel versi 12.22.1 
### Test dari sftp

### Fitur BRD tambahan (Okt 2026)

Setelah `git pull` di server, jalankan:

```
php artisan migrate --force \
  --path=database/migrations/2026_10_19_000001_create_badal_assignments_table.php \
  --path=database/migrations/2026_10_19_000002_create_user_badges_table.php \
  --path=database/migrations/2026_10_19_000003_create_kas_transaksi_table.php \
  --path=database/migrations/2026_10_19_000004_create_dokumentasi_khotaman_table.php \
  --path=database/migrations/2026_10_19_000005_create_device_tokens_table.php
php artisan storage:link   # sekali saja, untuk URL file dokumentasi & bukti kas
```

`--path` dipakai supaya hanya lima tabel baru yang dibuat, karena sebagian
tabel lama di server dibuat manual dan belum tercatat di tabel `migrations`.

Migrasi baru: `badal_assignments`, `user_badges`, `kas_transaksi` (data lama
`donasi` ikut disalin sebagai pemasukan terverifikasi), `dokumentasi_khotaman`,
`device_tokens`.

Endpoint baru (prefix `/api/v1`, wajib login):

| Endpoint | Fungsi |
|---|---|
| `GET /jadwal-bergulir` | Juz periode berjalan, kalender 14 hari, riwayat, 2 juz berikutnya |
| `GET /badal` | Juz yang bisa diambil alih di grup, badal saya, badge |
| `POST /badal/{assignment}/ambil`, `POST /badal/{id}/batal` | Ambil alih / batalkan badal |
| `GET /badges` | Jumlah badge Pengganti Aktif |
| `GET /kas`, `POST /kas`, `DELETE /kas/{id}` | Kas grup: saldo, riwayat, tambah sumbangan |
| `POST /kas/{id}/verifikasi` | Admin memverifikasi sumbangan |
| `GET /kas/export` | Unduh CSV (dibuka di Excel) |
| `GET /dokumentasi`, `POST /dokumentasi`, `DELETE /dokumentasi/{id}` | Dokumentasi khotaman (foto/video/audio, maks 50 MB) |
| `POST /device-token`, `DELETE /device-token` | Token FCM perangkat |

Upload 50 MB butuh `upload_max_filesize` dan `post_max_size` PHP minimal `55M`
(cPanel → Select PHP Version / MultiPHP INI Editor).

Push notification (FCM) aktif setelah `.env` diisi:

```
FCM_PROJECT_ID=id-project-firebase
FCM_CREDENTIALS=/home/USER/firebase-service-account.json
```

File JSON diambil dari Firebase Console → Project settings → Service accounts →
Generate new private key. Simpan di luar folder `public_html`. Selama belum
diisi, notifikasi tetap tersimpan di tabel `notifications` seperti biasa.
