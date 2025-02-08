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
        return static function (mixed $value) use ($enum) {
            if (is_string($value) && $value !== '') {
                $enumCase = array_filter($enum->type::cases(), static fn($case) => strcasecmp($case->name, $value) === 0);
                return $enumCase
                    ? reset($enumCase)
                    : throw new HydratorException("Invalid enum value '$value' for '$enum'");
            }
            throw new HydratorException("Unable to convert value to $enum");
        };
    }


}