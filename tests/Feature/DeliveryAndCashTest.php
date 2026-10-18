<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** Prueba de entrega, recaudo contra entrega y cuadre de caja, por HTTP. */
final class DeliveryAndCashTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    private CompanyId $company;

    private string $carrierId;

    private ?User $driver = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->company = $this->company('Tienda Uno');
        $this->carrierId = $this->carrier($this->company);
    }

    public function test_delivery_stores_photo_and_signature_and_they_can_be_downloaded(): void
    {
        $id = $this->orderInRoute();

        $delivery = $this->asDriver()->post("/api/orders/{$id}/deliver", [
            'receiver_name' => 'Portero Edificio',
            'receiver_document' => '1020304050',
            'photo' => UploadedFile::fake()->image('paquete.jpg', 800, 600),
            'signature' => UploadedFile::fake()->image('firma.png', 400, 200),
            'latitude' => 6.2088,
            'longitude' => -75.5679,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.delivery.receiver_name', 'Portero Edificio')
            ->assertJsonPath('data.delivery.has_photo', true)
            ->assertJsonPath('data.delivery.has_signature', true)
            ->json('data.delivery');

        self::assertEqualsWithDelta(6.2088, $delivery['latitude'], 0.0001);
        self::assertCount(2, Storage::disk('local')->allFiles("evidence/{$this->company}/{$id}"));

        $this->get("/api/orders/{$id}/evidence/photo")->assertOk();
        $this->get("/api/orders/{$id}/evidence/signature")->assertOk();
    }

    public function test_delivery_without_required_proof_is_rejected_and_nothing_is_stored(): void
    {
        $id = $this->orderInRoute();

        $this->asDriver()->postJson("/api/orders/{$id}/deliver", ['receiver_name' => 'Laura'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'delivery_proof_incomplete');

        $this->asDriver()->post("/api/orders/{$id}/deliver", [
            'photo' => UploadedFile::fake()->image('p.jpg'),
            'signature' => UploadedFile::fake()->image('f.png'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        self::assertSame([], Storage::disk('local')->allFiles(), 'Los archivos de una entrega rechazada se borran');
    }

    public function test_proof_requirements_are_configurable(): void
    {
        config(['isa.delivery_proof.require.photo' => false, 'isa.delivery_proof.require.signature' => false]);
        $id = $this->orderInRoute();

        $this->asDriver()->postJson("/api/orders/{$id}/deliver", ['receiver_name' => 'Laura'])->assertOk();
    }

    public function test_cash_on_delivery_must_match_and_supports_split_payments(): void
    {
        $id = $this->orderInRoute(cashOnDelivery: 120000);

        $this->asDriver()->post("/api/orders/{$id}/deliver", $this->proof([
            ['method' => 'cash', 'amount' => 100000],
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'collection_amount_mismatch');

        $this->asDriver()->post("/api/orders/{$id}/deliver", $this->proof([
            ['method' => 'cash', 'amount' => 80000],
            ['method' => 'nequi', 'amount' => 40000, 'reference' => 'M998877'],
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.cash_on_delivery', 120000)
            ->assertJsonPath('data.delivery.collected_total', 120000)
            ->assertJsonCount(2, 'data.delivery.payments');
    }

    public function test_daily_cash_summary_and_closing(): void
    {
        $this->deliverWithPayments(50000, [['method' => 'cash', 'amount' => 50000]]);
        $this->deliverWithPayments(70000, [['method' => 'cash', 'amount' => 30000], ['method' => 'daviplata', 'amount' => 40000]]);
        $this->deliverWithPayments(25000, [['method' => 'card_terminal', 'amount' => 25000]]);

        // El conductor ve su propio resumen sin indicar carrier_id.
        $this->asDriver()->getJson('/api/cash/summary')
            ->assertOk()
            ->assertJsonPath('data.collected.orders_count', 3)
            ->assertJsonPath('data.collected.expected_cash', 80000)
            ->assertJsonPath('data.collected.total', 145000)
            ->assertJsonPath('data.collected.totals_by_method.daviplata', 40000)
            ->assertJsonPath('data.closing', null);

        // El conductor no puede cerrar caja; el despachador sí.
        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 80000])->assertForbidden();

        $this->asDispatcher();
        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 75000])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'difference_requires_note');

        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 75000, 'notes' => 'Faltan 5.000, se descuentan'])
            ->assertCreated()
            ->assertJsonPath('data.difference', -5000)
            ->assertJsonPath('data.result', 'short');

        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 80000])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'cash_already_closed');

        $this->getJson('/api/cash/closings')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_register_collections_after_cash_is_closed(): void
    {
        $id = $this->orderInRoute(cashOnDelivery: 30000);

        $this->asDispatcher();
        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 0])->assertCreated();

        $this->asDriver()->post("/api/orders/{$id}/deliver", $this->proof([['method' => 'cash', 'amount' => 30000]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'cash_register_already_closed');
    }

    public function test_other_company_cannot_see_cash_or_evidence(): void
    {
        $id = $this->deliverWithPayments(10000, [['method' => 'cash', 'amount' => 10000]]);

        Sanctum::actingAs($this->user($this->company('Otra'), Role::Dispatcher));
        $this->get("/api/orders/{$id}/evidence/photo", ['Accept' => 'application/json'])->assertNotFound();
        $this->postJson('/api/cash/closings', ['carrier_id' => $this->carrierId, 'cash_received' => 10000])
            ->assertNotFound()
            ->assertJsonPath('error', 'carrier_not_found');
    }

    // ----------------------------------------------------------------- ayudantes

    private function orderInRoute(?int $cashOnDelivery = null): string
    {
        $this->asDispatcher();
        $id = $this->postJson('/api/orders', $this->orderPayload(['cash_on_delivery' => $cashOnDelivery]))->assertCreated()->json('data.id');
        $this->postJson("/api/orders/{$id}/assign", ['carrier_id' => $this->carrierId])->assertOk();

        $this->asDriver()->postJson("/api/orders/{$id}/status", ['status' => 'in_route'])->assertOk();

        return $id;
    }

    /** @param list<array<string, mixed>> $payments */
    private function deliverWithPayments(int $cashOnDelivery, array $payments): string
    {
        $id = $this->orderInRoute($cashOnDelivery);
        $this->asDriver()->post("/api/orders/{$id}/deliver", $this->proof($payments), ['Accept' => 'application/json'])->assertOk();

        return $id;
    }

    /** @param list<array<string, mixed>> $payments */
    private function proof(array $payments = []): array
    {
        return [
            'receiver_name' => 'Laura Gómez',
            'photo' => UploadedFile::fake()->image('paquete.jpg'),
            'signature' => UploadedFile::fake()->image('firma.png'),
            'payments' => $payments,
        ];
    }

    private function asDriver(): static
    {
        $this->driver ??= $this->user($this->company, Role::Driver, $this->carrierId);
        Sanctum::actingAs($this->driver);

        return $this;
    }

    private function asDispatcher(): static
    {
        Sanctum::actingAs($this->user($this->company, Role::Dispatcher));

        return $this;
    }
}
