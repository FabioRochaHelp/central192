<?php

declare(strict_types=1);

namespace App\Integrations\Traccar\DTOs;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

final readonly class TraccarDevice
{
    public function __construct(
        public int $id,
        public string $name,
        public string $uniqueId,
        public string $status,
        public ?string $lastUpdate,
        public ?string $phone,
        public ?string $model,
        public ?string $category,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            uniqueId: (string) ($data['uniqueId'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            lastUpdate: isset($data['lastUpdate']) ? (string) $data['lastUpdate'] : null,
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            model: isset($data['model']) && $data['model'] !== '' ? (string) $data['model'] : null,
            category: isset($data['category']) && $data['category'] !== '' ? (string) $data['category'] : null,
        );
    }

    public function label(): string
    {
        return "{$this->name} ({$this->uniqueId})";
    }

    /** Online somente se houve comunicação recente (`lastUpdate`), não apenas o flag `status` do Traccar. */
    public function isReportingAt(?CarbonInterface $at = null): bool
    {
        if ($this->lastUpdate === null || $this->lastUpdate === '') {
            return false;
        }

        $at ??= now();

        try {
            $last = Carbon::parse($this->lastUpdate);
        } catch (Throwable) {
            return false;
        }

        $threshold = max(60, (int) config('traccar.device_online_threshold_seconds', 180));

        return $last->gte($at->copy()->subSeconds($threshold));
    }
}
