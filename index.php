<?php

use Rammewerk\Component\Hydrator\Hydrator;

require __DIR__ . '/vendor/autoload.php';

$hydrator = new Hydrator(\Rammewerk\Component\Hydrator\Tests\Fixture\StringEntity::class);

$stringEntity = $hydrator->hydrate([
    'string' => 'Hello hydrator!',
]);

echo $stringEntity->string;

#$jsonData = file_get_contents(__DIR__ . '/tests/TestData/orders_1190.json');

#$converter = new \Rammewerk\Component\Hydrator\Generator\JsonToEntityConverter();
#$converter->generate($jsonData, 'WooCommerceOrder', 'Rammewerk\Component\Hydrator\Tests\TestData');

#$hydrator = new Hydrator(\Rammewerk\Component\Hydrator\Tests\TestData\WooCommerceOrder::class);
#$hydrated = $hydrator->hydrateFromJson($jsonData);

//echo '<pre>';
//print_r($hydrated);
//echo '</pre>';
//die;