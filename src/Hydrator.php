<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator;

use BackedEnum;
use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * A fast, reflection-based hydrator/mapper.
 *
 * @author Kristoffer Follestad <kristoffer@bonsy.no>
 *
 * @template T of object
 */
final class Hydrator {

    /** @var ReflectionClass<T> */
    private readonly ReflectionClass $class;

    /** @var T|null */
    private ?object $instance = null;

    /** @var null|Closure(array<string, mixed>):mixed[] */
    private ?Closure $parameters = null;

    private ?Closure $requiredProperties = null;

    /** @var array<string, ReflectionProperty> List of all properties in the class */
    private array $properties = [];

    /** @var array<string, Closure> Converters for each property */
    private array $converters = [];

    /** @var array<string, mixed> Default values for each property */
    private array $defaultValues = [];

    /** @var array<string, class-string> Array mappings */
    private array $arrayMappings = [];



    /**
     * Constructor for hydration class
     *
     * Will cache data about the model and its properties, to hydrate the model quickly. Also, the constructor will
     * validate the repository model, so we can throw errors early if the model is not set correctly.
     *
     * @param class-string<T>|T $entity
     *
     * @throws HydratorException
     */
    public function __construct(object|string $entity) {

        if (!is_string($entity)) {
            $this->instance = clone $entity;
        }

        try {
            $this->class = new ReflectionClass($this->instance ?? $entity);
        } catch (ReflectionException $e) { // @phpstan-ignore-line
            throw new HydratorException('Invalid hydration class', $e->getCode(), $e);
        }

        if (is_null($this->instance) && $constructor = $this->class->getConstructor()) {
            $this->parameters = $this->getConstructorArguments(
                $this->parseParameters($constructor->getParameters()),
            );
        }

        $this->setRequiredProperties();
        $this->setPropertyConverters();

    }



