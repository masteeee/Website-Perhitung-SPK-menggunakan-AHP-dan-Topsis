Buatkan Saya Sebuah website Sistem Pendukung Keputusan penentuan penerimaan beasiswa bantuan dana pendidikan menggunakan metode ahp dan topsis
Buatkan Menggunakan PHP, HTML, Css, Javascript dan Database Menggunakan MySql

Berikut merupakan rancangan Interface dan database nya, sesuaikan semua nya agar semunya saling keterkaitan dan berfungsi dengan baik

## 3.6 Perancangan Interface

Perancangan interface merupakan tahap perancangan tampilan antarmuka yang digunakan sebagai penghubung antara pengguna dengan sistem pendukung keputusan penentuan penerima beasiswa bantuan dana pendidikan. Perancangan ini bertujuan untuk menghasilkan tampilan sistem yang mudah dipahami, memiliki susunan informasi yang jelas, serta memudahkan admin dalam melakukan pengelolaan data dan menjalankan proses perhitungan menggunakan metode AHP dan TOPSIS.

Antarmuka sistem dirancang berbasis website dengan menggunakan HTML, CSS, JavaScript, PHP, dan MySQL sebagai basis data. Sistem ini memiliki satu aktor utama, yaitu admin atau pihak administrasi yang bertanggung jawab untuk mengelola data siswa, mengelola kriteria, memasukkan nilai penilaian, melakukan perhitungan AHP dan TOPSIS, serta melihat hasil perangkingan calon penerima beasiswa.

Perancangan interface pada penelitian ini terdiri atas rancangan halaman login, dashboard, data siswa, data kriteria, data penilaian, perhitungan AHP, perhitungan TOPSIS, dan hasil perangkingan. Setiap halaman dirancang berdasarkan kebutuhan fungsional sistem yang telah dijelaskan pada analisis dan perancangan sebelumnya.

### 3.6.1 Rancangan Halaman Login

Halaman login merupakan halaman awal yang ditampilkan ketika admin mengakses sistem. Halaman ini digunakan sebagai mekanisme autentikasi untuk memastikan bahwa hanya pengguna yang memiliki akun terdaftar yang dapat masuk dan mengakses fitur pengelolaan sistem.

Pada halaman login, pengguna diminta memasukkan username dan password yang telah terdaftar. Sistem akan melakukan pemeriksaan terhadap data yang dimasukkan dengan data akun yang tersimpan dalam basis data. Apabila data yang dimasukkan sesuai, pengguna akan diarahkan ke halaman dashboard. Sebaliknya, apabila data tidak sesuai, sistem akan menampilkan pesan kesalahan dan pengguna diminta untuk memasukkan kembali data login.

Komponen yang terdapat pada halaman login meliputi:

1. Nama atau identitas sistem pendukung keputusan penerima beasiswa.
2. Kolom input username.
3. Kolom input password.
4. Tombol login untuk memproses autentikasi pengguna.
5. Pesan pemberitahuan apabila proses login gagal.

Rancangan halaman login bertujuan untuk membatasi akses sistem dan menjaga keamanan data siswa serta hasil perhitungan yang dikelola oleh admin.

**Gambar 3.x Rancangan Halaman Login**

*[Tempatkan gambar rancangan antarmuka halaman login di sini.]*

### 3.6.2 Rancangan Halaman Dashboard

Halaman dashboard merupakan halaman utama yang ditampilkan setelah admin berhasil melakukan login. Halaman ini berfungsi sebagai pusat navigasi yang memberikan gambaran umum mengenai sistem dan menyediakan akses menuju fitur-fitur utama.

Dashboard dirancang dengan menampilkan menu navigasi yang terdiri atas dashboard, data siswa, data kriteria, data penilaian, perhitungan AHP, perhitungan TOPSIS, hasil perangkingan, dan logout. Selain itu, dashboard dapat menampilkan ringkasan jumlah data siswa, jumlah kriteria, serta informasi mengenai proses pengolahan data yang tersedia di dalam sistem.

Komponen yang terdapat pada halaman dashboard meliputi:

