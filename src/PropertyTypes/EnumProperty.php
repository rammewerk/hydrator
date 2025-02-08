<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;


use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use UnitEnum;

final class EnumProperty extends PropertyHandler {

    protected function getConverter(): Closure {
        /** @var class-string<UnitEnum> $enum */
        $enum = $this->type;
        $default = $this->default;
        $nullable = $this->nullable;
        return static function (mixed $value) use ($enum, $default, $nullable) {

            if ($value instanceof $enum) return $value;

            if (is_null($value) || $value === '') {
                if ($default instanceof $enum) return $default;
                if ($nullable) return null;
            }

            if (!is_scalar($value)) {
                throw new HydratorException("Unable to convert non-scalar value to $enum");
            }

            $enumCase = array_filter($enum::cases(), static function (UnitEnum $case) use ($value) {
                return strcasecmp($case->name, (string)$value) === 0;
            });

            return $enumCase
                ? reset($enumCase)
                : throw new HydratorException("Invalid enum value '$value' for '$enum'");

        };
    }


}