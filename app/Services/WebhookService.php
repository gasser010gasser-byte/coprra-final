<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Webhook;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;

final readonly class WebhookService
{
    public function __construct(
        private CacheService $cacheService,
        private LoggerInterface $logger,
        private Dispatcher $dispatcher,
        private Webhook $webhook,
        private Product $product
    ) {
    }

    /**
     * Process webhook.
     */
    public function processWebhook(Webhook $webhook): void
    {
        try {
            $this->prepareWebhookForProcessing($webhook);
            $this->processWebhookEvent($webhook);
            $this->finalizeWebhookProcessing($webhook);
        } catch (\Exception $exception) {
            $this->handleWebhookProcessingError($webhook, $exception);
        }
    }

    /**
     * Handle incoming webhook and create webhook record.
     */
    public function handleWebhook(string $storeIdentifier, string $eventType, array $payload): Webhook
    {
        try {
            $webhook = $this->webhook->create([
                'store_identifier' => $storeIdentifier,
                'event_type' => $eventType,
                'product_identifier' => $payload['product_identifier'] ?? null,
                'payload' => $payload,
                'status' => Webhook::STATUS_PENDING,
            ]);

            $webhook->addLog('created', 'Webhook created successfully');

            return $webhook;
        } catch (\Exception $exception) {
            $this->logger->error('Failed to create webhook', [
                'store_identifier' => $storeIdentifier,
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);

            // Create failed webhook record for tracking
            $webhook = $this->webhook->create([
                'store_identifier' => $storeIdentifier,
                'event_type' => $eventType,
                'product_identifier' => $payload['product_identifier'] ?? null,
                'payload' => $payload,
                'status' => Webhook::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            return $webhook;
        }
    }

    /**
     * Get webhook statistics for the specified number of days.
     */
    public function getStatistics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $total = $this->webhook->where('created_at', '>=', $startDate)->count();
        $pending = $this->webhook->where('created_at', '>=', $startDate)
            ->where('status', Webhook::STATUS_PENDING)->count()
        ;
        $completed = $this->webhook->where('created_at', '>=', $startDate)
            ->where('status', Webhook::STATUS_COMPLETED)->count()
        ;
        $failed = $this->webhook->where('created_at', '>=', $startDate)
            ->where('status', Webhook::STATUS_FAILED)->count()
        ;

        return [
            'total' => $total,
            'pending' => $pending,
            'completed' => $completed,
            'failed' => $failed,
        ];
    }

    private function prepareWebhookForProcessing(Webhook $webhook): void
    {
        $webhook->markAsProcessing();
        $webhook->addLog('processing', 'Started processing webhook');

        if ($webhook->signature && ! $this->verifySignature($webhook)) {
            throw new \Exception('Invalid webhook signature');
        }
    }

    private function processWebhookEvent(Webhook $webhook): void
    {
        $product = $this->findOrCreateProduct($webhook);

        if ($product instanceof Product) {
            $webhook->update(['product_id' => $product->id]);
        }

        match ($webhook->event_type) {
            Webhook::EVENT_PRICE_UPDATE => $this->handlePriceUpdate($webhook, $product),
            Webhook::EVENT_STOCK_UPDATE => $this->handleStockUpdate($webhook, $product),
            Webhook::EVENT_PRODUCT_UPDATE => $this->handleProductUpdate($webhook, $product),
            default => throw new \Exception("Unknown event type: {$webhook->event_type}"),
        };
    }

    private function finalizeWebhookProcessing(Webhook $webhook): void
    {
        $webhook->markAsCompleted();
        $webhook->addLog('completed', 'Webhook processed successfully');
    }

    private function handleWebhookProcessingError(Webhook $webhook, \Exception $exception): void
    {
        $webhook->markAsFailed($exception->getMessage());
        $webhook->addLog('failed', 'Webhook processing failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->logger->error('Webhook processing failed', [
            'webhook_id' => $webhook->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Handle price update event.
     */
    private function handlePriceUpdate(Webhook $webhook, ?Product $product): void
    {
        if (! $product instanceof Product) {
            throw new \Exception('Product not found for price update');
        }

        $payload = $webhook->payload;

        /** @var array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null> $payload */
        /** @var float|int|string|null $priceValue */
        $priceValue = $payload['price'] ?? null;
        $newPrice = is_numeric($priceValue) ? (float) $priceValue : null;

        if (! $newPrice) {
            throw new \Exception('Price not provided in payload');
        }

        $this->updateProductPrice(
            $product,
            $webhook->store_identifier,
            $newPrice,
            $payload['currency'] ?? 'USD'
        );

        // Invalidate cache
        $this->cacheService->invalidateProduct($product->id);

        $newPriceStr = (string) $newPrice;
        $webhook->addLog('price_updated', 'Price updated to '.$newPriceStr, [
            'new_price' => $newPrice,
        ]);
    }

    private function updateProductPrice(
        Product $product,
        string $storeIdentifier,
        float $newPrice,
        string $currency
    ): void {
        /** @var array<string, array{
         *     price: float,
         *     currency: string,
         *     updated_at: string
         * }> $storePrices */
        $storePrices = $product->store_prices ?? [];
        if (! \is_array($storePrices)) {
            $storePrices = [];
        }
        $storePrices[$storeIdentifier] = [
            'price' => $newPrice,
            'currency' => $currency,
            'updated_at' => now()->toIso8601String(),
        ];

        $product->update(['store_prices' => $storePrices]);
    }

    /**
     * Handle stock update event.
     */
    private function handleStockUpdate(Webhook $webhook, ?Product $product): void
    {
        if (! $product instanceof Product) {
            throw new \Exception('Product not found for stock update');
        }

        $payload = $webhook->payload;
        $inStock = $payload['in_stock'] ?? null;

        if (null === $inStock) {
            throw new \Exception('Stock status not provided in payload');
        }

        /** @var int|string|null $quantityValue */
        $quantityValue = $payload['quantity'] ?? null;
        $quantity = is_numeric($quantityValue) ? (int) $quantityValue : null;

        // Update product stock for this store
        $storeStock = $product->store_stock ?? [];
        if (! \is_array($storeStock)) {
            $storeStock = [];
        }

        $storeStock[$webhook->store_identifier] = [
            'in_stock' => $inStock,
            'quantity' => $quantity,
            'updated_at' => now()->toIso8601String(),
        ];

        $product->update(['store_stock' => $storeStock]);

        // Invalidate cache
        $this->cacheService->invalidateProduct($product->id);

        $webhook->addLog('stock_updated', 'Stock status updated', [
            'in_stock' => $inStock,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Handle product update event.
     */
    private function handleProductUpdate(Webhook $webhook, ?Product $product): void
    {
        if (! $product instanceof Product) {
            throw new \Exception('Product not found for product update');
        }

        $payload = $webhook->payload;
        $updates = $this->getProductUpdatesFromPayload($payload);

        if ([] !== $updates) {
            $product->update($updates);

            // Invalidate cache
            $this->cacheService->invalidateProduct($product->id);

            $webhook->addLog('product_updated', 'Product details updated', $updates);
        }
    }

    /**
     * @param  iterable<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>  $payload
     *
     * @return array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>
     */
    private function getProductUpdatesFromPayload(iterable $payload): array
    {
        $updates = [];

        if (isset($payload['title']) && \is_string($payload['title'])) {
            $updates['name'] = $payload['name'];
        }

        if (isset($payload['description'])) {
            $updates['description'] = $payload['description'];
        }

        if (isset($payload['image_url'])) {
            $updates['image_url'] = $payload['image_url'];
        }

        return $updates;
    }

    /**
     * Find or create product from webhook.
     */
    private function findOrCreateProduct(Webhook $webhook): ?Product
    {
        $productIdentifier = $webhook->product_identifier;

        // Try to find existing product by store mapping
        $product = $this->product->whereJsonContains(
            "store_mappings->{$webhook->store_identifier}",
            $productIdentifier
        )->first();

        if (
            ! $product
            && isset($webhook->payload['create_if_not_exists'])
            && $webhook->payload['create_if_not_exists']
        ) {
            // Create new product
            $product = $this->product->create([
                'name' => $webhook->payload['name'] ?? 'Unknown Product',
                'description' => $webhook->payload['description'] ?? null,
                'image_url' => $webhook->payload['image_url'] ?? null,
                'store_mappings' => [
                    $webhook->store_identifier => $productIdentifier,
                ],
            ]);

            $webhook->addLog('product_created', 'New product created', [
                'product_id' => $product->id,
            ]);
        }

        return $product;
    }

    /**
     * Verify webhook signature.
     */
    private function verifySignature(Webhook $webhook): bool
    {
        $secret = config("services.{$webhook->store_identifier}.webhook_secret");

        if (! $secret) {
            return true; // No secret configured, skip verification
        }

        $payload = $webhook->payload;
        if (! \is_array($payload)) {
            return false; // Invalid payload format
        }

        $payloadJson = json_encode($payload);
        if (false === $payloadJson) {
            return false; // Invalid payload
        }

        $secretStr = (string) $secret;
        $expectedSignature = hash_hmac('sha256', $payloadJson, $secretStr);

        return hash_equals((string) $webhook->signature, $expectedSignature);
    }
}