1. Sidebar sebagai menu navigasi utama.
2. Header yang menampilkan nama sistem dan informasi akun admin.
3. Ringkasan jumlah siswa yang telah terdaftar.
4. Ringkasan jumlah kriteria yang digunakan.
5. Informasi akses cepat menuju pengelolaan data dan perhitungan metode.
6. Tombol logout untuk mengakhiri sesi pengguna.

Melalui halaman dashboard, admin dapat mengakses seluruh fungsi sistem secara terstruktur tanpa harus kembali ke halaman login selama sesi pengguna masih aktif.

**Gambar 3.x Rancangan Halaman Dashboard**

*[Tempatkan gambar rancangan antarmuka halaman dashboard di sini.]*

### 3.6.3 Rancangan Halaman Data Siswa

Halaman data siswa digunakan untuk mengelola informasi siswa yang menjadi alternatif dalam proses penentuan penerima beasiswa bantuan dana pendidikan. Data siswa yang tersimpan di dalam sistem akan digunakan sebagai dasar untuk memasukkan nilai kriteria dan menjalankan perhitungan TOPSIS.

Halaman ini menampilkan data siswa dalam bentuk tabel sehingga admin dapat melihat dan memeriksa informasi yang telah tersimpan. Data yang digunakan dalam sistem disesuaikan dengan kebutuhan penelitian, meliputi NISN, nama siswa, kelas, jenis kelamin, dan alamat. Informasi lain dari sumber data sekolah dapat disimpan sesuai kebutuhan sistem, tetapi tidak seluruh data identitas harus ditampilkan pada halaman pengolahan beasiswa.

Fitur yang direncanakan pada halaman data siswa meliputi:

1. Tombol tambah data siswa.
2. Tabel daftar siswa yang telah terdaftar.
3. Fitur pencarian siswa berdasarkan identitas yang relevan.
4. Tombol edit untuk memperbarui data siswa.
5. Tombol hapus untuk menghapus data siswa apabila diperlukan.
6. Formulir untuk memasukkan dan memperbarui informasi siswa.

Halaman ini diharapkan dapat membantu admin dalam mengelola data alternatif secara teratur. Data siswa yang telah dimasukkan selanjutnya dapat digunakan pada halaman penilaian untuk menentukan nilai setiap kriteria.

**Gambar 3.x Rancangan Halaman Data Siswa**

*[Tempatkan gambar rancangan antarmuka halaman data siswa di sini.]*

### 3.6.4 Rancangan Halaman Data Kriteria

Halaman data kriteria digunakan untuk mengelola kriteria yang menjadi dasar penilaian calon penerima beasiswa. Kriteria yang digunakan dalam penelitian ini terdiri atas penghasilan orang tua, jumlah tanggungan orang tua, dan nilai rata-rata siswa.

Halaman ini dirancang dalam bentuk tabel yang menampilkan kode kriteria, nama kriteria, dan jenis atribut. Jenis atribut digunakan untuk membedakan kriteria *Cost* dan *Benefit* dalam proses perhitungan TOPSIS.

Adapun kriteria yang digunakan adalah sebagai berikut:

1. C1 – Penghasilan Orang Tua dengan atribut *Cost*.
2. C2 – Jumlah Tanggungan Orang Tua dengan atribut *Benefit*.
3. C3 – Nilai Rata-rata Siswa dengan atribut *Benefit*.

Fitur yang direncanakan pada halaman data kriteria meliputi:

1. Tabel daftar kriteria yang digunakan dalam sistem.
2. Formulir tambah kriteria apabila diperlukan.
3. Fitur edit untuk memperbarui informasi kriteria.
4. Fitur hapus dengan pemeriksaan keterkaitan data agar tidak mengganggu hasil penilaian yang sudah tersimpan.
5. Informasi jenis atribut setiap kriteria.

Data kriteria menjadi acuan dalam proses perhitungan AHP untuk memperoleh bobot prioritas. Bobot yang dihasilkan selanjutnya digunakan dalam proses perhitungan TOPSIS. Oleh karena itu, perubahan kriteria perlu dikelola secara hati-hati agar tidak menyebabkan ketidaksesuaian antara data penilaian dan hasil perhitungan.

**Gambar 3.x Rancangan Halaman Data Kriteria**

*[Tempatkan gambar rancangan antarmuka halaman data kriteria di sini.]*

### 3.6.5 Rancangan Halaman Data Penilaian

