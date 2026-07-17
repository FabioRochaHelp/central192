<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * CRUD mínimo por nome para cadastros globais de parâmetro da ocorrência.
 *
 * @property-read class-string<Model> $modelClass Overridden in docblocks via concrete classes.
 */
#[Layout('layouts.app')]
abstract class SimpleParameterManage extends Component
{
    public string $formName = '';

    public ?int $editingId = null;

    public string $message = '';

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function heading(): string;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);
    }

    public function resetForm(): void
    {
        $this->formName = '';
        $this->editingId = null;
        $this->afterResetForm();
    }

    protected function afterResetForm(): void {}

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $row = $this->modelClass()::query()->findOrFail($id);
        $this->editingId = $row->id;
        $this->formName = $row->name;
        $this->afterEdit($row);
    }

    protected function afterEdit(Model $row): void {}

    /** @return array<string, mixed> */
    protected function validationRules(): array
    {
        return [
            'formName' => ['required', 'string', 'max:255'],
        ];
    }

    /** @param  array<string, mixed>  $validated */
    protected function payloadFromValidated(array $validated): array
    {
        return ['name' => $validated['formName']];
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $validated = $this->validate($this->validationRules());
        $payload = $this->payloadFromValidated($validated);

        if ($this->editingId !== null) {
            $row = $this->modelClass()::query()->findOrFail($this->editingId);
            $row->update($payload);
            $this->message = __('Registro atualizado.');
        } else {
            $this->modelClass()::query()->create($payload);
            $this->message = __('Registro criado.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $this->modelClass()::query()->findOrFail($id)->delete();
        $this->message = __('Registro excluído.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $class = $this->modelClass();

        return view('livewire.operations.parameters.simple-crud', [
            'heading' => $this->heading(),
            'items' => $class::query()->orderBy('name')->get(),
        ]);
    }
}
