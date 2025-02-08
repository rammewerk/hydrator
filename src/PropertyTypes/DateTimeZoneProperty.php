<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use DateTimeZone;
use Exception;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class DateTimeZoneProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        return static function (mixed $value): DateTimeZone {
            if (!is_string($value)) throw new HydratorException('DateTimeZone must be a string');
            try {
                return new DateTimeZone($value);
            } catch (Exception $e) {
                throw new HydratorException('Unable to convert value to DateTimeZone: ' . $e->getMessage(), $e->getCode(), $e);
            }
        };
    }


}