<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use BackedEnum;
use Closure;

final class UndefinedProperty extends PropertyHandler {

    protected function getConverter(): Closure {
        $type = $this->type;
        return static function (mixed $value) use ($type): mixed {

            if ($type && is_subclass_of($type, BackedEnum::class)) {
                $property = new BackEnumProperty();
                $property->type = $type;
                $property->generateConverter();
                return $property->convert($value);
            }

            if ($type && enum_exists($type)) {
                $property = new EnumProperty();
                $property->type = $type;
                $property->generateConverter();
                return $property->convert($value);
            }


            if ($type && class_exists($type)) {
                $property = new ClassProperty();
                $property->type = $type;
                $property->generateConverter();
                return $property->convert($value);
            }
            return $value;
        };
    }


}