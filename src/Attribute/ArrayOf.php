<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\Attribute;

use Attribute;

/**
 * Tells the hydrator that this array property contains entities of the given class.
 * Example: #[ArrayOf(OrderItem::class)] public array $items = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayOf {

    /** @param class-string $class */
    public function __construct(public string $class) {}


}