<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Nature;
use App\Models\NatureType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Natureza e tipo — cadastro global; apenas operador central (middleware). */
#[Layout('layouts.app')]
#[Title('Parâmetros — Naturezas')]
final class NatureParameterManage extends Component
{
    public string $typeFormName = '';

    public ?int $editingTypeId = null;

    public string $natureFormName = '';

    public ?int $natureFormNatureTypeId = null;

    public string $natureFormReportModality = '';

    public ?int $editingNatureId = null;

    public string $message = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);
    }

    public function resetNatureTypeForm(): void
    {
        $this->typeFormName = '';
        $this->editingTypeId = null;
    }

    public function editNatureType(int $id): void
    {
        $this->resetErrorBag();
        $type = NatureType::query()->findOrFail($id);
        $this->editingTypeId = $type->id;
        $this->typeFormName = $type->name;
    }

    public function saveNatureType(): void
    {
        $this->resetErrorBag();
        $validated = $this->validate([
            'typeFormName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('nature_types', 'name')->ignore($this->editingTypeId),
            ],
        ]);

        if ($this->editingTypeId !== null) {
            NatureType::query()->whereKey($this->editingTypeId)->update(['name' => $validated['typeFormName']]);
            $this->message = __('Tipo atualizado.');
        } else {
            NatureType::query()->create(['name' => $validated['typeFormName']]);
            $this->message = __('Tipo criado.');
        }

        $this->resetNatureTypeForm();
    }

    public function deleteNatureType(int $id): void
    {
        $this->resetErrorBag();
        $type = NatureType::query()->findOrFail($id);
        if ($type->natures()->exists()) {
            $this->addError('typeDelete', __('Remova ou realoque as naturezas deste tipo antes de excluir.'));

            return;
        }
        $type->delete();
        $this->message = __('Tipo excluído.');
        if ($this->editingTypeId === $id) {
            $this->resetNatureTypeForm();
        }
    }

    public function resetNatureForm(): void
    {
        $this->natureFormName = '';
        $this->natureFormNatureTypeId = null;
        $this->natureFormReportModality = '';
        $this->editingNatureId = null;
    }

    public function editNature(int $id): void
    {
        $this->resetErrorBag();
        $nature = Nature::query()->findOrFail($id);
        $this->editingNatureId = $nature->id;
        $this->natureFormName = $nature->name;
        $this->natureFormNatureTypeId = $nature->nature_type_id;
        $this->natureFormReportModality = $nature->report_modality?->value ?? '';
    }

    public function saveNature(): void
    {
        $this->resetErrorBag();
        $modalityValues = collect(IncidentReportModality::cases())->pluck('value')->implode(',');
        $validated = $this->validate([
            'natureFormName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('natures', 'name')
                    ->where(fn ($q) => $q->where('nature_type_id', $this->natureFormNatureTypeId))
                    ->ignore($this->editingNatureId),
            ],
            'natureFormNatureTypeId' => ['required', 'integer', 'exists:nature_types,id'],
            'natureFormReportModality' => ['nullable', 'string', "in:{$modalityValues}"],
        ]);

        $modality = $validated['natureFormReportModality'] ?: null;

        if ($this->editingNatureId !== null) {
            Nature::query()->whereKey($this->editingNatureId)->update([
                'name' => $validated['natureFormName'],
                'nature_type_id' => $validated['natureFormNatureTypeId'],
                'report_modality' => $modality,
            ]);
            $this->message = __('Natureza atualizada.');
        } else {
            Nature::query()->create([
                'nature_type_id' => $validated['natureFormNatureTypeId'],
                'name' => $validated['natureFormName'],
                'report_modality' => $modality,
            ]);
            $this->message = __('Natureza criada.');
        }

        $this->resetNatureForm();
    }

    public function deleteNature(int $id): void
    {
        $this->resetErrorBag();
        Nature::query()->findOrFail($id)->delete();
        $this->message = __('Natureza excluída.');
        if ($this->editingNatureId === $id) {
            $this->resetNatureForm();
        }
    }

    public function render(): View
    {
        return view('livewire.operations.parameters.nature-parameter-manage', [
            'natureTypes' => NatureType::query()->orderBy('name')->get(),
            'natures' => Nature::query()->with('natureType')->orderBy('name')->get(),
            'reportModalities' => IncidentReportModality::cases(),
        ]);
    }
}
