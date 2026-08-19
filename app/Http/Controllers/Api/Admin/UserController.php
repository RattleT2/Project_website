<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $perPage = min((int) request()->input('per_page', 20), 100);

        $query = User::where('role', 'pelapor')
            ->withCount('reports')
            ->orderBy('created_at', 'desc');

        $users = $query->paginate($perPage);

        $users->through(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'reports_count' => $user->reports_count ?? 0,
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        // Ambil user dan hitung laporan berdasarkan status untuk ringkasan
        $user = User::withCount([
            'reports as verified_reports_count' => function ($query) {
                $query->where('status', 'disetujui');
            },
            'reports as unverified_reports_count' => function ($query) {
                $query->whereIn('status', ['pending', 'proses']);
            }
        ])->findOrFail($id);

        // Kembalikan id numeric (NIP tidak diperlukan di detail) dan sertakan
        // jumlah laporan terverifikasi / belum terverifikasi di dua lokasi:
        // - top-level fields untuk kompatibilitas frontend
        // - `reports_summary` sebagai ringkasan tersarang
        $verified = $user->verified_reports_count ?? 0;
        $unverified = $user->unverified_reports_count ?? 0;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'verified_reports_count' => $verified,
            'unverified_reports_count' => $unverified,
            'reports_summary' => [
                'verified' => $verified,
                'unverified' => $unverified,
            ],
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status user berhasil diperbarui.',
            'user' => $user,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Tidak dapat menghapus akun admin.'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'Akun pelapor berhasil dihapus.']);
    }
}
