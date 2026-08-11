<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken('pos-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role', 'permissions', 'avatar', 'avatar_url']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->only(['id', 'name', 'email', 'role', 'permissions', 'avatar', 'avatar_url']));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            'avatar' => ['sometimes', 'nullable'],
        ]);

        $data = collect($validated)->filter(fn ($v) => $v !== null && $v !== '')->all();

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect.'], 422);
            }
            $data['password'] = bcrypt($data['password']);
        }

        unset($data['current_password'], $data['password_confirmation']);

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048']]);
            $data['avatar'] = $this->storeAvatar($request->file('avatar'), $user);
        } elseif (array_key_exists('avatar', $validated) && ($validated['avatar'] === '' || $validated['avatar'] === null)) {
            $data['avatar'] = null;
            $this->deleteAvatar($user);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'permissions', 'avatar', 'avatar_url']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public static function storeAvatar($file, User $user): string
    {
        $dir = storage_path('app/avatars');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $name = 'u' . $user->id . '-' . time() . '.' . $ext;
        $tmp = $file->getRealPath();

        if (! @rename($tmp, $dir . '/' . $name)) {
            if (! copy($tmp, $dir . '/' . $name)) {
                throw new \RuntimeException('Could not store the avatar file.');
            }
            @unlink($tmp);
        }

        if ($user->avatar && is_file($dir . '/' . $user->avatar)) {
            @unlink($dir . '/' . $user->avatar);
        }

        return $name;
    }

    public static function deleteAvatar(User $user): void
    {
        if ($user->avatar && is_file(storage_path('app/avatars/' . $user->avatar))) {
            @unlink(storage_path('app/avatars/' . $user->avatar));
        }
    }
}