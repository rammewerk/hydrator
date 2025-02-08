<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class IntProperty extends PropertyHandler {


    protected function getConverter(): Closure {

        /** @var int|null $default */
        $default = $this->default ?? ($this->nullable ? null : 0);

        return static function (mixed $value) use ($default): ?int {

            $type = gettype($value);

            if (is_string($value) && trim($value) === '') {
                return $default;
            }

            if (is_scalar($value)) {
                $value = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            }

            if (is_null($value)) {
                return $default;
            }

            return is_int($value) ? $value : throw new HydratorException("Cannot convert $type value to int");

        };
    }


}