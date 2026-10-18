<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

/**
 * Canal para avisar al cliente final. Hoy: log. Mañana: WhatsApp (Meta/Twilio).
 * Cambiar de canal = escribir otro adaptador y cambiar ISA_NOTIFIER en el .env.
 */
interface CustomerNotifier
{
    public function send(string $phone, string $message): void;
}
