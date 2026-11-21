<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\MataKuliah;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Dosen Pengampu Default
        $dosen = User::updateOrCreate(
            ['email' => 'dosen@celoe.test'],
            [
                'name' => 'Dr. Ir. Budi Santoso, M.T.',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'nomor_induk' => '198501012015041001',
            ]
        );

        // 2. Akun Mahasiswa Default
        $mahasiswa1 = User::updateOrCreate(
            ['email' => 'mahasiswa@celoe.test'],
            [
                'name' => 'Ahmad Pratama',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210001',
            ]
        );

        // 3. Mata Kuliah Dasar (Modul 3)
        $course1 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF301'],
            [
                'nama_mk' => 'Pengembangan Aplikasi Website',
                'deskripsi' => 'Mata kuliah yang membahas arsitektur aplikasi web modern menggunakan framework Laravel, MVC, Eloquent ORM, dan keamanan web.',
                'sks' => 4,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 4. Daftarkan Mahasiswa ke Mata Kuliah Pertama (status approved)
        $course1->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
        ]);

        // 5. Tambahkan Materi Contoh
        Material::updateOrCreate(
            ['course_id' => $course1->id, 'minggu_ke' => 1, 'urutan' => 1],
            [
                'judul' => 'Pengenalan Arsitektur MVC & Routing di Laravel 12',
                'konten' => 'Mempelajari alur HTTP Request, Route Middleware, Controller, dan pembuatan View menggunakan Blade Template.',
            ]
        );

        // Modul 4: Akun mahasiswa tambahan
        $mahasiswa2 = User::updateOrCreate(
            ['email' => 'siti@celoe.test'],
            [
                'name' => 'Siti Nurhaliza',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210002',
            ]
        );

        // Modul 4: Tambahkan dua mata kuliah dengan SKS dan semester berbeda
        $course2 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF204'],
            [
                'nama_mk' => 'Basis Data Relasional',
                'deskripsi' => 'Mempelajari konsep basis data, relasi tabel, dan SQL.',
                'sks' => 3,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        $course3 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF402'],
            [
                'nama_mk' => 'Analisis Sistem Informasi',
                'deskripsi' => 'Menganalisis kebutuhan sistem dan proses bisnis.',
                'sks' => 2,
                'semester' => 'Genap 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // Supaya kedua mahasiswa dapat melihat konten mata kuliah
        $course1->students()->syncWithoutDetaching([
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        $course2->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        $course3->students()->syncWithoutDetaching([
            $mahasiswa2->id => ['status' => 'approved'],
        ]);

        // Modul 4: Minimal dua tugas pada mata kuliah
        $assignment1 = Assignment::updateOrCreate(
            ['course_id' => $course1->id, 'judul' => 'Tugas 1: Implementasi Middleware & Eloquent ORM'],
            [
                'deskripsi' => 'Jelaskan cara kerja middleware dan praktik penerapan Eloquent pada kelas CeLOE LMS.',
                'deadline' => now()->addDays(14),
            ]
        );

        Assignment::updateOrCreate(
            ['course_id' => $course2->id, 'judul' => 'Tugas 2: Query Basis Data Relasional'],
            [
                'deskripsi' => 'Tuliskan contoh query SELECT, JOIN, dan pengurutan hasil query.',
                'deadline' => now()->addDays(21),
            ]
        );

        // Modul 4: Contoh jawaban pertama, telah dinilai (tampil di sisi Mahasiswa)
        Submission::updateOrCreate(
            ['assignment_id' => $assignment1->id, 'user_id' => $mahasiswa1->id],
            [
                'jawaban' => 'Middleware Laravel berfungsi menyaring HTTP request sebelum mencapai controller. Eloquent ORM digunakan untuk membaca dan mengelola data melalui model.',
                'submitted_at' => now()->subHours(3),
                'nilai' => 88,
                'catatan_dosen' => 'Penjelasan sudah baik. Tambahkan contoh implementasi middleware pada route.',
            ]
        );

        // Jawaban kedua belum dinilai, supaya form Grading bisa diuji
        Submission::updateOrCreate(
            ['assignment_id' => $assignment1->id, 'user_id' => $mahasiswa2->id],
            [
                'jawaban' => 'Middleware melindungi halaman dari akses yang tidak sesuai. Eloquent membantu query database menggunakan Model dan relasi.',
                'submitted_at' => now()->subHours(2),
                'nilai' => null,
                'catatan_dosen' => null,
            ]
        );
    }
}