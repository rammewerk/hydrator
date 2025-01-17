# Rammewerk Hydrator - A strongly typed hydrator

The **Rammewerk Hydrator** is a fast, reflection-based hydrator for PHP 8.4+, built to effortlessly map arrays onto PHP
objects with minimal configuration.

It’s not an ORM - no annotations or heavy frameworks - just a simple yet powerful mapper. It even includes a
lazy-loading HydratorCollection for handling large datasets.

## Installation

```bash
composer require rammewerk/hydrator
```

Basic usage
----

Let's consider a simple entity/dto:

```php
final class Product {
    public string $name = '';
    public ?string $sku = null;
    public float $price = 0;
    public StatusEnum $status = StatusEnum::draft;
    public ?\DateTime $created_at = null;
    public ?ProductDetails $details = null;
}
```

We can populate this from an array:

```php
$data = [
    'name' => 'Some product',
    'sku' => '1020',
    'status' => 'active',
    'created_at' => '2022-01-01',
    'details' => [
        'color' => 'red',
        'size' => 'large',
    ],
];

$hydrator = new \Rammewerk\Component\Hydrator(Product::class);
$product = $hydrator->hydrate($data);

echo $product->name; // Some product
echo $product->details?->color; // red

```

In return, we got a typed entity.

This might be useful to automatically convert data from database to an entity, or from an API etc.

## Features
- Reflection-based hydrator with caching
- Converts scalars, date/time objects, enums, union/intersection types
- Handles promoted/readonly properties, default/null values
- Automatically hydrates nested objects
- HydratorCollection for quickly hydrating large datasets
- Full IDE autocompletion

Benefits
- Enhanced type safety via entities
- Automatic class mapping from arrays
- Clear IDE type hints
- Improved code quality


## Allowed Property Types

The hydrator ensures each property is set to its declared type by converting raw data as needed (e.g., booleans stored
as integers). It supports:

