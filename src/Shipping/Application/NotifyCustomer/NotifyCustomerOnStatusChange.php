<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\NotifyCustomer;

use Isa\Shipping\Application\Port\CustomerNotifier;
use Isa\Shipping\Domain\Event\OrderStatusChanged;

/**
 * Suscriptor del evento OrderStatusChanged: avisa al cliente final con el
 * link de rastreo. Qué estados notifican y el texto de cada mensaje se
 * configuran en config/isa.php -> notifications.
 */
final class NotifyCustomerOnStatusChange
{
    /** @param array<string, string> $templates mensaje por estado; los estados sin plantilla no notifican */
    public function __construct(
        private readonly CustomerNotifier $notifier,
        private readonly array $templates,
        private readonly string $trackingBaseUrl,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $template = $this->templates[$event->newStatus] ?? null;
        if ($template === null) {
            return;
        }

        $this->notifier->send($event->recipientPhone, strtr($template, [
            '{nombre}' => explode(' ', trim($event->recipientName))[0],
            '{codigo}' => $event->trackingCode,
            '{link}' => rtrim($this->trackingBaseUrl, '/').'/'.$event->trackingCode,
        ]));
    }
}
