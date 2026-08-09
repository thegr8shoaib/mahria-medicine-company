<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role', 'permissions', 'created_at']);

        return response()->json($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'cashier',
            'permissions' => $data['role'] === 'admin' ? null : ($data['permissions'] ?? []),
        ]);

        return response()->json(['message' => 'User created.', 'user' => $user->only(['id', 'name', 'email', 'role', 'permissions', 'created_at'])], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['sometimes', 'in:admin,cashier'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:sales,inventory,purchases,customers'],
        ]);

        $data = collect($validated)->filter(fn ($v) => $v !== null && $v !== '')->all();

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        if ($user->id === $request->user()->id && isset($data['role']) && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        if (isset($data['role']) && $data['role'] === 'admin') {
            $data['permissions'] = null;
        }

        $user->update($data);

        return response()->json(['message' => 'User updated.', 'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'permissions', 'created_at'])]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}