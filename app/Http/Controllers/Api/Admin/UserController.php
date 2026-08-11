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
        $users = User::where('role', 'pelapor')
            ->withCount('reports')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('reports')->findOrFail($id);

        return response()->json($user);
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
