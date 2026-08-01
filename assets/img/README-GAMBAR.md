# Panduan Gambar — Pondok Pesantren Ash-Shiddiq

Taruh file gambar sesuai **nama & path persis** di bawah ini. Jika file ada, otomatis tampil.
Jika belum ada, placeholder gradient tetap muncul (tidak error). Tidak perlu edit kode.

Format yang didukung: **.jpg** (disarankan). Untuk kualitas lebih baik bisa pakai `.webp`,
tapi di kode memakai `.jpg`. Jika ingin pakai format lain, ganti nama file di kode manual.

Tips umum: kompres dulu ke < 300 KB per file (pakai tinypng.com atau squoosh.app).

---

## 1. Halaman Beranda (`/`)

### 1a. Latar belakang Hero (opsional)
| File                               | Keterangan                                    |
|------------------------------------|------------------------------------------------|
| `assets/img/hero-bg.jpg`           | Background hero full-screen. Landscape. Min. 1920x1080. Overlay hijau gelap otomatis ditambahkan. |

### 1b. Foto Mudir (section Sambutan)
| File                               | Rasio  | Ukuran disarankan | Keterangan             |
|------------------------------------|--------|-------------------|------------------------|
| `assets/img/mudir.png` atau `mudir.jpg` | 3:4 | 600 × 800 px | Foto potret Mudir; jika keduanya ada, PNG dipakai |

### 1c. Galeri "Kehidupan Santri" (5 foto)
Rasio **16:10** landscape (~1200 × 750 px). Foto pertama tampil 2x lebih besar.

| File                                        | Label tampilan          |
|---------------------------------------------|-------------------------|
| `assets/img/galeri/halaqah-tahfidz.jpg`     | Halaqah Tahfidz Pagi    |
| `assets/img/galeri/shalat-berjamaah.jpg`    | Shalat Berjamaah        |
| `assets/img/galeri/asrama-santri.jpg`       | Asrama Santri           |
| `assets/img/galeri/ekstrakurikuler.jpg`     | Ekstrakurikuler         |
| `assets/img/galeri/wisuda-hafidz.jpg`       | Wisuda Hafidz           |

### 1d. Foto Avatar Testimoni (3 orang)
Rasio **1:1** bulat (200 × 200 px). Kalau kosong, inisial huruf tetap tampil.

| File                                      | Nama                 |
|-------------------------------------------|----------------------|
| `assets/img/testimoni/faisal-rahman.jpg`  | Bpk. Faisal Rahman   |
| `assets/img/testimoni/zaki-alhasan.jpg`   | Zaki Al-Hasan        |
| `assets/img/testimoni/ibu-nurhayati.jpg`  | Ibu Nurhayati        |

---

## 2. Halaman Profil (`/profil`)

### 2a. Foto Identitas Pesantren (3 foto)
| File                                         | Rasio | Keterangan               |
|----------------------------------------------|-------|--------------------------|
| `assets/img/profil/gedung-pesantren.jpg`     | 4:3   | Foto utama gedung (besar)|
| `assets/img/profil/masjid-pesantren.jpg`     | 4:3   | Foto masjid (kecil kiri) |
| `assets/img/profil/asrama-santri.jpg`        | 4:3   | Foto asrama (kecil kanan)|

### 2b. Foto Pengajar (4 orang)
Rasio **1:1** kotak/bulat (400 × 400 px).

| File                                         | Nama                        |
|----------------------------------------------|-----------------------------|
| `assets/img/pengajar/kh-ahmad-fauzi.jpg`        | KH. Suroto Abu Nizam, M. Pd.         |
| `assets/img/pengajar/usth-ina-rusiana.jpg`      | Usth. Ina Rusiana, S. Pd.            |
| `assets/img/pengajar/ust-nurwidi-sasongko.jpg`  | USt. Nurwidi Sasongko, S. Pd.        |
| `assets/img/pengajar/ust-nur-wahyudi.jpg`       | USt. Nur Wahyudi, S. Pd.             |

> Catatan: file pengajar juga dipakai di bagan struktur organisasi
> (kh-ahmad-fauzi.jpg sebagai mudir, tiga lainnya sebagai level 2).

### 2c. Foto Fasilitas (6 foto)
Rasio landscape (~800 × 480 px).

| File                                            | Label               |
|-------------------------------------------------|---------------------|
| `assets/img/fasilitas/masjid.jpg`               | Masjid Ash-Shiddiq  |
| `assets/img/fasilitas/asrama.jpg`               | Asrama Santri       |
| `assets/img/fasilitas/perpustakaan.jpg`         | Perpustakaan        |
| `assets/img/fasilitas/ruang-kelas.jpg`          | Ruang Kelas Modern  |
| `assets/img/fasilitas/lab-komputer.jpg`         | Lab Komputer        |
| `assets/img/fasilitas/klinik.jpg`               | Klinik Kesehatan    |

---

## 3. Favicon & Social Share (opsional tapi direkomendasikan)

| File                                 | Keterangan                                     |
|--------------------------------------|------------------------------------------------|
| `assets/img/favicon.svg`             | Logo kecil di tab browser (format SVG)         |
| `assets/img/apple-touch-icon.png`    | Icon 180×180 untuk iOS home-screen             |
| `assets/img/og-default.jpg`          | Preview saat link dibagikan ke WA/FB (1200×630)|

---

## 4. Artikel (`/artikel` & detail)

Gambar artikel **TIDAK** diatur di folder ini — diupload lewat panel admin,
disimpan otomatis di `/uploads/artikel/` dengan nama acak aman.

---

## Ringkasan jumlah gambar yang bisa diganti

- Beranda: **10 gambar** (1 hero + 1 mudir + 5 galeri + 3 testimoni)
- Profil: **13 gambar** (3 identitas + 4 pengajar + 6 fasilitas)
- Favicon: **3 gambar** (favicon + apple + og)

**Total: 26 slot foto statis**.
