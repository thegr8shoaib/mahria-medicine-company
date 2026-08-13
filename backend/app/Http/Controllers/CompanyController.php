<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::with('distributor:id,name')->withCount('products');

        if ($request->filled('distributor_id')) {
            $query->where('distributor_id', $request->integer('distributor_id'));
        }

        $companies = $query->orderBy('name')->get();

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'distributor_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $company = Company::create($validated);

        return response()->json(['message' => 'Company created.', 'company' => $company->load('distributor:id,name')], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'distributor_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $company->update($validated);

        return response()->json(['message' => 'Company updated.', 'company' => $company->fresh()->load('distributor:id,name')]);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(['message' => 'Company deleted.']);
    }
}