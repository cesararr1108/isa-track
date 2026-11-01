<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\UpdateCarrier;

use Isa\Fleet\Application\CarrierData;
use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\Exception\CarrierNotFound;
use Isa\Fleet\Domain\Exception\DuplicateCarrierDocument;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CarrierId;

final class UpdateCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(string $carrierId, CarrierData $data): Carrier
    {
        $companyId = $this->tenant->companyId();
        $id = CarrierId::fromString($carrierId);
        $carrier = $this->carriers->find($companyId, $id) ?? throw new CarrierNotFound($carrierId);
        $profile = $data->profile();

        if ($profile->documentNumber !== null && $this->carriers->documentTaken($companyId, $profile->documentNumber, $id)) {
            throw new DuplicateCarrierDocument($profile->documentNumber);
        }

        $carrier->update($data->name, CarrierType::from($data->type), $profile);
        $this->carriers->save($carrier);

        return $carrier;
    }
}
