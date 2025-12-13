<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\VariantGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes per job
    public $tries = 3;

    protected $productId;
    protected $attributeIds;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(int $productId, array $attributeIds, array $options = [])
    {
        $this->productId = $productId;
        $this->attributeIds = $attributeIds;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(VariantGeneratorService $variantGenerator): void
    {
        $product = Product::find($this->productId);

        if (!$product) {
            Log::warning("GenerateVariantsJob: Product {$this->productId} not found");
            return;
        }

        try {
            $variants = $variantGenerator->generateVariants(
                $product,
                $this->attributeIds,
                $this->options
            );

            Log::info("GenerateVariantsJob: Created " . count($variants) . " variants for product {$product->id} ({$product->name})");

        } catch (\Exception $e) {
            Log::error("GenerateVariantsJob: Failed for product {$this->productId}: " . $e->getMessage());
            throw $e; // Re-throw to trigger retry
        }
    }
}
