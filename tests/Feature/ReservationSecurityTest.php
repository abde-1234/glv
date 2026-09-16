<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class ReservationSecurityTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_foreign_participants_dates_amounts_and_cancelled_conflicts(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $this->actingAs($a['admin']);
        $data = ['client_id' => $a['client']->id, 'voiture_id' => $a['car']->id,
            'date_debut' => '2026-12-20', 'date_fin' => '2026-12-22', 'statut' => 'confirmee'];
        foreach (['client_id' => $b['client']->id, 'voiture_id' => $b['car']->id] as $field => $id) {
            $this->postJson('/reservations', array_replace($data, [$field => $id]))->assertForbidden();
            $this->putJson('/reservations/'.$a['reservation']->id, array_replace($data, [$field => $id]))->assertForbidden();
        }
        $this->postJson('/reservations', array_replace($data, ['date_fin' => '2026-12-01']))->assertUnprocessable()->assertJsonValidationErrors('date_fin');
        $this->postJson('/reservations', array_replace($data, ['date_debut' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date_debut');
        $this->post('/reservations', $data + ['agence_id' => $b['agency']->id, 'prix_jour' => 1, 'montant' => 1])->assertSessionHasNoErrors();
        $created = Reservation::latest('id')->firstOrFail();
        $this->assertSame('600.00', $created->montant);
        $this->assertSame('300.00', $created->prix_jour);
        $this->assertSame($a['agency']->id, $created->agence_id);
        $this->postJson('/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors('voiture_id');
        $this->putJson('/reservations/'.$a['reservation']->id, $data)->assertUnprocessable()->assertJsonValidationErrors('voiture_id');
        $this->post('/reservations', array_replace($data, ['statut' => 'annulee']))->assertSessionHasNoErrors();
        $cancelled = Reservation::latest('id')->firstOrFail();
        $this->putJson('/reservations/'.$cancelled->id, $data)->assertUnprocessable()->assertJsonValidationErrors('voiture_id');
        $this->patch('/reservations/'.$created->id.'/annuler')->assertSessionHasNoErrors();
        $this->put('/reservations/'.$cancelled->id, $data)->assertSessionHasNoErrors();
    }

    public function test_inconsistent_legacy_relations_are_rejected_for_reservations_and_contracts(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $this->actingAs($a['admin']);
        foreach (['client_id' => $b['client']->id, 'voiture_id' => $b['car']->id] as $field => $foreignId) {
            $original = $a['reservation']->getAttribute($field);
            $a['reservation']->update([$field => $foreignId]);
            $this->get('/reservations/'.$a['reservation']->id)->assertForbidden();
            $this->post('/contrats', ['reservation_id' => $a['reservation']->id, 'statut' => 'actif'])->assertForbidden();
            $a['reservation']->update([$field => $original]);
            $original = $a['contract']->getAttribute($field);
            $a['contract']->update([$field => $foreignId]);
            $this->get('/contrats/'.$a['contract']->id)->assertForbidden();
            $this->put('/contrats/'.$a['contract']->id, ['statut' => 'annule'])->assertForbidden();
            $this->patch('/contrats/'.$a['contract']->id.'/resilier')->assertForbidden();
            $a['contract']->update([$field => $original]);
        }
        $a['contract']->update(['reservation_id' => $b['reservation']->id]);
        $this->get('/contrats/'.$a['contract']->id)->assertForbidden();
    }

    public function test_contract_creation_ignores_injected_fields_and_rejects_duplicates_or_cancellation(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $a['contract']->delete();
        $this->actingAs($a['admin']);
        $this->post('/contrats', ['reservation_id' => $a['reservation']->id, 'statut' => 'actif',
            'agence_id' => $b['agency']->id, 'client_id' => $b['client']->id, 'voiture_id' => $b['car']->id,
            'montant' => 1, 'reference' => 'Injected', 'date_debut' => '2099-01-01'])->assertSessionHasNoErrors();
        $contract = Contrat::latest('id')->firstOrFail();
        $this->assertSame($a['agency']->id, $contract->agence_id);
        $this->assertSame($a['client']->id, $contract->client_id);
        $this->assertSame($a['car']->id, $contract->voiture_id);
        $this->assertSame('600.00', $contract->montant);
        $this->assertMatchesRegularExpression('/^CTR-2026-\d{4}$/', $contract->reference);
        $this->postJson('/contrats', ['reservation_id' => $a['reservation']->id, 'statut' => 'actif'])->assertUnprocessable();
        $contract->delete();
        $a['reservation']->update(['statut' => 'annulee']);
        $this->postJson('/contrats', ['reservation_id' => $a['reservation']->id, 'statut' => 'actif'])->assertUnprocessable();
    }

    public function test_invalid_scalar_and_oversized_amount_return_validation_not_server_error(): void
    {
        $a = $this->tenant('A');
        $this->actingAs($a['admin'])->postJson('/voitures', ['immatriculation' => ['bad']])
            ->assertUnprocessable()->assertJsonValidationErrors('immatriculation');
        $a['car']->update(['prix_jour' => 99999999]);
        $this->postJson('/reservations', ['client_id' => $a['client']->id, 'voiture_id' => $a['car']->id,
            'date_debut' => '2027-01-01', 'date_fin' => '2027-01-03', 'statut' => 'confirmee'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }
}
