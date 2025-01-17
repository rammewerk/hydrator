<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

class ConstructorEntity {

    public readonly string $testString;
    public int $intTest = 0;



    public function __construct(
        public ?string $promotedString = null,
        public readonly int $id = 100,
        string $string = 'ok',
        int $intTest = 1,
    ) {
        $this->testString = $string;
        $this->intTest = $intTest;
    }



    public function getId(): int {
        return $this->id;
    }


}