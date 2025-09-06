<?php

namespace Rammewerk\Component\Hydrator\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Hydrator\Error\HydratorException;
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Component\Hydrator\HydratorCollection;
use Rammewerk\Component\Hydrator\PropertyTypes\PropertyHandler;
use Rammewerk\Component\Hydrator\Tests\Fixture\ArrayMapEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\BooleanEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\ConstructorEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\DateEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\EnumInt;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\EnumString;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectClassA;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectClassB;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectInterface1;
use Rammewerk\Component\Hydrator\Tests\Fixture\EmptyEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\EnumEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\IntersectEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\NumberEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\ReadonlyEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\StringEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\UnionEntity;
use Rammewerk\Component\Hydrator\Tests\Fixture\UntypedEntity;

class HydratorTest extends TestCase {


//    public function testArrayEntity(): void {
//        $hydrator = new Hydrator(ArrayEntity::class);
//        $hydrated = $hydrator->hydrate(['array' => ['a', 'b', 'c']]);
//        $this->assertSame(['a', 'b', 'c'], $hydrated->array);
//    }



    public function testArrayMapEntity(): void {
        $hydrator = new Hydrator(ArrayMapEntity::class);
        $hydrated = $hydrator->hydrate(['intersectClasses' => [
            ['string' => 'first'],
            ['string' => 'second'],
            ['string' => 'third'],
        ]]);
        $this->assertInstanceOf(IntersectClassA::class, $hydrated->intersectClasses[0]);
        $this->assertInstanceOf(IntersectClassA::class, $hydrated->intersectClasses[1]);
        $this->assertInstanceOf(IntersectClassA::class, $hydrated->intersectClasses[2]);
        $this->assertSame('first', $hydrated->intersectClasses[0]->string);
        $this->assertSame('second', $hydrated->intersectClasses[1]->string);
        $this->assertSame('third', $hydrated->intersectClasses[2]->string);


    }



    public function testBooleanEntity(): void {
        $hydrator = new Hydrator(BooleanEntity::class);
        $hydrated = $hydrator->hydrate([
            'boolean'         => true,
            'nullableBoolean' => false,
            'nullableTrue'    => true,
            'nullableFalse'   => false,
        ]);
        $this->assertTrue($hydrated->boolean);
        $this->assertFalse($hydrated->nullableBoolean);
        $this->assertTrue($hydrated->nullableTrue);
        $this->assertFalse($hydrated->nullableFalse);
    }



    public function testConstructorEntity(): void {
        $hydrator = new Hydrator(ConstructorEntity::class);
        $hydrated_1 = $hydrator->hydrate(['promotedString' => 'yes']);
        $this->assertInstanceOf(ConstructorEntity::class, $hydrated_1);
        $this->assertSame(100, $hydrated_1->id);
        $this->assertSame('ok', $hydrated_1->testString);
        $this->assertSame(1, $hydrated_1->intTest);
        $this->assertSame('yes', $hydrated_1->promotedString);
        $hydrated_2 = $hydrator->hydrate(['promotedString' => 'no']);
        $this->assertSame('no', $hydrated_2->promotedString);
    }



    public function testDateEntity(): void {
        $hydrator = new Hydrator(DateEntity::class);
        $hydrated = $hydrator->hydrate([
            'dateTime'          => '2025-01-01',
            'dateTimeZone'      => 'Europe/Amsterdam',
            'dateTimeImmutable' => '2025-01-02',
        ]);
        $dt = new DateTime('2025-01-01');
        $dti = new DateTimeImmutable('2025-01-02');
        $zone = new DateTimeZone('Europe/Amsterdam');
        $this->assertSame($dt->format('Y-m-d'), $hydrated->dateTime->format('Y-m-d'));
        $this->assertSame($dti->format('Y-m-d'), $hydrated->dateTimeImmutable->format('Y-m-d'));
        $this->assertSame($zone->getLocation(), $hydrated->dateTimeZone->getLocation());
        $this->assertInstanceOf(DateTime::class, $hydrated->dateTime);
        $this->assertInstanceOf(DateTimeImmutable::class, $hydrated->dateTimeImmutable);
        $this->assertInstanceOf(DateTimeZone::class, $hydrated->dateTimeZone);
        $hydrated_2 = $hydrator->hydrate([
            'dateTimeOrNull'          => '2025-01-01',
            'dateTimeZoneOrNull'      => 'Europe/Amsterdam',
            'dateTimeImmutableOrNull' => '2025-01-02',
        ]);
        $this->assertSame($dt->format('Y-m-d'), $hydrated_2->dateTimeOrNull?->format('Y-m-d'));
        $this->assertSame($dti->format('Y-m-d'), $hydrated_2->dateTimeImmutableOrNull?->format('Y-m-d'));
        $this->assertSame($zone->getLocation(), $hydrated_2->dateTimeZoneOrNull?->getLocation());
    }



    public function testEmptyEntityHydration(): void {
        $hydrator = new Hydrator(EmptyEntity::class);
        $hydrated = $hydrator->hydrate(['something']);
        $this->assertInstanceOf(EmptyEntity::class, $hydrated);
    }



