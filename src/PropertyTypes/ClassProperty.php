<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use Rammewerk\Component\Hydrator\Hydrator;
use Throwable;

final class ClassProperty extends PropertyHandler {


    protected function getConverter(): Closure {
        /** @var class-string $type */
        $type = $this->type;
        return static function (mixed $value) use ($type) {
            if ($value instanceof $type) return $value;
            if (is_array($value) && class_exists($type)) {
                try {
                    /** @var array<string, mixed> $value */
                    return new Hydrator($type)->hydrate($value);
                } catch (Throwable $e) {
                    throw new HydratorException("Unable to hydrate child element of type $type: " . $e->getMessage(), $e->getCode(), $e);
                }
            }
            throw new HydratorException("Unable to convert value to $type");
        };
    }


}