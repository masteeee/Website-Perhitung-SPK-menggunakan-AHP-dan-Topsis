-- SQL schema generated from design

CREATE TABLE admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_admin VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE siswa (
    id_siswa INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    nama_siswa VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL,
    jenis_kelamin VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kriteria (
    id_kriteria INT AUTO_INCREMENT PRIMARY KEY,
    kode_kriteria VARCHAR(10) NOT NULL,
    nama_kriteria VARCHAR(100) NOT NULL,
    atribut VARCHAR(20) NOT NULL -- 'Benefit' or 'Cost'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE penilaian (
    id_penilaian INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_kriteria INT NOT NULL,
    nilai DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_kriteria) REFERENCES kriteria(id_kriteria) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE hasil_perankingan (
    id_hasil INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    nilai_preferensi DECIMAL(10,4) NOT NULL,
    peringkat INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
