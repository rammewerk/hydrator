<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class StringProperty extends PropertyHandler {


    protected function getConverter(): Closure {

        /** @var string|null $default */
        $default = $this->default ?? ($this->nullable ? null : '');

        return static function (mixed $value) use ($default): ?string {

            if ($value === '') {
                return $default;
            }

            if (is_scalar($value)) {
                return (string)$value;
            }

            if (is_null($value)) {
                return $default;
            }

            throw new HydratorException('Cannot convert non-scalar value to string');

        };
    }


}