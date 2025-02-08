<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator;

use Closure;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use Rammewerk\Component\Hydrator\PropertyTypes\ArrayProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\BoolProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\DateTimeImmutableProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\DateTimeProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\DateTimeZoneProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\FloatProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\IntersectionProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\IntProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\PropertyHandler;
use Rammewerk\Component\Hydrator\PropertyTypes\StringProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\UndefinedProperty;
use Rammewerk\Component\Hydrator\PropertyTypes\UnionTypeProperty;
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

    /** @var T|null
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    private ?object $instance = null;

    /** @var null|Closure(array<string, mixed>, ?callable):array<mixed> */
    private ?Closure $parameters = null;

    /** @var array<string, PropertyHandler> */
    private array $properties = [];



    /**
     * Constructor for hydration class
     *
     * Will cache data about the model and its properties, to hydrate the model quickly. Also, the constructor will
     * validate the repository model, so we can throw errors early if the model is not set correctly.
     *
     * @param class-string<T>|T $entity
     *
     * @throws HydratorException
     * @noinspection PhpDocSignatureInspection
     */
    public function __construct(object|string $entity) {

        if (!is_string($entity)) {
            $this->instance = clone $entity;
        }

        try {
            $this->class = new ReflectionClass($this->instance ?? $entity);
        } catch (ReflectionException $e) { // @phpstan-ignore-line
            throw new HydratorException('Invalid hydration class', 0, $e);
        }

        if (is_null($this->instance) && $constructor = $this->class->getConstructor()) {
            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                try {
                    $parameters[] = $this->getPropertyHandler($parameter);
                } catch (ReflectionException $e) {
                    throw new HydratorException('Unable to parse parameter: ' . $e->getMessage(), $e->getCode(), $e);
                }
            }
            $this->parameters = $this->getConstructorArguments($parameters);
        }

        foreach ($this->class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            /** Promoted type, will be handled by the constructor */
            if ($property->isPromoted()) continue;
            try {
                $this->properties[$property->getName()] = $this->getPropertyHandler($property);
            } catch (ReflectionException $e) {
                throw new HydratorException('Unable to define property: ' . $property->getName(), $e->getCode(), $e);
            }
        }

    }



    /**
     * Hydrate the model
     *
     * @param array<string, mixed> $data
     *
     * @return T
     */
    public function hydrate(array $data = [], ?callable $callback = null) {

        $instance = $this->instance ? clone $this->instance : $this->instance($data, $callback);

        /* Remove null-values from the dataset, use default values instead (which might be null) */
        $data = array_filter($data, static fn($v) => !is_null($v));

        foreach ($this->properties as $property) {

            // Copy value or get it from the callback
            $value = $data[$property->name] ?? ($callback ? $callback($property) : null);

            // Free up memory
            unset($data[$property->name]);

            // Quick check if the property is initialized and should use the initialized value
            if ((is_null($value) || $value === '') && $instance->{$property->name} !== $property->default) {
                continue;
            }

            try {
                $instance->{$property->name} = !is_null($value)
                    ? $property->convert($value)
                    : $property->default;

            } catch (Throwable $e) {
                throw new HydratorException(
                    "Unable to hydrate property $property->name in class $property->className: " . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }

        }

        return $instance;

    }



    /**
     * @param string $property_name
     * @param class-string $class
     *
     * @return Hydrator<T>
     * @immutable
     */
    public function mapArray(string $property_name, string $class): Hydrator {
        if (!isset($this->properties[$property_name])) {
            throw new HydratorException("Trying to map array on a property that does not exist: $property_name");
        }
        $property = &$this->properties[$property_name];
        if ($property instanceof ArrayProperty) {
            $property->mapEntity = $class;
            $property->generateConverter();
            return $this;
        }
        throw new HydratorException("Trying to map array on a property that is not an array: $property_name");
    }



    /**
     * Create an array of arguments for the constructor
     *
     * @param PropertyHandler[] $parameters
     *
     * @return Closure(array<string, mixed>, ?callable):array<mixed>
     */
    private function getConstructorArguments(array $parameters): Closure {
        return static function (array &$data, ?callable $callback) use ($parameters): array {
            return array_map(static function ($param) use (&$data, $callback) {

                if ($param->promoted && $callback) {
                    return $param->convert($callback($param));
                }

                if ($param->promoted && array_key_exists($param->name, $data)) {
                    $value = $param->convert($data[$param->name]);
                    unset($data[$param->name]);
                    return $value;
                }

                return $param->default;
            }, $parameters);
        };
    }



    /**
     * @param array<string, mixed> $data
     *
     * @return T
     * @throws HydratorException
     */
    private function instance(array &$data, ?callable $callback) {
        try {
            if ($parameterClosure = $this->parameters) {
                return $this->class->newInstanceArgs($parameterClosure($data, $callback));
            }
            return $this->class->newInstance();
        } catch (ReflectionException $e) {
            throw new HydratorException('Unable to generate an instance of entity: ' . $e->getMessage(), 0, $e);
        }
    }



    /**
     * @throws ReflectionException
     */
    public function getPropertyHandler(ReflectionProperty|ReflectionParameter $property): PropertyHandler {

        $handler = $this->getPropertyTypeHandler($property);
        $handler->promoted = $property->isPromoted();
        $handler->className = $this->class->getName();
        $handler->name = $property->getName();
        $handler->nullable = $property->getType() && $property->getType()->allowsNull();

        if ($property instanceof ReflectionParameter) {
            $handler->default = $property->isOptional() ? $property->getDefaultValue() : null;
        } else {
            $handler->default = $property->getDefaultValue();
        }

        $handler->generateConverter();

        return $handler;

    }



    /**
     * @param ReflectionNamedType[]|ReflectionIntersectionType[]|ReflectionType[] $types
     *
     * @return string[]
     */
    public function extractMultipleTypes(array $types): array {
        return array_map(static fn($type) => $type->getName(),
            array_filter($types, static fn($type) => $type instanceof ReflectionNamedType),
        );
    }



    private function getPropertyTypeHandler(ReflectionParameter|ReflectionProperty $property): PropertyHandler {

        if ($property->getType() instanceof ReflectionNamedType) {
            $handler = match ($property->getType()->getName()) {
                'array'             => new ArrayProperty(),
                'string'            => new StringProperty(),
                'float'             => new FloatProperty(),
                'int'               => new IntProperty(),
                'bool'              => new BoolProperty(),
                'DateTime'          => new DateTimeProperty(),
                'DateTimeImmutable' => new DateTimeImmutableProperty(),
                'DateTimeZone'      => new DateTimeZoneProperty(),
                default             => new UndefinedProperty(),
            };
            $handler->type = $property->getType()->getName();
            return $handler;
        }

        if ($property->getType() instanceof ReflectionUnionType) {
            $handler = new UnionTypeProperty();
            $handler->types = $this->extractMultipleTypes($property->getType()->getTypes());
            return $handler;
        }

        if ($property->getType() instanceof ReflectionIntersectionType) {
            $handler = new IntersectionProperty();
            $handler->types = $this->extractMultipleTypes($property->getType()->getTypes());
            return $handler;
        }

        return new UndefinedProperty();
    }


}
