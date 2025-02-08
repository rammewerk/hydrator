<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class UnionTypeProperty extends PropertyHandler {

    /** @var string[] */
    public array $types = [];



    protected function getConverter(): Closure {
        $types = $this->types;
        return static function (mixed $value) use ($types) {
            foreach ($types as $type) {
                $checkFn = "is_$type";
                if (function_exists($checkFn) && $checkFn($value)) {
                    return $value;
                }
                if ($value instanceof $type) {
                    return $value;
                }
            }
            throw new HydratorException('Given value is not of any of the defined union types');
        };
    }


}