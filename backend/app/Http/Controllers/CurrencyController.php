<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrencyResource;
use App\Http\Resources\ExchangeRateResource;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Display a listing of currencies
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Currency::query();

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $currencies = $query->orderBy('code')->get();

        return CurrencyResource::collection($currencies);
    }

    /**
     * Get active currencies (for dropdowns)
     */
    public function active(): AnonymousResourceCollection
    {
        $currencies = $this->currencyService->getActiveCurrencies();

        return CurrencyResource::collection($currencies);
    }

    /**
     * Store a newly created currency
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:10',
            'decimal_places' => 'integer|min:0|max:4',
            'decimal_separator' => 'string|max:1',
            'thousands_separator' => 'nullable|string|max:1',
            'symbol_first' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $currency = $this->currencyService->createCurrency($validated);

        return (new CurrencyResource($currency))
            ->additional(['message' => 'Currency created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified currency
     */
    public function show(Currency $currency): JsonResource
    {
        return new CurrencyResource($currency);
    }

    /**
     * Update the specified currency
     */
    public function update(Request $request, Currency $currency): JsonResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'symbol' => 'sometimes|required|string|max:10',
            'decimal_places' => 'integer|min:0|max:4',
            'decimal_separator' => 'string|max:1',
            'thousands_separator' => 'nullable|string|max:1',
            'symbol_first' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $currency = $this->currencyService->updateCurrency($currency, $validated);

        return (new CurrencyResource($currency))
            ->additional(['message' => 'Currency updated successfully']);
    }

    /**
     * Remove the specified currency
     */
    public function destroy(Currency $currency): JsonResponse
    {
        $this->currencyService->deleteCurrency($currency);

        return response()->json([
            'message' => 'Currency deleted successfully',
        ]);
    }

    /**
     * Toggle currency active status
     */
    public function toggleActive(Currency $currency): JsonResource
    {
        $currency->update(['is_active' => !$currency->is_active]);

        return (new CurrencyResource($currency->fresh()))
            ->additional(['message' => 'Currency status updated successfully']);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3|exists:currencies,code',
            'to' => 'required|string|size:3|exists:currencies,code',
            'date' => 'nullable|date',
        ]);

        $rate = $this->currencyService->getExchangeRate(
            strtoupper($validated['from']),
            strtoupper($validated['to']),
            $validated['date'] ?? null
        );

        if ($rate === null) {
            return response()->json([
                'message' => 'Exchange rate not found for this currency pair',
            ], 404);
        }

        return response()->json([
            'data' => [
                'from' => strtoupper($validated['from']),
                'to' => strtoupper($validated['to']),
                'rate' => $rate,
                'date' => $validated['date'] ?? now()->toDateString(),
            ],
        ]);
    }

    /**
     * Set exchange rate between two currencies
     */
    public function setExchangeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3|exists:currencies,code',
            'to' => 'required|string|size:3|exists:currencies,code',
            'rate' => 'required|numeric|min:0',
            'date' => 'nullable|date',
            'source' => 'nullable|string|max:50',
        ]);

        $exchangeRate = $this->currencyService->setExchangeRate(
            strtoupper($validated['from']),
            strtoupper($validated['to']),
            $validated['rate'],
            $validated['date'] ?? null,
            $validated['source'] ?? 'manual'
        );

        return (new ExchangeRateResource($exchangeRate))
            ->additional(['message' => 'Exchange rate set successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get exchange rate history
     */
    public function exchangeRateHistory(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3|exists:currencies,code',
            'to' => 'required|string|size:3|exists:currencies,code',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $history = $this->currencyService->getExchangeRateHistory(
            strtoupper($validated['from']),
            strtoupper($validated['to']),
            $validated['days'] ?? 30
        );

        return ExchangeRateResource::collection($history);
    }

    /**
     * Convert amount between currencies
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'from' => 'required|string|size:3|exists:currencies,code',
            'to' => 'required|string|size:3|exists:currencies,code',
            'date' => 'nullable|date',
        ]);

        $from = strtoupper($validated['from']);
        $to = strtoupper($validated['to']);

        $convertedAmount = $this->currencyService->convert(
            $validated['amount'],
            $from,
            $to,
            $validated['date'] ?? null
        );

        if ($convertedAmount === null) {
            return response()->json([
                'message' => 'Cannot convert: exchange rate not found for this currency pair',
            ], 422);
        }

        return response()->json([
            'data' => [
                'from' => [
                    'amount' => $validated['amount'],
                    'currency' => $from,
                    'formatted' => $this->currencyService->format($validated['amount'], $from),
                ],
                'to' => [
                    'amount' => $convertedAmount,
                    'currency' => $to,
                    'formatted' => $this->currencyService->format($convertedAmount, $to),
                ],
                'rate' => $this->currencyService->getExchangeRate($from, $to, $validated['date'] ?? null),
                'date' => $validated['date'] ?? now()->toDateString(),
            ],
        ]);
    }
}
