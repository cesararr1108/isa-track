<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/** Medios de recaudo contra entrega. Las etiquetas están en config/isa.php. */
enum PaymentMethod: string
{
    case Cash = 'cash';                 // Efectivo
    case Nequi = 'nequi';
    case Daviplata = 'daviplata';
    case CardTerminal = 'card_terminal'; // Datáfono
    case BankTransfer = 'bank_transfer';
}
