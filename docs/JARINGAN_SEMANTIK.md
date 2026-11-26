# JARINGAN SEMANATIK LAPANGANKU (Mermaid)

Diagram ini menggambarkan hubungan antar entitas utama dalam basis data dan alur sistem LapanganKu.

## 1. Diagram Relasi Basis Data (ERD Sederhana)

```mermaid
erDiagram
    users ||--o{ booking : membuat
    users ||--o{ pembayaran : verifikasi (verified_by)
    lapangan ||--o{ booking : dimiliki
    booking ||--o{ pembayaran : memiliki

    users {
        int id PK
        string nama
        string email UK
        string password
        enum role (penyewa, admin, pemilik)
        datetime created_at
    }

    lapangan {
        int id PK
        string nama
        enum jenis (futsal, badminton)
        string deskripsi
        int harga_per_jam
        time jam_buka
        time jam_tutup
        enum status (aktif, nonaktif)
    }

    booking {
        int id PK
        string kode UK
        int user_id FK
        int lapangan_id FK
        date tanggal
        time jam_mulai
        time jam_selesai
        int durasi
        int total_harga
        datetime batas_bayar
        enum status (menunggu_pembayaran, menunggu_verifikasi, dikonfirmasi, ditolak, dibatalkan)
        datetime created_at
    }

    pembayaran {
        int id PK
        int booking_id FK
        string bukti_file
        int verified_by FK
        datetime verified_at
        datetime uploaded_at
        text catatan_admin
    }
```

## 2. Alur Booking Penyewa

```mermaid
sequenceDiagram
    participant P as Penyewa
    participant S as Sistem (PHP)
    participant DB as Basis Data

    P->>S: Buka halaman jadwal
    S->>DB: Ambil lapangan aktif & slot kosong
    DB-->>S: Data jadwal
    S-->>P: Tampilkan grid slot hijau/merah
    P->>S: Pilih 1-3 slot berurutan
    S->>DB: Simpan booking (status: menunggu_pembayaran, batas_bayar = +2 jam)
    DB-->>S: Kode booking LK-YYYYMMDD-XXXX
    S-->>P: Tampilkan detail + hitung mundur 2 jam
    P->>S: Transfer & unggah bukti (JPG/PNG/PDF, ≤2 MB)
    S->>DB: Simpan bukti, ubah status ke menunggu_verifikasi
    S-->>P: Konfirmasi bukti terunggah
    P->>S: Tunggu verifikasi admin
    Admin->>S: Verifikasi atau tolak bukti
    S->>DB: Ubah status (dikonfirmasi / ditolak)
    S-->>P: Notifikasi hasil verifikasi
```

## 3. Alur Verifikasi Admin

```mermaid
flowchart TD
    A[Admin buka admin/booking.php] --> B{Filter / Pencarian}
    B --> C[Tampilkan tabel booking]
    C --> D{Status = menunggu_verifikasi?}
    D -->|Ya| E[Tombol Verifikasi / Tolak tersedia]
    D -->|Tidak| F[Tombol disembunyikan]
    E --> G[Admin klik Verifikasi atau Tolak]
    G --> H{Validasi CSRF & alasan?}
    H -->|Tolak tanpa alasan| I[Tampilkan error: Alasan wajib diisi]
    H -->|Valid| J[Update status booking & pembayaran]
    J --> K[Flash pesan sukses / gagal]
    K --> C
    I --> E
```

## 4. Alur Pembatalan Otomatis

```mermaid
stateDiagram-v2
    [*] --> menunggu_pembayaran : Buat booking
    menunggu_pembayaran --> menunggu_verifikasi : Unggah bukti transfer
    menunggu_pembayaran --> dibatalkan : Waktu habis (> 2 jam)
    menunggu_pembayaran --> dibatalkan : Penyewa batalkan
    menunggu_verifikasi --> dikonfirmasi : Admin verifikasi
    menunggu_verifikasi --> ditolak : Admin tolak
    menunggu_verifikasi --> dibatalkan : Penyewa batalkan (>= 3 jam sebelum mulai)
    dikonfirmasi --> dibatalkan : Penyewa batalkan (>= 3 jam sebelum mulai)
    ditolak --> [*]
    dibatalkan --> [*]
```

---

*Dokumentasi untuk Tugas Besar IMK — LapanganKu*
