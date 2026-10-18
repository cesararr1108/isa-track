<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

interface TrackingCodeGenerator
{
    public function generate(): TrackingCode;
}