- **Built-in types**: `string`, `int`, `float`, `bool`, `array`
- **Date types**: `DateTime`, `DateTimeImmutable`, `DateTimeZone`
- **Backed enums** - Using the enum’s `from()` method
- **Union/Intersection types** (e.g., `string|int`, `InterfaceA&InterfaceB`)
- **Classes** (e.g., `public AnotherClass $class`) — nested objects are hydrated if valid data is provided
- **Arrays of classes** via the `mapArray()` method. [More about nested objects here.](#nested-hydration)

## Nested hydration

If the hydrator encounters a property of type class, it will hydrate the class if given data is an array.

```php
class OrderItems {
    public ?Product $product = null;
}

$hydrator = new Hydrator(OrderItems::class)->hydrate([
    'order_id' => 100,
    'product' => [
        'name' => 'Some product',
        'sku' => '1020',
    ],
]);
```

To handle an array of classes, you can use the built-in mapArray method:

```php
class OrderItem {
    /** @var Product[] */
    public array $products = [];
}

$hydrator = new Hydrator(OrderItem::class);

// Notice: the mapArray is an immutable method, so we need to assign the result
$hydrator = $hydrator->mapArray('products', Product::class);

$orderItem = $hydrator->hydrate([
    'order_id' => 100,
    'products' => [
        ['name' => 'Some product', 'sku' => '1020'],
        ['name' => 'Some product', 'sku' => '1020'],
    ],
]);

$orderItem->products[0] instanceof Product; // true

```

## Hydrator Collection

```php
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Component\Hydrator\HydratorCollection;

$collection = new HydratorCollection(new Hydrator(Product::class), $data);

foreach( $collection as $entity ) {
    echo $entity->name;
}

// Get all as an array of entities: Product[]
$entities = $collection->toArray();
```

Collections are lazy-loaded, and will only hydrate the entities when you iterate over them. This is useful for large
datasets, as it will only hydrate the entities that are actually needed. If needed all at once, you can use the
`toArray()` method.

## Constructor parameters

When your class uses constructor parameters, the hydrator automatically resolves them, but with a few rules:

- **Promoted parameters** (`public string $firstName`): Treated like properties, but assigned through the constructor.
  If no default is set, a dataset value is required.
- **Non-promoted parameters** (`string $firstName`): These aren’t set directly from the dataset. They’re constructed
  with their default or null if nullable. If a non-promoted parameter is non-nullable and lacks a default, it throws an
  exception. After construction, a corresponding property (if it exists) is separately hydrated from the dataset.

Example:

```php
public function __construct(
    // Promoted parameters:
    public readonly string $firstName, // Must be in dataset
    public int $age,                   // Must be in dataset
    public ?string $email,             // If not in dataset, defaults to null
    public bool $active = true,        // Optional, uses default if missing
   

    // Non-promoted parameters:
    int $required_id,                  // No default, not nullable => throws exception
    public ?string $lastName,          // Nullable => defaults to null
    int $something = 0,                // Optional => defaults to 0
){}
```

Read more on this below in the section about [object design](#be-mindful-of-object-design).

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

## Why Use the Hydrator?

The hydrator helps convert raw data (e.g., from a database) into typed PHP objects without tedious manual checks. It
enforces strict types, simplifies property access, and makes your code more maintainable.

**Without hydrator**

```php
// Get all users, returns as an array
$user = $this->userRepository->getAll();

foreach($users as $user) {
   if( isset($user['email']) && is_string($user['email']) && ! empty($user['email']) ) {
       $this->sendEmail($user['email']);
   }
}
```

**With Hydrator**

```php
$users = $this->userRepository->getAll();

foreach ($users as $user) {
    if (!$user->email) continue;
    $this->sendEmail($user->email);
}
```

In the hydrator version, your IDE can autocomplete `$user` properties because it knows each user is a `User` object. It
also ensures that only defined properties on the User entity are hydrated - no unexpected data creeping in.

> There’s no hidden `__call()` magic or dynamic property illusions - just strictly typed objects, so you always know
exactly what data you’re working with.

To achieve this, simply return a HydratorCollection from your repository:

```php
class User {
    public string $email = '';
    public string $name = '';
    public int $id = 0;
}
```

```php
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Component\Hydrator\HydratorCollection;

class UserRepository {
    /**
     * @return HydratorCollection<User>
     */
    public function getAll(): HydratorCollection {
        $data = $this->fetchAll("SELECT * FROM `users`");
        return new HydratorCollection(new Hydrator(User::class), $data);
    }
}
```

Now you get a collection of actual `User` objects, each property strictly typed, making your code cleaner and more
reliable.

## Be Mindful of Object Design

While the hydrator is powerful, it doesn’t replace good object design.

Avoid making your classes reliant on the hydrator by declaring uninitialized or non-optional properties without
defaults. If you attempt to access such properties before the hydrator sets them, you’ll encounter exceptions. Instead,
use sensible defaults or optional properties, and ensure your constructor aligns with how data is actually provided. See
below for an example.

### Set reasonable defaults:

```php
public ?string $name = '';
```

The `$name` property is nullable, but it’s initialized with an empty string. This means the hydrator will never set it
to `null`; instead, it will use `''`, even when `null` is provided. To allow null values, initialize the property like
this:

```php
public ?string $name = null;
```

### Define Required Properties via Constructor

```php
public int $id;
```

**Avoid** declaring non-optional properties without initializing them. This is bad practice as it can lead to
uninitialized properties.

**Best Practice**: Define required properties through the constructor. If a property should be read-only, use the
readonly keyword in the constructor’s promoted parameters.

```php
public string $name = ''; // Optional property with a default value

public function __construct(
    public string $id, // Required property
    public readonly string $uid, // Required and read-only property
) {}
```

**Benefits:**

- **Ensures Initialization**: All required properties are set upon object creation.
- **Immutability**: Using readonly enforces that certain properties cannot be modified after initialization.
- **Clear Intent**: Makes the code more predictable and easier to understand.

### Limited Hydration for Non-Promoted Parameters

Non-promoted constructor parameters aren’t automatically hydrated because they often don’t match a specific property or
may be used for computed values. The hydrator’s role is to fill properties, not infer custom constructor logic.

If you need non-promoted parameters, give them defaults, make them nullable, or manually instantiate your object before
hydration:

```php
$entity = new Entity('John Doe', 100);
$hydrator = new \Rammewerk\Component\Hydrator($entity);
```

### Preserving Properties Set by the Constructor

The hydrator won’t overwrite properties already set to values different from their default. This prevents overwriting
values explicitly set by your constructor. For example:

```php
class Entity {
    public int $age = 0;

    public function __construct(int $age = 18) {
        $this->age = $age;
    }
}
```

If the hydrator is given ['age' => 20], it won’t override age (already set to 18 by the constructor). This behavior
ensures constructor-defined values are respected.

## Error handling

All exceptions extend Rammewerk\Component\Hydrator\Error\HydratorException. Catch them to manage invalid data.

```php
try {
    $hydrator->hydrate(...);
} catch (\Rammewerk\Component\Hydrator\Error\HydratorException $e) {
    // It's an error
}
```

Contribution
---------------
If you have any issues or would like to contribute to the development of this library, feel free to open an issue or
pull request.

License
---------------
The Rammewerk Hydrator is open-sourced software licensed under
the [MIT license](http://opensource.org/licenses/MIT).

----

*Keywords: DTO, mapper, data-mapper and populator*