<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\RegisterCarrier;

use Isa\Fleet\Application\CarrierData;
use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\Exception\DuplicateCarrierDocument;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CarrierId;

final class RegisterCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(CarrierData $data): CarrierId
    {
        $companyId = $this->tenant->companyId();
        $profile = $data->profile();

        if ($profile->documentNumber !== null && $this->carriers->documentTaken($companyId, $profile->documentNumber)) {
            throw new DuplicateCarrierDocument($profile->documentNumber);
        }

        $carrier = Carrier::register(CarrierId::generate(), $companyId, $data->name, CarrierType::from($data->type), $profile);
        $this->carriers->save($carrier);

        return $carrier->id();
    }
}
