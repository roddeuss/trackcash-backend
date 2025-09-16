<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Update nama & email user.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user->only(['id', 'name', 'email', 'default_currency']),
        ]);
    }

    public function updateProfilePicture(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Hapus foto lama (kalau ada)
        if ($user->profile_picture && file_exists(public_path('profile/' . $user->profile_picture))) {
            unlink(public_path('profile/' . $user->profile_picture));
        }

        // Simpan dengan nama unik
        $file = $request->file('profile_picture');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('profile'), $filename);

        // Update di database
        $user->profile_picture = $filename;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'data' => [
                'profile_picture_url' => url('profile/' . $filename), // url publik
            ],
        ]);
    }

    /**
     * Ganti password (harus masukkan password lama).
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:8', 'confirmed'], // perlu field password_confirmation
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Password lama tidak sesuai',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }

    /**
     * Pilih mata uang default (IDR / USD).
     */
    public function updateCurrency(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'default_currency' => ['required', Rule::in(['IDR', 'USD'])],
        ]);

        $user->default_currency = $validated['default_currency'];
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Mata uang berhasil diperbarui',
            'data' => ['default_currency' => $user->default_currency],
        ]);
    }

    /**
     * (Opsional) Ambil profil saat ini.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data' => $user->only(['id', 'name', 'email', 'default_currency', 'profile_picture_url']),
        ]);
    }
}
