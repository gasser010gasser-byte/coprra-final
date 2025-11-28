<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class StorageBreakdown
{
    public function __construct(
        public float $sizeMb,
        public int $sizeBytes,
        public string $path,
    ) {
    }
}
