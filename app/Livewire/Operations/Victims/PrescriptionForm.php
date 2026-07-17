<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Victims;

use App\Domain\Operations\Actions\CreatePrescriptionAction;
use App\Domain\Operations\Enums\StaffCargo;
use App\Models\Prescription;
use App\Models\Staff;
use App\Models\User;
use App\Models\Victim;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Prescrição médica')]
final class PrescriptionForm extends Component
{
    public Victim $victim;

    public string $medical_staff_id = '';

    public string $description = '';

    /** @var list<array{medication_name: string, quantity: string}> */
    public array $items = [];

    public function mount(Victim $victim): void
    {
        $this->victim = $victim->load(['incident', 'prescriptions.items']);

        Gate::authorize('create', [Prescription::class, $this->victim]);

        $this->items = [$this->emptyItem()];
    }

    /** @return array{medication_name: string, quantity: string} */
    private function emptyItem(): array
    {
        return [
            'medication_name' => '',
            'quantity' => '1',
        ];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->emptyItem()];
        }
    }

    public function save(CreatePrescriptionAction $action): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('create', [Prescription::class, $this->victim]);

        $validated = Validator::make([
            'medical_staff_id' => $this->medical_staff_id === '' ? null : (int) $this->medical_staff_id,
            'description' => $this->description,
            'items' => array_values($this->items),
        ], [
            'medical_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ])->validate();

        $items = array_map(static fn (array $item): array => [
            'medication_name' => trim((string) $item['medication_name']),
            'quantity' => (int) $item['quantity'],
        ], $validated['items']);

        try {
            $prescription = $action->execute(
                $this->victim->fresh(['incident']),
                $user,
                $validated['medical_staff_id'] ?? null,
                filled($validated['description'] ?? null) ? (string) $validated['description'] : null,
                $items,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível criar a prescrição.'));

            return;
        }

        $this->redirect(route('operations.prescriptions.approval', $prescription), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.operations.victims.prescription-form', [
            'medicalStaff' => Staff::query()
                ->where('cargo', StaffCargo::Socorrista->value)
                ->where('municipio_id', $this->victim->municipio_id)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
