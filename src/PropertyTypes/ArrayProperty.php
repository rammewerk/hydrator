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

            if ($entity && is_array($value)) {
                try {
                    $hydrator = new Hydrator($entity);
                    /** @var array<int|string, mixed> $value */
                    foreach ($value as $k => $v) {
                        if ($v instanceof $entity) continue;
                        if (is_array($v)) {
                            $value[$k] = $hydrator->hydrate($v);
                        } else if (is_object($v)) {
                            $value[$k] = $hydrator->hydrate((array)$v);
                        } else {
                            throw new HydratorException("Array element at '$k' is not array nor $entity");
                        }
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