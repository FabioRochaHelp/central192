<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Models\Shift;
use App\Models\ShiftChecklistItem;
use App\Models\VehicleChecklistResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveShiftChecklistAction
{
    /**
     * @param  list<int|string>  $selectedResourceIds
     * @param  array<int|string, int|string|null>  $quantities  keyed by vehicle_checklist_resource_id
     */
    public function execute(
        Shift $shift,
        array $selectedResourceIds,
        array $quantities,
        bool $markComplete,
        ?string $observation = null,
    ): Shift {
        $catalogResourceIds = VehicleChecklistResource::query()
            ->where('municipio_id', $shift->municipio_id)
            ->orderBy('name')
            ->pluck('id', 'id');

        if ($catalogResourceIds->isEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => __('Cadastre itens de check-list para esta base antes de preencher o turno.'),
            ]);
        }

        $selectedIds = collect($selectedResourceIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        $invalidSelection = $selectedIds->first(
            static fn (int $id): bool => ! $catalogResourceIds->has($id),
        );

        if ($invalidSelection !== null) {
            throw ValidationException::withMessages([
                'checklist' => __('Seleção de recursos inválida para esta base.'),
            ]);
        }

        if (! $markComplete && blank($observation)) {
            throw ValidationException::withMessages([
                'checklistObservation' => __('Informe uma observação quando o check-list não for concluído.'),
            ]);
        }

        $normalized = $this->normalizeSelectedQuantities($selectedIds, $quantities);

        return DB::transaction(function () use ($shift, $selectedIds, $normalized, $markComplete, $observation): Shift {
            ShiftChecklistItem::query()
                ->where('shift_id', $shift->id)
                ->when(
                    $selectedIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('vehicle_checklist_resource_id', $selectedIds->all()),
                    fn ($query) => $query,
                )
                ->delete();

            foreach ($normalized as $resourceId => $quantity) {
                ShiftChecklistItem::query()->updateOrCreate(
                    [
                        'shift_id' => $shift->id,
                        'vehicle_checklist_resource_id' => $resourceId,
                    ],
                    ['quantity' => $quantity],
                );
            }

            $shift->update([
                'checklist_completed_at' => $markComplete ? now() : null,
                'checklist_observation' => $markComplete ? null : ($observation !== null && $observation !== '' ? $observation : null),
            ]);

            return $shift->fresh(['checklistItems.resource', 'vehicle']);
        });
    }

    /**
     * @param  Collection<int, int>  $selectedIds
     * @param  array<int|string, int|string|null>  $quantities
     * @return array<int, int>
     */
    private function normalizeSelectedQuantities(Collection $selectedIds, array $quantities): array
    {
        if ($selectedIds->isEmpty()) {
            return [];
        }

        $normalized = [];

        foreach ($selectedIds as $resourceId) {
            $key = (string) $resourceId;
            $raw = $quantities[$key] ?? $quantities[$resourceId] ?? null;

            if ($raw === null || $raw === '') {
                $resourceName = VehicleChecklistResource::query()->whereKey($resourceId)->value('name') ?? (string) $resourceId;

                throw ValidationException::withMessages([
                    "checklistQuantities.{$resourceId}" => __('Informe a quantidade para :item.', ['item' => $resourceName]),
                ]);
            }

            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw != floor((float) $raw)) {
                throw ValidationException::withMessages([
                    "checklistQuantities.{$resourceId}" => __('Informe uma quantidade inteira válida (zero ou maior).'),
                ]);
            }

            $normalized[$resourceId] = (int) $raw;
        }

        return $normalized;
    }
}
