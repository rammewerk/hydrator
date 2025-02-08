<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;

final class IntersectionProperty extends PropertyHandler {

    /** @var string[] */
    public array $types = [];



    protected function getConverter(): Closure {
        $types = $this->types;
        return static function (mixed $value) use ($types) {
            $isOfAllTypes = true;
            foreach ($types as $type) {
                if (!$value instanceof $type) {
                    $isOfAllTypes = false;
                }
            }
            if ($isOfAllTypes) {
                return $value;
            }
            throw new HydratorException('Given value is not of all of the defined intersection types');
        };
    }


}