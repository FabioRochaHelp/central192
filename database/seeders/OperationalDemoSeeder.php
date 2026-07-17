<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Operations\Enums\ShiftStatus;
use App\Domain\Operations\Enums\StaffCargo;
use App\Domain\Operations\Support\InjuryMatrixDefinition;
use App\Models\Accessory;
use App\Models\CareLocal;
use App\Models\InjurySite;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\NatureType;
use App\Models\Procedure;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vehicle;
use App\Models\VehicleChecklistResource;
use App\Models\VictimType;
use Illuminate\Database\Seeder;

/** Demonstração alinhada a docs/migracao (municipios + núcleo operacional). */
class OperationalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $typeCentral = UserType::query()->create(['name' => 'Central ampla']);
        $typeMunicipal = UserType::query()->create(['name' => 'Operador municipal']);
        $typeDispatcher = UserType::query()->create(['name' => 'Despachador']);
        $typeAttendant = UserType::query()->create(['name' => 'Atendente']);

        $municipio = Municipio::query()->create([
            'razao_social' => 'CCO — Base demonstração',
            'cnpj' => '00.000.000/0001-91',
            'ie' => '123456789',
            'phone' => '(11) 99999-0000',
            'city' => 'Demonstração',
            'state' => 'BR',
            'active' => true,
        ]);

        $natureType = NatureType::query()->create([
            'name' => 'Tipo urgência médica',
        ]);

        Nature::query()->create([
            'nature_type_id' => $natureType->id,
            'name' => 'Atendimento pré-hospitalar',
        ]);

        VictimType::query()->create([
            'name' => 'Tipo vítima demonstração',
        ]);

        Procedure::query()->firstOrCreate(['name' => 'Oxigenoterapia']);
        Procedure::query()->firstOrCreate(['name' => 'Acesso venoso periférico']);
        Accessory::query()->firstOrCreate(['name' => 'Colar cervical']);
        Accessory::query()->firstOrCreate(['name' => 'Kit trauma']);
        foreach (InjuryMatrixDefinition::BODY_REGIONS as $region) {
            foreach (InjuryMatrixDefinition::LESION_TYPES as $lesion) {
                InjurySite::query()->updateOrCreate(
                    ['name' => $region.' - '.$lesion],
                    ['matrix_region' => $region, 'matrix_lesion' => $lesion],
                );
            }
        }
        CareLocal::query()->firstOrCreate(['name' => 'Via pública']);

        VehicleChecklistResource::query()->firstOrCreate(
            ['municipio_id' => $municipio->id, 'name' => 'Água'],
            ['unit_of_measure' => 'litros'],
        );
        VehicleChecklistResource::query()->firstOrCreate(
            ['municipio_id' => $municipio->id, 'name' => 'Abafador'],
            ['unit_of_measure' => 'unidade'],
        );
        VehicleChecklistResource::query()->firstOrCreate(
            ['municipio_id' => $municipio->id, 'name' => 'Corda'],
            ['unit_of_measure' => 'metro'],
        );

        $vehicle = Vehicle::query()->create([
            'municipio_id' => $municipio->id,
            'plate' => 'ABC1D23',
            'prefix' => 'US 01',
            'make' => 'Demo',
            'model' => 'Ambulância',
            'year' => (int) date('Y'),
            'device_id' => null,
            'ssx_integration_code' => null,
        ]);

        Shift::query()->create([
            'municipio_id' => $municipio->id,
            'vehicle_id' => $vehicle->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(18),
            'status' => ShiftStatus::Disponivel,
            'status_legacy' => 1,
            'available_at' => now()->subHour(),
        ]);

        $medicalStaff = Staff::query()->create([
            'municipio_id' => $municipio->id,
            'name' => 'Médico demonstração',
            'document_type' => null,
            'document_number' => '000000',
            'cpf' => null,
            'email' => 'medico.demo@example.com',
            'phone' => null,
            'cargo' => StaffCargo::Socorrista->value,
        ]);

        User::factory()->create([
            'name' => 'Operador central',
            'email' => 'central@example.com',
            'password' => 'password',
            'municipio_id' => null,
            'user_type_id' => $typeCentral->id,
            'users_type_legacy' => 1,
        ]);

        User::factory()->create([
            'name' => 'Operador municipal',
            'email' => 'municipal@example.com',
            'password' => 'password',
            'municipio_id' => $municipio->id,
            'user_type_id' => $typeMunicipal->id,
            'users_type_legacy' => 5,
        ]);

        User::factory()->create([
            'name' => 'Enfermeiro demonstração',
            'email' => 'enfermeiro@example.com',
            'password' => 'password',
            'municipio_id' => $municipio->id,
            'user_type_id' => $typeMunicipal->id,
            'users_type_legacy' => 3,
        ]);

        User::factory()->create([
            'name' => 'Médico regulador',
            'email' => 'medico@example.com',
            'password' => 'password',
            'municipio_id' => $municipio->id,
            'user_type_id' => $typeMunicipal->id,
            'staff_id' => $medicalStaff->id,
            'users_type_legacy' => 4,
        ]);

        User::factory()->create([
            'name' => 'Despachador demonstração',
            'email' => 'despachador@example.com',
            'password' => 'password',
            'municipio_id' => null,
            'user_type_id' => $typeDispatcher->id,
            'users_type_legacy' => 6,
            'call_intake_websocket_enabled' => true,
        ]);

        User::factory()->create([
            'name' => 'Atendente demonstração',
            'email' => 'atendente@example.com',
            'password' => 'password',
            'municipio_id' => null,
            'user_type_id' => $typeAttendant->id,
            'users_type_legacy' => 7,
        ]);
    }
}
