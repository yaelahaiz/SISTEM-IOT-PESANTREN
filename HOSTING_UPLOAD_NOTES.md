# Catatan Upload Hosting

Target domain: http://monitoring-pesantren-rm.arndilhmzbr.engineer/

## Yang sudah disiapkan

- Konfigurasi database hosting aktif otomatis saat domain adalah `monitoring-pesantren-rm.arndilhmzbr.engineer`.
- Localhost tetap memakai database lokal `iot_pesantren`.
- Base URL aplikasi sudah dinamis, jadi upload ke root domain tidak lagi memakai `/SISTEM-IOT-PESANTREN`.
- Endpoint firmware gateway diarahkan ke `http://monitoring-pesantren-rm.arndilhmzbr.engineer/api/`.
- File debug chart sudah dihapus dari source.
- `.htaccess` ditambahkan untuk mematikan directory listing dan memblokir file SQL/arsip/log jika tidak sengaja ikut terupload.

## Urutan upload

1. Di phpMyAdmin hosting, pilih database `arndilh2_monitoringpesantrenrm`.
2. Import `database/iot_pesantren_hosting.sql`.
3. Upload isi paket `SISTEM-IOT-PESANTREN-hosting-ready.zip` ke root domain atau `public_html`.
4. Buka `http://monitoring-pesantren-rm.arndilhmzbr.engineer/`.

Login default:

- Username: `admin`
- Password: `password`

Jika login gagal, jalankan SQL di `database/reset_admin_password.sql` lewat phpMyAdmin pada database hosting, lalu coba login ulang dengan akun default di atas.

Setelah berhasil login, ubah password admin dari halaman pengaturan.
