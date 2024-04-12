<?php

namespace Rammewerk\Component\Hydrator\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Hydrator\Hydrator;

class HydratorTest extends TestCase {

    private function getEntitySource(): array {
        return [
            'id' => 12,
            'string' => 'hello',
            'nullableString' => null,
            'integer' => '2',
            'boolean' => 'false',
        ];
    }

    public function testEntityHydration(): void {
        $source = $this->getEntitySource();
        $hydrator = new Hydrator( EntityClass::class );
        $hydrated = $hydrator->hydrate( $source );
        $this->assertSame( $source['id'], $hydrated->id );
        $this->assertNull( $hydrated->nullable);
        $this->assertSame( $source['string'], $hydrated->string );
        $this->assertNull( $hydrated->nullableString );
        $this->assertIsInt( $hydrated->integer );
        $this->assertSame( $hydrated->integer, 2 );
        $this->assertFalse( $hydrated->boolean );
    }

}
