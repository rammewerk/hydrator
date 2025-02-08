<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class StringProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        return static function (mixed $value): string {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to string');
            return (string)$value;
        };
    }


}