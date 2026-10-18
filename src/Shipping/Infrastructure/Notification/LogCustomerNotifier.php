<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Notification;

use Isa\Shipping\Application\Port\CustomerNotifier;
use Psr\Log\LoggerInterface;

/** Notificador de desarrollo: escribe el mensaje en storage/logs/laravel.log. */
final class LogCustomerNotifier implements CustomerNotifier
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(string $phone, string $message): void
    {
        $this->logger->info('[notificación al cliente]', ['phone' => $phone, 'message' => $message]);
    }
}
