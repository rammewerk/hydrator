<?php

namespace Rammewerk\Component\Hydrator\Tests;

class EntityClass {

    public readonly int $id;
    public ?string $nullable;
    public string $string = '';
    public ?string $nullableString = null;
    public int $integer = 0;
    public bool $boolean = true;

    public function __construct(int $id) {
        $this->id = $id;
    }

}