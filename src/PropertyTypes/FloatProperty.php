<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class FloatProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        return static function (mixed $value): float {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to float');
            return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
                ?? throw new HydratorException('Unable to convert value of ' . gettype($value) . ' to float');
        };
    }


}