    public function testEnumEntity(): void {
        $hydrator = new Hydrator(EnumEntity::class);
        $hydrated = $hydrator->hydrate([
            'enumString'       => EnumString::A,
            'enumInt'          => EnumInt::A,
            'enumStringOrNull' => 'b',
            'enumIntOrNull'    => 3,
        ]);
        $this->assertSame(EnumString::A, $hydrated->enumString);
        $this->assertSame(EnumInt::A, $hydrated->enumInt);
        $this->assertSame(EnumString::B, $hydrated->enumStringOrNull);
        $this->assertSame(EnumInt::C, $hydrated->enumIntOrNull);
    }



    public function testEnumEntityCallback(): void {
        $hydrator = new Hydrator(EnumEntity::class);
        $hydrated = $hydrator->hydrate([
            // Will override the callback
            'enumString' => EnumString::A,
        ], function (PropertyHandler $prop) {
            return match ($prop->name) {
                'enumString'       => EnumString::B, // Will be ignored
                'enumInt'          => EnumInt::A,
                'enumStringOrNull' => 'b',
                'enumIntOrNull'    => 3,
                default            => null,
            };
        });
        $this->assertSame(EnumString::A, $hydrated->enumString);
        $this->assertSame(EnumInt::A, $hydrated->enumInt);
        $this->assertSame(EnumString::B, $hydrated->enumStringOrNull);
        $this->assertSame(EnumInt::C, $hydrated->enumIntOrNull);
    }



    public function testIntersectEntity(): void {
        $hydrator = new Hydrator(IntersectEntity::class);
        $hydrated_1 = $hydrator->hydrate([
            'interface' => new IntersectClassB(),
        ]);
        $hydrated_2 = $hydrator->hydrate([
            'interface' => new IntersectClassA(),
        ]);
        $this->assertInstanceOf(IntersectClassB::class, $hydrated_1->interface);
        $this->assertInstanceOf(IntersectInterface1::class, $hydrated_1->interface);
        $this->assertInstanceOf(IntersectClassA::class, $hydrated_2->interface);
    }



    public function testNumberEntity(): void {
        $hydrator = new Hydrator(NumberEntity::class);
        $hydrated = $hydrator->hydrate([
            'integer'         => '100',
            'nullableInteger' => 200,
            'float'           => '300.0',
            'nullableFloat'   => 400.0,
        ]);
        $this->assertSame(100, $hydrated->integer);
        $this->assertSame(200, $hydrated->nullableInteger);
        $this->assertSame(300.0, $hydrated->float);
        $this->assertSame(400.0, $hydrated->nullableFloat);
    }



    public function testReadonlyEntity(): void {
        $hydrator = new Hydrator(ReadonlyEntity::class);
        $hydrated_1 = $hydrator->hydrate(['id' => 100]);
        $hydrated_2 = $hydrator->hydrate(['id' => 200]);
        $this->assertInstanceOf(ReadonlyEntity::class, $hydrated_1);
        $this->assertSame(100, $hydrated_1->id);
        $this->assertSame(200, $hydrated_2->id);
    }



    public function testStringEntity(): void {
        $hydrator = new Hydrator(StringEntity::class);
        $hydrated = $hydrator->hydrate([
            'string'         => 'a',
            'nullableString' => 'b',
        ]);
        $this->assertSame('a', $hydrated->string);
        $this->assertSame('b', $hydrated->nullableString);
        $hydrated_2 = $hydrator->hydrate([
            'nullableString' => ' ',
        ]);
        $this->assertSame(' ', $hydrated_2->nullableString);
        $hydrated_2 = $hydrator->hydrate([
            'nullableString' => null,
        ]);
        $this->assertSame('', $hydrated_2->string);
    }



    public function testUnionEntity(): void {
        $hydrator = new Hydrator(UnionEntity::class);
        $hydrated = $hydrator->hydrate([
            'union' => 100,
        ]);
        $this->assertSame(100, $hydrated->union);
        $hydrated = $hydrator->hydrate([
            'union' => 'test',
        ]);
        $this->assertSame('test', $hydrated->union);
        $this->expectException(HydratorException::class);
        $hydrator->hydrate([
            'union' => true,
        ]);
    }



    public function testUntypedEntity(): void {
        $hydrator = new Hydrator(UntypedEntity::class);
        $hydrated = $hydrator->hydrate([
            'mixed' => 100,
        ]);
        $this->assertSame(100, $hydrated->mixed);
    }



    public function testHydratorCollection(): void {
        $hydrator = new Hydrator(StringEntity::class);
        $data = [
            ['string' => 'first'],
            ['string' => 'second'],
            ['string' => 'third'],
        ];
        /** @var HydratorCollection<StringEntity> $collection */
        $collection = new HydratorCollection($hydrator, $data);
        $this->assertCount(3, $collection);
        $i = 0;
        foreach( $collection as $entity ) {
            $this->assertSame($data[$i++]['string'], $entity->string);
            $this->assertInstanceOf(StringEntity::class, $entity);
        }
    }


}
