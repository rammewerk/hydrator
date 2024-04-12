<?php

namespace Rammewerk\Component\Hydrator;

use DateTime;
use Exception;
use JsonException;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

/**
 * This class will hydrate a given model with data from a repository.
 *
 * @template TEntity of object
 */
class Hydrator {

    /** @var ReflectionClass<TEntity> */
    private ReflectionClass $class;

    /** @var array<string, ReflectionProperty> */
    private array $properties = [];

    /** @var array<string, string> */
    private array $propertyTypes = [];


    /**
     * Constructor for hydration class
     *
     * Will cache data about the model and its properties, to hydrate the model quickly. Also, the constructor will
     * validate the repository model, so we can throw errors early if the model is not set correctly.
     * @param class-string<TEntity> $entity
     */
    public function __construct(string $entity) {

        try {

            $this->class = new ReflectionClass( $entity );

            foreach( $this->class->getProperties( ReflectionProperty::IS_PUBLIC ) as $property ) {

                $type = $property->getType();
                $name = $property->getName();

                if( $type === null ) {
                    throw new HydratorException( sprintf( 'The %s.%s property is not typed.', $this->class->getShortName(), $name ) );
                }

                if( !$type instanceof ReflectionNamedType ) {
                    throw new HydratorException( $name . ' is not a reflection named type' );
                }

                $this->properties[$name] = $property;
                $this->propertyTypes[$name] = $type->getName();

            }

            if( empty( $this->properties ) ) {
                throw new HydratorException( "$entity has no public properties an cannot be hydrated" );
            }

        } catch( ReflectionException $e ) {
            throw new HydratorException( 'Invalid hydration class', $e->getCode(), $e );
        }

    }


    /**
     * Hydrate the model
     *
     * @param array<string, mixed> $data
     *
     * @return TEntity
     */
    public function hydrate(array $data) {

        if( empty( $data ) ) {
            throw new HydratorException( 'Empty data for hydration is not allowed' );
        }

        try {
            $model = $this->class->newInstanceWithoutConstructor();
        } catch( ReflectionException $e ) {
            throw new HydratorException( 'Unable to generate an instance of the model without constructor.', $e->getCode(), $e );
        }

        foreach( $this->properties as $name => $property ) {

            $type = $this->propertyTypes[$name] ?? throw new HydratorException( "Required property type for $name is not found in cached" );
            $value = $data[$name] ?? null;

            # Clear memory by unsetting the data
            unset( $data[$name] );

            if( is_null( $value ) && $property->hasDefaultValue() ) {
                continue;
            }

            if( is_null( $value ) && !$property->isInitialized( $model ) && !$property->getType()?->allowsNull() ) {

                if( class_exists( $type ) ) {
                    $value = new $type;
                } else {
                    throw new HydratorException( "Property $name in " . $this->class->getShortName() . " does not allow NULL value" );
                }

            }

            if( is_null( $value ) && $property->isInitialized( $model ) ) continue;

            # Type is not string, but we got an empty string here
            if( $type !== 'string' && is_string( $value ) && trim( $value ) === '' ) {
                if( $property->isInitialized( $model ) ) continue;
                if( $property->getType()?->allowsNull() ) $value = null;
            }

            $property->setValue( $model, $this->convertValue( $type, $value, (bool)$property->getType()?->allowsNull() ) );

        }

        return $model;

    }


    private function convertValue(string $type, mixed $value, bool $allowsNull): mixed {

        if( is_string( $value ) ) {

            if( $type === 'DateTime' ) {
                try {
                    return new DateTime( $value );
                } catch( Exception $e ) {
                    throw new HydratorException( 'Unable to construct DateTime', $e->getCode(), $e );
                }
            }

            if( enum_exists( $type ) ) {
                /** @phpstan-ignore-next-line */
                return call_user_func( [$type, 'from'], $value );
            }

            if( $type === 'array' && $value ) {
                try {
                    $value = json_decode( $value, true, 512, JSON_THROW_ON_ERROR );
                } catch( JsonException $e ) {
                    throw new HydratorException( 'Unable to convert string to array', $e->getCode(), $e );
                }
            }

        }

        if( is_scalar( $value ) ) {
            $value = match ($type) {
                'float' => filter_var( $value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE ),
                'bool' => filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ),
                'int' => filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ),
                'string' => (string)$value,
                default => $value
            };

            if( !$allowsNull && is_null( $value ) ) {
                throw new HydratorException( "Property in hydrated object does not allow null" );
            }

            return $value;
        }


        # Hydrate children classes
        if( class_exists( $type ) && !($type instanceof $value) ) {
            try {
                return (new self( $type ))->hydrate( (array)$value );
            } catch( Throwable $e ) {
                throw new HydratorException( "Unable to hydrate child element of type $type: " . $e->getMessage(), $e->getCode(), $e );
            }
        }

        if( $type === 'array' && is_object( $value ) ) {
            return (array)$value;
        }

        return $value;

    }

}