<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isMahasiswa()) {
            abort(403, 'Akses ditolak. Pengumpulan tugas hanya dapat dilakukan oleh Mahasiswa.');
        }

        $user = Auth::user();

        $enrollment = $assignment->course->students()->where('users.id', $user->id)->first();
        if (!$enrollment || $enrollment->pivot->status !== 'approved') {
            abort(403, 'Akses ditolak. Anda belum disetujui oleh Dosen untuk mengikuti mata kuliah tugas ini.');
        }

        if ($assignment->isOverdue()) {
            return back()->with('error', 'Gagal mengumpulkan: Batas waktu pengumpulan (deadline) tugas telah berakhir.');
        }

        $validated = $request->validate([
            'jawaban' => ['required', 'string', 'min:10', 'max:20000'],
        ], [
            'jawaban.required' => 'Teks jawaban tugas wajib diisi.',
            'jawaban.min' => 'Jawaban terlalu pendek. Tuliskan jawaban minimal 10 karakter.',
            'jawaban.max' => 'Jawaban melebihi batas maksimal 20.000 karakter.',
        ]);

        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'jawaban' => $validated['jawaban'],
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Jawaban tugas Anda berhasil disimpan ke sistem.');
    }

    // Modul 4 - Dosen memberikan nilai dan catatan pada satu submission
    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        if (!Auth::user()->isDosen() || $submission->assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah ini.');
        }

        $validated = $request->validate([
            'nilai' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan_dosen' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update($validated);

        return back()->with('success', 'Nilai dan catatan dosen berhasil disimpan.');
    }

    // Modul 4 - Ekspor nilai seluruh mahasiswa approved pada tugas terkait
    public function exportCsv(Assignment $assignment): StreamedResponse
    {
        if (!Auth::user()->isDosen() || $assignment->course->dosen_id !== Auth::id()) {
            abort(403, 'Akses ditolak. Anda bukan dosen pengampu mata kuliah ini.');
        }

        $assignment->load(['course.approvedStudents', 'submissions.user']);
        $students = $assignment->course->approvedStudents;
        $submissions = $assignment->submissions->keyBy('user_id');
        $filename = 'rekap_nilai_' . $assignment->course->kode_mk . '_tugas_' . $assignment->id . '.csv';

        return response()->streamDownload(function () use ($students, $submissions) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM supaya karakter Indonesia dibaca baik oleh Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'NIM',
                'Nama Mahasiswa',
                'Status Pengumpulan',
                'Waktu Pengumpulan',
                'Nilai',
                'Status Nilai',
                'Catatan Dosen',
            ]);

            foreach ($students as $student) {
                $submission = $submissions->get($student->id);

                fputcsv($handle, [
                    $student->nomor_induk ?? '',
                    $student->name,
                    $submission ? 'Sudah Mengumpulkan' : 'Belum Mengumpulkan',
                    $submission?->submitted_at?->format('Y-m-d H:i:s') ?? '',
                    $submission?->nilai ?? '',
                    $submission?->status_nilai ?? 'Belum Dinilai',
                    $submission?->catatan_dosen ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}