<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use DateTime;
use DateTimeImmutable;
use Exception;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class DateTimeProperty extends PropertyHandler {


    protected function getConverter(): Closure {

        $nullable = $this->nullable;

        return static function (mixed $value) use ($nullable): ?DateTime {

            if ($value instanceof DateTime) return $value;
            if ($value instanceof DateTimeImmutable) return DateTime::createFromImmutable($value);

            if ($nullable && (is_null($value) || $value === '')) {
                return null;
            }

            # Check if it is int and timestamp
            if (is_int($value)) {
                $value = "@$value";
            }

            if (!is_string($value)) {
                throw new HydratorException('DateTime must be a string, timestamp or DateTime object. ' . gettype($value) . ' given');
            }

            try {
                return new DateTime($value);
            } catch (Exception $e) {
                throw new HydratorException("Unable to convert '$value' to DateTime: " . $e->getMessage(), $e->getCode(), $e);
            }
        };
    }


}