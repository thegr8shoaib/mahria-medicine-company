<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::withCount('sales')->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->paginate((int) $request->get('per_page', 15));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::create($validated);

        return response()->json(['message' => 'Customer created.', 'customer' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer->update($validated);

        return response()->json(['message' => 'Customer updated.', 'customer' => $customer->fresh()]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    public function payments(Customer $customer): JsonResponse
    {
        $payments = $customer->payments()
            ->with('user:id,name')
            ->latest()
            ->paginate(15);

        return response()->json([
            'customer' => $customer,
            'payments' => $payments,
        ]);
    }

    public function receivePayment(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $customer->payments()->create([
            'user_id' => $request->user()->id,
            'amount' => round((float) $validated['amount'], 2),
            'note' => $validated['note'] ?? null,
        ]);

        $newCredit = $customer->credit - $payment->amount;
        $customer->update(['credit' => $newCredit]);

        return response()->json([
            'message' => 'Payment received.',
            'payment' => $payment->load('user:id,name'),
            'credit' => $customer->fresh()->credit,
        ], 201);
    }
}