Halaman data penilaian digunakan untuk memasukkan dan mengelola nilai setiap siswa berdasarkan kriteria yang telah ditentukan. Nilai yang dimasukkan pada halaman ini menjadi matriks keputusan yang digunakan dalam perhitungan metode TOPSIS.

Setiap siswa memiliki nilai penghasilan orang tua, jumlah tanggungan orang tua, dan nilai rata-rata siswa. Penghasilan orang tua dimasukkan dalam bentuk nominal rupiah, jumlah tanggungan dinyatakan dalam jumlah orang, sedangkan nilai rata-rata siswa menggunakan skala nilai akademik yang berlaku di sekolah.

Halaman ini dirancang dengan menampilkan tabel penilaian yang menghubungkan data siswa dengan nilai ketiga kriteria. Admin dapat memilih siswa yang akan dinilai, memasukkan nilai kriteria, kemudian menyimpan data penilaian ke dalam basis data.

Fitur yang direncanakan pada halaman data penilaian meliputi:

1. Pemilihan siswa yang akan diberikan penilaian.
2. Kolom input penghasilan orang tua.
3. Kolom input jumlah tanggungan orang tua.
4. Kolom input nilai rata-rata siswa.
5. Tombol simpan untuk menyimpan penilaian.
6. Tombol edit untuk memperbarui nilai yang telah dimasukkan.
7. Tabel daftar penilaian yang telah tersimpan.
8. Validasi input untuk memastikan nilai yang dimasukkan sesuai dengan jenis data masing-masing kriteria.

Data penilaian harus diperiksa sebelum digunakan dalam perhitungan. Pemeriksaan tersebut bertujuan untuk mengurangi kesalahan input dan memastikan setiap alternatif memiliki nilai yang diperlukan. Apabila terdapat data yang belum lengkap, sistem perlu memberikan pemberitahuan agar admin dapat melengkapinya sebelum proses perhitungan dijalankan.

**Gambar 3.x Rancangan Halaman Data Penilaian**

*[Tempatkan gambar rancangan antarmuka halaman data penilaian di sini.]*

### 3.6.6 Rancangan Halaman Perhitungan AHP

Halaman perhitungan AHP digunakan untuk melakukan perhitungan bobot prioritas kriteria berdasarkan matriks perbandingan berpasangan. Proses ini bertujuan untuk menentukan tingkat kepentingan relatif dari setiap kriteria yang digunakan dalam penentuan penerima beasiswa.

Halaman perhitungan AHP dirancang untuk menampilkan matriks perbandingan berpasangan antar-kriteria, hasil normalisasi matriks, bobot prioritas, nilai lambda maksimum, *Consistency Index* (CI), dan *Consistency Ratio* (CR). Informasi tersebut diperlukan untuk mengetahui bobot kriteria dan memeriksa konsistensi penilaian perbandingan yang digunakan.

Tahapan perhitungan yang ditampilkan pada halaman ini meliputi:

1. Menampilkan matriks perbandingan berpasangan antar-kriteria.
2. Menghitung jumlah setiap kolom matriks.
3. Menampilkan hasil normalisasi matriks.
4. Menghitung bobot prioritas setiap kriteria.
5. Menghitung nilai lambda maksimum.
6. Menghitung nilai CI dan CR untuk menguji konsistensi matriks.
7. Menampilkan hasil bobot kriteria yang akan digunakan dalam perhitungan TOPSIS.

Halaman ini juga dirancang untuk memberikan informasi apabila nilai CR memenuhi batas konsistensi yang digunakan dalam penelitian. Jika matriks perbandingan belum konsisten, penilaian perlu ditinjau kembali sebelum bobot digunakan pada proses TOPSIS.

Bobot yang ditampilkan pada rancangan perhitungan AHP selanjutnya digunakan oleh sistem dalam menghitung nilai alternatif pada metode TOPSIS. Dengan demikian, perhitungan AHP dan TOPSIS memiliki keterkaitan dalam proses penentuan peringkat calon penerima beasiswa.

**Gambar 3.x Rancangan Halaman Perhitungan AHP**

*[Tempatkan gambar rancangan antarmuka halaman perhitungan AHP di sini.]*

### 3.6.7 Rancangan Halaman Perhitungan TOPSIS

