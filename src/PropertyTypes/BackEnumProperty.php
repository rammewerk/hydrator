<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use BackedEnum;
use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class BackEnumProperty extends PropertyHandler {

    protected function getConverter(): Closure {
        /** @var class-string<BackedEnum> $type */
        $type = $this->type;
        $default = $this->default;
        $nullable = $this->nullable;
        return static function (mixed $value) use ($type, $default, $nullable) {
            if ($value instanceof $type) return $value;

            if (is_null($value) || $value === '') {
                if ($default instanceof $type) return $default;
                if ($nullable) return null;
            }

            if (!is_scalar($value)) {
                throw new HydratorException("Unable to convert non-scalar value to $type");
            }

            $value = is_string($value) && ctype_digit(trim($value)) ? (int)$value : $value;
            $value = !is_int($value) ? (string)$value : $value;
            return $type::tryFrom($value) ?? throw new HydratorException("Unable to convert value to $type");
        };
    }


}