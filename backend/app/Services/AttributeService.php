<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AttributeService
{
    /**
     * Get all attributes with optional filters
     */
    public function getAll(array $filters = [])
    {
        $query = Attribute::with(['values' => function ($q) {
            $q->where('is_active', true)->orderBy('order');
        }]);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['variant_only']) && $filters['variant_only']) {
            $query->where('is_variant_attribute', true);
        }

        return $query->orderBy('order')->get();
    }

    /**
     * Create a new attribute
     */
    public function create(array $data): Attribute
    {
        Log::info('Creating new attribute', ['name' => $data['name']]);

        DB::beginTransaction();

        try {
            $values = $data['values'] ?? [];
            unset($data['values']);

            $attribute = Attribute::create($data);

            // Create initial values if provided
            if (!empty($values)) {
                foreach ($values as $valueData) {
                    $attribute->values()->create($valueData);
                }
                $attribute->load('values');
            }

            DB::commit();

            Log::info('Attribute created successfully', [
                'attribute_id' => $attribute->id,
                'name' => $attribute->name,
                'values_count' => count($values),
            ]);

            return $attribute;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create attribute', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an attribute
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        Log::info('Updating attribute', [
            'attribute_id' => $attribute->id,
            'changes' => array_keys($data),
        ]);

        $attribute->update($data);

        Log::info('Attribute updated successfully', ['attribute_id' => $attribute->id]);

        return $attribute->fresh();
    }

    /**
     * Delete an attribute
     */
    public function delete(Attribute $attribute): bool
    {
        Log::info('Deleting attribute', [
            'attribute_id' => $attribute->id,
            'name' => $attribute->name,
        ]);

        // Check if attribute is used by products
        if ($attribute->products()->count() > 0) {
            throw new BusinessException('Cannot delete attribute that is assigned to products.', 409);
        }

        // Check if attribute is used by categories
        if ($attribute->categories()->count() > 0) {
            throw new BusinessException('Cannot delete attribute that is assigned to categories.', 409);
        }

        $result = $attribute->delete();

        Log::info('Attribute deleted successfully', ['attribute_id' => $attribute->id]);

        return $result;
    }

    /**
     * Add values to an attribute
     */
    public function addValues(Attribute $attribute, array $values): array
    {
        Log::info('Adding values to attribute', [
            'attribute_id' => $attribute->id,
            'values_count' => count($values),
        ]);

        $createdValues = [];

        foreach ($values as $valueData) {
            // Check if value already exists
            $existing = $attribute->values()->where('value', $valueData['value'])->first();
            if (!$existing) {
                $createdValues[] = $attribute->values()->create($valueData);
            }
        }

        Log::info('Values added to attribute', [
            'attribute_id' => $attribute->id,
            'created_count' => count($createdValues),
        ]);

        return $createdValues;
    }

    /**
     * Update an attribute value
     */
    public function updateValue(Attribute $attribute, AttributeValue $value, array $data): AttributeValue
    {
        if ($value->attribute_id !== $attribute->id) {
            throw new BusinessException('Value does not belong to this attribute', 400);
        }

        $value->update($data);

        return $value->fresh();
    }

    /**
     * Delete an attribute value
     */
    public function deleteValue(Attribute $attribute, AttributeValue $value): bool
    {
        if ($value->attribute_id !== $attribute->id) {
            throw new BusinessException('Value does not belong to this attribute', 400);
        }

        Log::info('Deleting attribute value', [
            'attribute_id' => $attribute->id,
            'value_id' => $value->id,
            'value' => $value->value,
        ]);

        return $value->delete();
    }
}
