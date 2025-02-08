<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use DateTimeZone;
use Exception;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class DateTimeZoneProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        $nullable = $this->nullable;
        return static function (mixed $value) use ($nullable): ?DateTimeZone {

            if ($value instanceof DateTimeZone) return $value;

            if ($nullable && (is_null($value) || $value === '')) {
                return null;
            }

            if (!is_string($value)) {
                throw new HydratorException('DateTimeZone must be a string. ' . gettype($value) . ' given');
            }

            try {
                return new DateTimeZone($value);
            } catch (Exception $e) {
                throw new HydratorException('Unable to convert value to DateTimeZone: ' . $e->getMessage(), $e->getCode(), $e);
            }

        };
    }


}