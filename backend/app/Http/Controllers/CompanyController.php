<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    protected CompanyService $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    /**
     * Display a listing of companies
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'country']);
        $perPage = $request->get('per_page', 15);

        $companies = $this->companyService->getCompanies($filters, $perPage);

        return response()->json($companies);
    }

    /**
     * Store a newly created company
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50|unique:companies,tax_id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'base_currency' => 'nullable|string|size:3',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'timezone' => 'nullable|string|max:50',
            'fiscal_year_start' => 'nullable|date',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $company = $this->companyService->create($validated);

        return response()->json([
            'message' => 'Company created successfully',
            'data' => $company,
        ], 201);
    }

    /**
     * Display the specified company
     */
    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $this->companyService->getCompany($company),
        ]);
    }

    /**
     * Update the specified company
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:companies,name,' . $company->id,
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50|unique:companies,tax_id,' . $company->id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'base_currency' => 'nullable|string|size:3',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'timezone' => 'nullable|string|max:50',
            'fiscal_year_start' => 'nullable|date',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $company = $this->companyService->update($company, $validated);

        return response()->json([
            'message' => 'Company updated successfully',
            'data' => $company,
        ]);
    }

    /**
     * Remove the specified company (soft delete)
     */
    public function destroy(Company $company): JsonResponse
    {
        $this->companyService->delete($company);

        return response()->json([
            'message' => 'Company deleted successfully',
        ]);
    }

    /**
     * Restore a soft-deleted company
     */
    public function restore(int $id): JsonResponse
    {
        $company = $this->companyService->restore($id);

        return response()->json([
            'message' => 'Company restored successfully',
            'data' => $company,
        ]);
    }

    /**
     * Toggle company active status
     */
    public function toggleActive(Company $company): JsonResponse
    {
        $company = $this->companyService->toggleActive($company);

        return response()->json([
            'message' => 'Company status updated successfully',
            'data' => $company,
        ]);
    }
}
