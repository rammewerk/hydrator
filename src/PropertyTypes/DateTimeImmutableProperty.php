<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use DateTime;
use DateTimeImmutable;
use Exception;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class DateTimeImmutableProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        return  static function (mixed $value): DateTimeImmutable {
            if ($value instanceof DateTimeImmutable) return $value;
            if ($value instanceof DateTime) return DateTimeImmutable::createFromMutable($value);
            # Check if it is int and timestamp
            if (is_int($value)) {
                $value = "@$value";
            }
            if (!is_string($value)) throw new HydratorException('DateTime must be a string, timestamp or DateTime object');
            try {
                return new DateTimeImmutable($value);
            } catch (Exception $e) {
                throw new HydratorException('Unable to convert value to DateTimeImmutable: ' . $e->getMessage(), $e->getCode(), $e);
            }
        };
    }


}