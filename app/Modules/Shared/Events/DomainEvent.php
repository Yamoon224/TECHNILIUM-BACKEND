<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Marker interface for cross-module domain events (CommandePayee, StockAlloue,
 * LivraisonConfirmee...). Modules communicate only through events/contracts,
 * never by reaching into another module's models or tables (cahier §9.1).
 */
interface DomainEvent {}
