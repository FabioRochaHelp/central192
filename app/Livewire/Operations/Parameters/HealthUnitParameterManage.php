<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\HealthUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Unidades de atendimento')]
final class HealthUnitParameterManage extends SimpleParameterManage
{
    public string $formNotes = '';

    protected function modelClass(): string
    {
        return HealthUnit::class;
    }

    protected function heading(): string
    {
        return __('Unidades de atendimento');
    }

    protected function afterResetForm(): void
    {
        $this->formNotes = '';
    }

    protected function afterEdit(Model $row): void
    {
        /** @var HealthUnit $row */
        $this->formNotes = (string) ($row->notes ?? '');
    }

    protected function validationRules(): array
    {
        return [
            'formName' => ['required', 'string', 'max:255'],
            'formNotes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function payloadFromValidated(array $validated): array
    {
        return [
            'name' => $validated['formName'],
            'notes' => ($validated['formNotes'] ?? '') !== '' ? $validated['formNotes'] : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.operations.parameters.health-unit-crud', [
            'heading' => $this->heading(),
            'items' => HealthUnit::query()->orderBy('name')->get(),
        ]);
    }
}
