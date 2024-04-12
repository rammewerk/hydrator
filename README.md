# Rammewerk Hydrator - A strongly typed hydrator

The **Rammewerk Hydrator** is a strongly typed hydrator for PHP 8.3+.

## Installation

Install Rammewerk Router via composer:

```bash
composer require rammewerk/hydrator
```

How to use
----

Let's consider a simple entity/dto:

```php
final class Product {
    public string $name = '';
    public string $sku = '';
    public float $price = 0;
    public StatusEnum $status = StatusEnum::draft;
    public ?\DateTime $created_at = null;
}
```

We can populate this from an array:

```php
$data = [
    'name' => 'Some product',
    'sku' => '1020',
    'status' => 'active',
];

$hydrator = new \Rammewerk\Component\Hydrator(Product::class);
$product = $hydrator->hydrate($data);
```

In return, we got a typed entity.

This might be useful to automatically convert data from database to an entity, or from an API etc.

The benefit is many including:

* Use entities and classes to get type safety.
* Use hydrator to automatically map classes.
* Get type-hints from your IDE.
* Better code quality.

## Read only properties

To add readonly properties, they must be initiated through the constructor.

```php
class Entity {

    public function __construct(
        public readonly int $id
    ) {
    }

}
```

## Allowed property types

```php
// Required
public string $prop_required;
// Optional
public string $prop_optional = '';
// Nullable - Hydrator will add null if not defined.
public ?string $property;
// Boolean - accepts also 1, 0, "on", "off", "1", "true"..
public bool $prop = false;
// Integer
public int $prop = 0;
// Float/Number
public float $prop;
// String
public string $prop;
// Array
public array $prop;
// DateTime
public ?\DateTime $prop = null;
// Enums
public Status $status = Statis::draft;
// Classes
public ?AnotherClass $class = null;

```

## Error handling

```php
try {
    $hydrator->hydrate(...);
} catch (\Rammewerk\Component\Hydrator\Error\HydratorException $e) {
    // It's an error
}
```
