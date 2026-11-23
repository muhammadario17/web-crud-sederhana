# Web CRUD Sederhana (Login + HTML + CSS + PHP + MySQL XAMPP)

Aplikasi web sederhana dengan halaman **Login** terlebih dahulu, diikuti halaman **Kelola Akun (CRUD)** menggunakan HTML, CSS, PHP Session, dan database MySQL/MariaDB di XAMPP.

## Struktur File

- `index.html` — Halaman **Login** (dan tab **Buat Akun** untuk pendaftaran awal).
- `dashboard.html` — Halaman **Kelola Akun** setelah login (menampilkan daftar akun tersimpan, Edit, Hapus baris, dan Logout).
- `style.css` — Styling CSS sederhana dengan layout responsif.
- `database.sql` — Script SQL untuk membuat database `db_crud_akun` dan tabel `users`.
- `koneksi.php` — Koneksi PDO ke MySQL XAMPP (`127.0.0.1`, user `root`, database `db_crud_akun`).
- `api.php` — Endpoint PHP dengan session authentication (`login`, `logout`, `me`, `create`, `GET`, `PUT`, `DELETE`).

## Persiapan Database XAMPP

1. Buka **XAMPP Control Panel** dan pastikan **Apache** dan **MySQL** dalam status **Running**.
2. Database `db_crud_akun` dan tabel `users` dapat di-import melalui **phpMyAdmin** (`http://localhost/phpmyadmin`) dari file `database.sql`.

## Cara Mengakses

### Lewat Apache XAMPP (`htdocs`)
1. Pastikan **Apache** dan **MySQL** aktif di XAMPP Control Panel.
2. Buka browser dan akses:
   `http://localhost/wp1/Web%20CRUD%20sederhana/`

### Atau lewat PHP Built-in Server
1. Buka terminal di folder `C:\xampp\htdocs\wp1\Web CRUD sederhana`:
   ```bash
   C:\xampp\php\php.exe -S 127.0.0.1:8000
   ```
2. Buka browser dan akses `http://127.0.0.1:8000`.