Halaman perhitungan TOPSIS digunakan untuk menjalankan proses perhitungan nilai preferensi setiap alternatif siswa berdasarkan data penilaian dan bobot kriteria yang diperoleh dari metode AHP. Halaman ini menjadi bagian utama dalam proses pengolahan data untuk menghasilkan peringkat calon penerima beasiswa.

Pada halaman ini, sistem dirancang untuk menampilkan tahapan perhitungan TOPSIS secara terstruktur agar proses pengolahan nilai dapat diperiksa oleh admin. Tahapan tersebut meliputi pembentukan matriks keputusan, normalisasi matriks keputusan, pembentukan matriks ternormalisasi terbobot, penentuan solusi ideal positif dan negatif, perhitungan jarak terhadap solusi ideal, serta perhitungan nilai preferensi.

Fitur yang direncanakan pada halaman perhitungan TOPSIS meliputi:

1. Tombol untuk menjalankan proses perhitungan TOPSIS.
2. Tabel matriks keputusan berdasarkan data penilaian siswa.
3. Tabel hasil normalisasi matriks keputusan.
4. Tabel matriks keputusan ternormalisasi terbobot.
5. Informasi solusi ideal positif dan solusi ideal negatif.
6. Tabel jarak setiap alternatif terhadap solusi ideal positif dan negatif.
7. Tabel nilai preferensi setiap alternatif.
8. Informasi status proses perhitungan apabila berhasil atau terdapat data yang belum lengkap.

Sistem menggunakan bobot kriteria yang dihasilkan dari proses AHP sebagai dasar pembobotan pada perhitungan TOPSIS. Hasil perhitungan TOPSIS kemudian digunakan untuk menentukan urutan peringkat setiap alternatif siswa berdasarkan nilai preferensi yang diperoleh.

Halaman ini diharapkan dapat membantu admin memahami proses perhitungan secara sistematis serta mempermudah pemeriksaan terhadap nilai yang dihasilkan oleh sistem.

**Gambar 3.x Rancangan Halaman Perhitungan TOPSIS**

*[Tempatkan gambar rancangan antarmuka halaman perhitungan TOPSIS di sini.]*

### 3.6.8 Rancangan Halaman Hasil Perangkingan

Halaman hasil perangkingan digunakan untuk menampilkan hasil akhir pengolahan data calon penerima beasiswa menggunakan metode AHP dan TOPSIS. Hasil yang ditampilkan berupa daftar alternatif siswa yang telah diurutkan berdasarkan nilai preferensi dari yang tertinggi hingga terendah.

Halaman ini dirancang dalam bentuk tabel yang memuat nomor peringkat, identitas siswa yang diperlukan, nilai preferensi, dan keterangan hasil perangkingan. Admin dapat menggunakan informasi tersebut untuk mengetahui urutan prioritas calon penerima beasiswa berdasarkan kriteria dan bobot yang telah ditetapkan.

Komponen yang direncanakan pada halaman hasil perangkingan meliputi:

1. Tabel daftar alternatif siswa berdasarkan urutan peringkat.
2. Kolom nomor peringkat.
3. Kolom NISN atau identitas siswa yang relevan.
4. Kolom nama siswa.
5. Kolom nilai preferensi TOPSIS.
6. Kolom keterangan hasil perangkingan.
7. Fitur pencarian atau penyaringan hasil apabila diperlukan.
8. Fitur cetak atau ekspor laporan apabila diimplementasikan dalam aplikasi.

Hasil perangkingan dapat digunakan sebagai bahan pertimbangan bagi pihak sekolah dalam proses penentuan penerima beasiswa bantuan dana pendidikan. Meskipun sistem memberikan urutan prioritas berdasarkan perhitungan matematis, keputusan akhir mengenai penerima beasiswa tetap berada pada pihak sekolah sesuai dengan kebijakan, kuota, dan hasil verifikasi yang berlaku.

Dengan adanya halaman hasil perangkingan, proses penyajian hasil perhitungan diharapkan menjadi lebih terstruktur, mudah diperiksa, dan membantu meningkatkan transparansi proses pendukung keputusan.

**Gambar 3.x Rancangan Halaman Hasil Perangkingan**

*[Tempatkan gambar rancangan antarmuka halaman hasil perangkingan di sini.]*

