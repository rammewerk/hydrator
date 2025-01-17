<?php

use Rammewerk\Component\Hydrator\Hydrator;

require __DIR__ . '/vendor/autoload.php';

$hydrator = new Hydrator(\Rammewerk\Component\Hydrator\Tests\Fixture\StringEntity::class);

$stringEntity = $hydrator->hydrate([
    'string' => 'Hello hydrator!',
]);

echo $stringEntity->string;