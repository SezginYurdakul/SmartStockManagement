<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Exception;

class ProductImageService
{
    protected $imageManager;

    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * Upload and process multiple images for a product
     */
    public function uploadImages(Product $product, array $files, array $options = []): array
    {
        Log::info('Starting batch image upload', [
            'product_id' => $product->id,
            'image_count' => count($files),
        ]);

        $uploadedImages = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                $uploadedImages[] = $this->uploadSingleImage($product, $file, [
                    'is_primary' => $options['is_primary'][$index] ?? ($index === 0 && $product->images()->count() === 0),
                    'alt_text' => $options['alt_text'][$index] ?? $product->name,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to upload image in batch', [
                    'product_id' => $product->id,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Image {$index}: {$e->getMessage()}";
            }
        }

        if (!empty($errors)) {
            Log::warning('Batch upload completed with errors', [
                'product_id' => $product->id,
                'successful' => count($uploadedImages),
                'failed' => count($errors),
            ]);
        } else {
            Log::info('Batch image upload completed successfully', [
                'product_id' => $product->id,
                'uploaded_count' => count($uploadedImages),
            ]);
        }

        return $uploadedImages;
    }

    /**
     * Upload and process a single image
     */
    public function uploadSingleImage(Product $product, UploadedFile $file, array $options = []): ProductImage
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = 'products/' . $product->id . '/' . $filename;

        Log::debug('Starting single image upload', [
            'product_id' => $product->id,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        DB::beginTransaction();

        try {
            // Resize and optimize image
            $image = $this->imageManager->read($file);
            $image->scaleDown(800, 800);

            // Save to storage
            Storage::disk('public')->put($path, $image->encode());

            Log::debug('Image file saved to storage', [
                'product_id' => $product->id,
                'path' => $path,
            ]);

            // Handle primary image logic
            $isPrimary = $options['is_primary'] ?? false;
            if ($isPrimary) {
                $this->unsetPrimaryImages($product);
            }

            // Create image record
            $productImage = $product->images()->create([
                'path' => $path,
                'disk' => 'public',
                'order' => $product->images()->max('order') + 1,
                'is_primary' => $isPrimary,
                'alt_text' => $options['alt_text'] ?? $product->name,
            ]);

            DB::commit();

            Log::info('Image uploaded successfully', [
                'product_id' => $product->id,
                'image_id' => $productImage->id,
                'path' => $path,
                'is_primary' => $isPrimary,
            ]);

            return $productImage;

        } catch (Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::debug('Cleaned up orphaned image file', ['path' => $path]);
            }

            Log::error('Failed to upload image', [
                'product_id' => $product->id,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update image details
     */
    public function updateImage(ProductImage $image, array $data): ProductImage
    {
        Log::debug('Updating image details', [
            'image_id' => $image->id,
            'product_id' => $image->product_id,
            'data' => $data,
        ]);

        DB::beginTransaction();

        try {
            // Handle primary image logic
            if (isset($data['is_primary']) && $data['is_primary']) {
                $this->unsetPrimaryImages($image->product);
            }

            $image->update($data);

            DB::commit();

            Log::info('Image updated successfully', [
                'image_id' => $image->id,
                'product_id' => $image->product_id,
            ]);

            return $image->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update image', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete image and its file
     */
    public function deleteImage(ProductImage $image): bool
    {
        Log::info('Deleting image', [
            'image_id' => $image->id,
            'product_id' => $image->product_id,
            'path' => $image->path,
        ]);

        try {
            // Image deletion will trigger model event that deletes the file
            $result = $image->delete();

            Log::info('Image deleted successfully', [
                'image_id' => $image->id,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete image', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Reorder multiple images
     */
    public function reorderImages(Product $product, array $imageOrders): void
    {
        Log::info('Reordering images', [
            'product_id' => $product->id,
            'image_count' => count($imageOrders),
        ]);

        DB::beginTransaction();

        try {
            foreach ($imageOrders as $imageData) {
                ProductImage::where('id', $imageData['id'])
                    ->where('product_id', $product->id)
                    ->update(['order' => $imageData['order']]);
            }

            DB::commit();

            Log::info('Images reordered successfully', [
                'product_id' => $product->id,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to reorder images', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Unset all primary images for a product
     */
    protected function unsetPrimaryImages(Product $product): void
    {
        $product->images()->update(['is_primary' => false]);
    }

    /**
     * Validate image belongs to product
     */
    public function validateImageBelongsToProduct(ProductImage $image, Product $product): bool
    {
        return $image->product_id === $product->id;
    }
}
