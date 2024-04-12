<?php

namespace Rammewerk\Component\Router\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Component\Hydrator\Tests\EntityTest;
use Rammewerk\Component\Router\Error\RouteAccessDenied;
use Rammewerk\Component\Router\Router;

class HydratorTest extends TestCase {

    private function getEntitySource(): array {
        return [
            'string' => 'hello',
            'nullableString' => null
        ];
    }

    public function testEntityHydration(): void {

        $source = $this->getEntitySource();
        $hydrator = new Hydrator( EntityTest::class );
        $hydrated = $hydrator->hydrate( $source );

        $this->ass

    }

}
