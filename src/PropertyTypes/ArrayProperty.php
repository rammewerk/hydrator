<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use JsonException;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use Rammewerk\Component\Hydrator\Hydrator;
use Throwable;

final class ArrayProperty extends PropertyHandler {

    /** @var class-string */
    public string $mapEntity = '';



    protected function getConverter(): Closure {
        $mapEntity = $this->mapEntity;
        return static function (mixed $value) use ($mapEntity): array {

            if ($mapEntity && is_array($value)) {
                try {
                    $childMapper = new Hydrator($mapEntity);
                    return array_map(static function ($v) use ($childMapper) {
                        /** @phpstan-ignore-next-line Ignore type of array */
                        if (is_array($v)) return $childMapper->hydrate($v);
                        throw new HydratorException('Not an array');
                    }, $value);
                } catch (Throwable $e) {
                    throw new HydratorException('Unable to hydrate child element of type: ' . $mapEntity, 0, $e);
                }

            }

            if (is_array($value)) return $value;
            if (is_object($value)) return (array)$value;


            if (is_string($value) && !empty($value)) {
                try {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new HydratorException('Unable to convert string to array', $e->getCode(), $e);
                }
            }

            throw new HydratorException('Given value must be type array, object or string. Given value was of type ' . gettype($value));
        };
    }


}