<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

class ReadonlyEntity {

    public function __construct(
        public readonly int $id,
    ) {}



    public function getId(): int {
        return $this->id;
    }


}