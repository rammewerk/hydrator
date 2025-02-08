<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class BoolProperty extends PropertyHandler {


    protected function getConverter(): Closure {

        /** @var bool|null $default */
        $default = $this->default ?? ($this->nullable ? null : false);

        return static function (mixed $value) use ($default): ?bool {

            $type = gettype($value);

            if (is_string($value) && trim($value) === '') {
                return $default;
            }

            if (is_scalar($value)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }

            if (is_null($value)) {
                return $default;
            }

            return is_bool($value)
                ? $value
                : throw new HydratorException("Cannot convert $type to bool");

        };

    }


}