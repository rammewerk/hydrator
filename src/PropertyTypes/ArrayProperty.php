<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use JsonException;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use Rammewerk\Component\Hydrator\Hydrator;
use Throwable;

final class ArrayProperty extends PropertyHandler {

    /** @var class-string|null */
    public ?string $mapEntity = null;



    protected function getConverter(): Closure {

        $entity = $this->mapEntity;
        $default = $this->default ?? ($this->nullable ? [] : null);

        return static function (mixed $value) use ($entity, $default): ?array {


//            // Array of entities
//            try {
//                $hydrator = new Hydrator($mapEntity);
//                /** @var array<int|string, mixed> $value */
//                foreach ($value as $k => $v) {
//                    if (is_array($v)) {
//                        $value[$k] = $hydrator->hydrate($v);
//                    } elseif ($v instanceof $entity) {
//                        // keep as-is
//                    } else {
//                        throw new HydratorException("Array element at '$k' is not array nor $entity");
//                    }
//                }
//                /** @var array $value */
//                return $value;
//            } catch (Throwable $e) {
//                throw new HydratorException('Unable to hydrate array of ' . $entity . ': ' . $e->getMessage(), $e->getCode(), $e);
//            }


            if ($entity && is_array($value)) {
                try {
                    $hydrator = new Hydrator($entity);
                    /** @var array<int|string, mixed> $value */
                    foreach ($value as $k => $v) {
                        if ($v instanceof $entity) continue;
                        $value[$k] = is_array($v)
                            /** @phpstan-ignore-next-line Ignore the type of array */
                            ? $hydrator->hydrate($v)
                            : throw new HydratorException("Array element at '$k' is not array nor $entity");
                    }
                } catch (Throwable $e) {
                    throw new HydratorException('Unable to hydrate child element of type: ' . $entity, 0, $e);
                }
            }

            if (is_array($value)) return $value;
            if (is_object($value)) return (array)$value;

            if (is_string($value) && !empty($value)) {
                try {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($value)) return $value;
                } catch (JsonException $e) {
                    throw new HydratorException('Unable to convert string to array', $e->getCode(), $e);
                }
            }

            return $default;
        };

    }


}