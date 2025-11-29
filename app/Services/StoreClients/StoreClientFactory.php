<?php

declare(strict_types=1);

namespace App\Services\StoreClients;

use Illuminate\Support\Facades\Config;

class StoreClientFactory
{
    /**
     * Create a store client instance.
     */
    public function create(string $storeName): ?GenericStoreClient
    {
        $config = Config::get("external_stores.{$storeName}");

        if (! \is_array($config)) {
            return null;
        }

        // For now, we only have a generic client.
        // In the future, we could have specific clients for each store.
        return new GenericStoreClient($config);
    }
}