    /**
     * Hydrate the model
     *
     * @param array<string, mixed> $data
     *
     * @return T
     */
    public function hydrate(array $data) {

        $instance = $this->instance ? clone $this->instance : $this->instance($data);

        if ($this->requiredProperties) {
            ($this->requiredProperties)($instance, $data);
        }

        /* Remove null-values from the dataset, use default values instead (which might be null) */
        $data = array_filter($data, static fn($v) => !is_null($v));

        /** Only keep properties that are defined in the class, and include default values */
        $data = array_merge($this->defaultValues, array_intersect_key($data, $this->defaultValues));

        foreach ($this->properties as $name => $property) {

            /**
             * Do not overwrite set values if initialized with another value than the default
             * This is to prevent overwriting values that are set by the constructor
             */
            if ($property->hasDefaultValue() && $property->getValue($instance) !== $property->getDefaultValue()) {
                continue;
            }

            if (!array_key_exists($name, $data)) $data[$name] = null;

            /* Quick check if the property is initialized and should use the initialized value */
            if ((is_null($data[$name]) || $data[$name] === '') && $property->isInitialized($instance)) {
                continue;
            }

            if (is_null($data[$name])) {
                $property->setValue($instance, null);
                continue;
            }

            try {
                $property->setValue($instance, $this->converters[$name]($data[$name]));
            } catch (HydratorException $e) {
                throw new HydratorException(
                    "Unable to hydrate property $name in class " . $this->class->getName() . ": " . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }

        }

        return $instance;

    }



    /**
     * @param string $property
     * @param class-string $class
     *
     * @return Hydrator<T>
     * @immutable
     */
    public function mapArray(string $property, string $class): Hydrator {
        $c = clone $this;
        $c->arrayMappings[$property] = $class;
        $c->setPropertyConverters();
        return $c;
    }



    /**
     * @param ReflectionParameter[] $parameters
     *
     * @return array{0: non-empty-string, 1:bool, 2:mixed}[]
     */
    private function parseParameters(array $parameters): array {
        return array_map(static function (ReflectionParameter $parameter): array {
            try {
                return [
                    $parameter->getName(),
                    $parameter->isPromoted(),
                    $parameter->isOptional() ? $parameter->getDefaultValue() : null,
                ];
            } catch (ReflectionException $e) {
                throw new HydratorException('Unable to parse parameter: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }, $parameters);
    }



    /**
     * Create an array of arguments for the constructor
     *
     * @param array{0: non-empty-string, 1:bool, 2:mixed}[] $parameters
     *
     * @return Closure
     */
    private function getConstructorArguments(array $parameters): Closure {
        return static function (array &$data) use ($parameters): array {
            $args = [];
            foreach ($parameters as [$name, $promoted, $default]) {
                if ($promoted && array_key_exists($name, $data)) {
                    $args[] = $data[$name] ?? $default;
                    unset($data[$name]);
                } else {
                    $args[] = $default;
                }
            }
            return $args;
        };
    }



    private function setRequiredProperties(): void {

        $defaults = $this->class->getDefaultProperties();
        $properties = array_filter(
            $this->class->getProperties(),
            static function (\ReflectionProperty $property) use ($defaults) {

                if ($property->isPromoted()) {
                    return false; // Promoted type, will be handled by the constructor
                }

                if (isset($defaults[$property->getName()])) {
                    return false; // Default value exists
                }

                if ($property->getType() && $property->getType()->allowsNull()) {
                    return false; // Nullable type
                }

                return true; // Required if no default and not nullable

            });

        $className = $this->class->getName();

        $this->requiredProperties = static function (object $instance, array $data) use ($properties, $className): void {
            foreach ($properties as $property) {
                if (array_key_exists($property->getName(), $data)) continue;
                if ($property->isInitialized($instance)) continue;
                throw new HydratorException("Property '{$property->getName()}' is required when hydrating class '$className'");
            }
        };

    }



    /**
     * @param array<string, mixed> $data
     *
     * @return T
     * @throws HydratorException
     */
    private function instance(array &$data) {
        try {
            if ($parameterClosure = $this->parameters) {
                return $this->class->newInstanceArgs($parameterClosure($data));
            }
            return $this->class->newInstance();
        } catch (ReflectionException $e) {
            throw new HydratorException('Unable to generate an instance of entity: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Handle types
    |--------------------------------------------------------------------------
    */

    private function handleArray(string $property_name): Closure {
        $mapEntity = $this->arrayMappings[$property_name] ?? null;
        return static function (mixed $value) use ($mapEntity): array {

            if ($mapEntity && is_array($value)) {
                try {
                    $childMapper = new Hydrator($mapEntity);
                    return array_map(static function ($v) use ($childMapper) {
                        /** @phpstan-ignore-next-line Ignore type of array */
                        if (is_array($v)) return $childMapper->hydrate($v);
                        throw new HydratorException('Not an array');
                    }, $value);
                } catch (\Throwable $e) {
                    throw new HydratorException('Unable to hydrate child element of type: ' . $mapEntity);
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



    private function handleString(): Closure {
        return static function (mixed $value): ?string {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to string');
            return (string)$value;
        };
    }



    private function handleInt(): Closure {
        return static function (mixed $value): int {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to int');
            return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw new HydratorException('Unable to convert value of ' . gettype($value) . ' to int');
        };
    }



    private function handleFloat(): Closure {
        return static function (mixed $value): float {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to float');
            return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
                ?? throw new HydratorException('Unable to convert value of ' . gettype($value) . ' to float');
        };
    }



    private function handleBool(): Closure {
        return static function (mixed $value): bool {
            if (!is_scalar($value)) throw new HydratorException('Cannot convert non-scalar value to bool');
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw new HydratorException('Unable to convert value of ' . gettype($value) . ' to bool');
        };
    }



    private function handleDateTime(): Closure {
        return static function (mixed $value): DateTime {
            if ($value instanceof DateTime) return $value;
            # Check if it is int and timestamp
            if (is_int($value)) $value = "@$value";
            if (!is_string($value)) throw new HydratorException('DateTime must be a string, timestamp or DateTime object');
            try {
                return new DateTime($value);
            } catch (Exception $e) {
                throw new HydratorException('Unable to convert value to DateTime: ' . $e->getMessage(), $e->getCode(), $e);
            }
        };
    }



    private function handleDateTimeImmutable(): Closure {
        return static function (mixed $value): DateTimeImmutable {
            if ($value instanceof DateTimeImmutable) return $value;
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



    private function handleDateTimeZone(): Closure {
        return static function (mixed $value): DateTimeZone {
            if (!is_string($value)) throw new HydratorException('DateTimeZone must be a string');
            try {
                return new DateTimeZone($value);
            } catch (Exception $e) {
                throw new HydratorException('Unable to convert value to DateTimeZone: ' . $e->getMessage(), $e->getCode(), $e);
            }
        };
    }



    /**
     * @param class-string<BackedEnum> $type
     *
     * @return Closure
     */
    private function handleEnum(string $type): Closure {
        return static function (mixed $value) use ($type) {
            if ($value instanceof $type) return $value;
            if (is_string($value) || is_int($value)) {
                return $type::from($value);
            }
            throw new HydratorException("Unable to convert value to $type");
        };
    }



    private function handleClass(string $type): Closure {
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



    protected function handleUnresolved(string|null $type): Closure {
        return function (mixed $value) use ($type): mixed {
            /** @phpstan-ignore-next-line */
            if ($type && enum_exists($type)) return $this->handleEnum($type)($value);
            if ($type && class_exists($type)) return $this->handleClass($type)($value);
            return $value;
        };
    }



    /**
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $getTypes
     *
     * @return Closure
     */
    private function handleUnionType(array $getTypes): Closure {
        return static function (mixed $value) use ($getTypes) {
            foreach ($getTypes as $type) {
                if ($type instanceof ReflectionIntersectionType) {
                    foreach ($type->getTypes() as $ic) {
                        if (!$ic instanceof ReflectionNamedType) {
                            continue 2;
                        }
                        $icName = $ic->getName();
                        if (!$value instanceof $icName) {
                            continue 2;
                        }
                    }
                    return $value;
                }

                $typeName = $type->getName();
                $checkFn = "is_$typeName";


                if (function_exists($checkFn) && $checkFn($value)) {
                    return $value;
                }

                if ($value instanceof $typeName) {
                    return $value;
                }
            }
            throw new HydratorException('Given value is not of any of the defined union types');
        };
    }



    /**
     * @param ReflectionType[] $getTypes
     *
     * @return Closure
     */
    private function handleIntersectionType(array $getTypes): Closure {
        return static function (mixed $value) use ($getTypes) {
            $isOfAllTypes = true;
            foreach ($getTypes as $type) {
                if (!$type instanceof ReflectionNamedType) {
                    $isOfAllTypes = false;
                } else {
                    $typeName = $type->getName();
                    if (!$value instanceof $typeName) {
                        $isOfAllTypes = false;
                    }
                }


            }
            if ($isOfAllTypes) {
                return $value;
            }
            throw new HydratorException('Given value is not of all of the defined intersection types');
        };
    }



    private function setPropertyConverters(): void {
        foreach ($this->class->getProperties() as $property) {

            /** Promoted type, will be handled by the constructor */
            if ($property->isPromoted()) continue;

            $name = $property->getName();
            $type = $property->getType();
            $this->properties[$name] = $property;

            /** Set required and default values */
            $this->defaultValues[$name] = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

            if ($type instanceof ReflectionNamedType) {

                $typeName = $type->getName();

                $this->converters[$name] = match ($typeName) {
                    'array'             => $this->handleArray($name),
                    'string'            => $this->handleString(),
                    'int'               => $this->handleInt(),
                    'float'             => $this->handleFloat(),
                    'bool'              => $this->handleBool(),
                    'DateTime'          => $this->handleDateTime(),
                    'DateTimeImmutable' => $this->handleDateTimeImmutable(),
                    'DateTimeZone'      => $this->handleDateTimeZone(),
                    default             => $this->handleUnresolved($typeName),
                };

            } else if ($type instanceof ReflectionUnionType) {
                $this->converters[$name] = $this->handleUnionType($type->getTypes());
            } else if ($type instanceof ReflectionIntersectionType) {
                $this->converters[$name] = $this->handleIntersectionType($type->getTypes());
            } else {
                $this->converters[$name] = $this->handleUnresolved(null);
            }

        }
    }



}
