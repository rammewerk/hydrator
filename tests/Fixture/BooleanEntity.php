<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

class BooleanEntity {

    public bool $boolean = false;
    public bool $nullableBoolean = false;
    public true|null $nullableTrue = null;
    public false|null $nullableFalse = null;

}