Berdasarkan rancangan interface yang telah dijelaskan, sistem pendukung keputusan yang dikembangkan diharapkan dapat menyediakan antarmuka yang memudahkan admin dalam mengelola data siswa, mengatur kriteria, memasukkan penilaian, menjalankan perhitungan AHP dan TOPSIS, serta melihat hasil perangkingan calon penerima beasiswa. Rancangan ini menjadi acuan dalam tahap implementasi sistem yang akan dibahas pada Bab IV.


## Rancangan Database

1. Tabel Admin
Digunakan untuk menyimpan akun pengguna yang memiliki hak akses masuk (login) ke dalam sistem web SPK.
CREATE TABLE admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_admin VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

2. Tabel Siswa 
Menyimpan data identitas siswa/alternatif yang menjadi calon penerima beasiswa bantuan dana pendidikan.
CREATE TABLE siswa (
    id_siswa INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    nama_siswa VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL,
    jenis_kelamin VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

3. Tabel Kriteria
Menyimpan daftar kriteria penilaian (misal: C1 = Penghasilan Orang Tua, C2 = Nilai Rapor, dll) lengkap dengan atributnya (Benefit atau Cost).
CREATE TABLE kriteria (
    id_kriteria INT AUTO_INCREMENT PRIMARY KEY,
    kode_kriteria VARCHAR(10) NOT NULL,
    nama_kriteria VARCHAR(100) NOT NULL,
    atribut VARCHAR(20) NOT NULL -- Berisi 'Benefit' atau 'Cost'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

4. Tabel Penilaian
Tabel relasi antara tabel Siswa dan tabel Kriteria. Tabel ini menampung nilai mentah/inputan dari setiap siswa untuk masing-masing kriteria.
CREATE TABLE penilaian (
    id_penilaian INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_kriteria INT NOT NULL,
    nilai DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_kriteria) REFERENCES kriteria(id_kriteria) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

5. Tabel AHP
Menyimpan hasil perhitungan dari metode AHP, khususnya yang berkaitan dengan perbandingan berpasangan antar kriteria, bobot akhir kriteria (bobot_kriteria), hingga nilai pengujian konsistensinya.
CREATE TABLE ahp (
    id_ahp INT AUTO_INCREMENT PRIMARY KEY,
    id_kriteria INT NOT NULL,
    nilai_perbandingan DECIMAL(10,4) DEFAULT 0.0000,
    bobot_kriteria DECIMAL(10,4) DEFAULT 0.0000,
    lambda_max DECIMAL(10,4) DEFAULT 0.0000,
    ci DECIMAL(10,4) DEFAULT 0.0000,
    cr DECIMAL(10,4) DEFAULT 0.0000,
    FOREIGN KEY (id_kriteria) REFERENCES kriteria(id_kriteria) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

6. Tabel Topsis
Menyimpan langkah-langkah kalkulasi sistem pendukung keputusan metode TOPSIS untuk setiap siswa/alternatif, mulai dari matriks normalisasi, normalisasi terbobot, matriks solusi ideal positif & negatif, hingga jarak pemisahan (D^+ dan D^-).
CREATE TABLE topsis (
    id_topsis INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    nilai_normalisasi DECIMAL(10,4) DEFAULT 0.0000,
    nilai_terbobot DECIMAL(10,4) DEFAULT 0.0000,
    solusi_ideal_positif DECIMAL(10,4) DEFAULT 0.0000,
    solusi_ideal_negatif DECIMAL(10,4) DEFAULT 0.0000,
    jarak_positif DECIMAL(10,4) DEFAULT 0.0000,
    jarak_negatif DECIMAL(10,4) DEFAULT 0.0000,
    nilai_preferensi DECIMAL(10,4) DEFAULT 0.0000,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

7. Tabel Hasil_perankingan
Menyimpan hasil akhir dari pengolahan data sistem. Tabel ini mencatat rekapitulasi nilai preferensi akhir, nomor peringkat (peringkat), serta status kelayakan (misalnya: "Diterima" atau "Tidak Diterima") untuk memudahkan pembuatan laporan.
CREATE TABLE hasil_perankingan (
    id_hasil INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    nilai_preferensi DECIMAL(10,4) NOT NULL,
    peringkat INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


