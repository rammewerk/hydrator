<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator;

use Closure;
use Rammewerk\Component\Hydrator\Attribute\ArrayOf;
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
use ReflectionMethod;
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

        if (is_null($this->instance) && $const = $this->class->getConstructor()) {
            $this->setParameterHandler($const);
        }

        foreach ($this->class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            /** Promoted types are handled by constructor | Static properties are not handled */
            if ($property->isPromoted() || $property->isStatic()) continue;
            $this->properties[$property->getName()] = $this->getPropertyHandler($property);
        }

    }



    /**
     * @param ReflectionMethod $const
     *
     * @return void
     */
    private function setParameterHandler(ReflectionMethod $const): void {

        $parameters = array_map(function ($parameter) {
            return $this->getPropertyHandler($parameter);
        }, $const->getParameters());

        $this->parameters = $this->getConstructorArguments($parameters);

    }



    /**
     * Hydrate the model
     *
     * @param array<string, mixed> $data
     *
     * @return T
     */
    public function hydrate(array $data = [], ?callable $callback = null) {

        /* Remove null-values from the dataset, use default values instead (which might be null) */
        $data = array_filter($data, static fn($v) => !is_null($v));

        $instance = $this->instance ? clone $this->instance : $this->instance($data, $callback);

        foreach ($this->properties as $property) {

            // Get given value from dataset, or use the callback
            $value = $data[$property->name] ?? ($callback ? $callback($property) : null);

            // Free up memory
            unset($data[$property->name]);

            // All values should be optional if not set in the constructor
            // So with an empty value we can skip the rest of the checks
            if (is_null($value) || $value === '') {
                continue;
            }

            try {
                $instance->{$property->name} = $property->convert($value);
            } catch (Throwable $e) {
                throw new HydratorException(
                    "Unable to hydrate '$property->name' in $property->className: " . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }

        }

        return $instance;

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



    private function getPropertyHandler(ReflectionProperty|ReflectionParameter $property): PropertyHandler {

        $handler = $this->getPropertyTypeHandler($property);

        $handler->className = $this->class->getName();
        $handler->name = $property->getName();
        $handler->promoted = $property->isPromoted();
        $handler->nullable = $property->getType() && $property->getType()->allowsNull();

        try {
            if ($property instanceof ReflectionParameter) {
                $handler->default = $property->isOptional() ? $property->getDefaultValue() : null;
            } else {
                $handler->default = $property->getDefaultValue();
            }
        } catch (ReflectionException $e) {
            throw new HydratorException('Unable to get default value: ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (($property instanceof ReflectionProperty) && $handler instanceof ArrayProperty) {
            $arrayOfAttribute = $property->getAttributes(ArrayOf::class)[0] ?? null;
            if ($arrayOfAttribute) {
                /** @var ArrayOf $arr */
                $arr = $arrayOfAttribute->newInstance();
                $handler->mapEntity = $arr->class;
            }
        }

        $handler->generateConverter();

        return $handler;

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



    /**
     * @param ReflectionNamedType[]|ReflectionIntersectionType[]|ReflectionType[] $types
     *
     * @return string[]
     */
    private function extractMultipleTypes(array $types): array {
        return array_map(static fn($type) => $type->getName(),
            array_filter($types, static fn($type) => $type instanceof ReflectionNamedType),
        );
    }



    /**
     * @param string $jsonData
     *
     * @return T
     */
    public function hydrateFromJson(string $jsonData) {
        try {
            $data = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                /** @var array<string, mixed> $data */
                return $this->hydrate($data);
            }
            throw new HydratorException('Invalid JSON: Not an array');
        } catch (\JsonException $e) {
            throw new HydratorException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
    }


}
