<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\EnumInt;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\EnumString;

class EnumEntity {

    public ?EnumString $enumStringOrNull = null;
    public ?EnumInt $enumIntOrNull = null;

    public EnumString $enumString = EnumString::A;
    public EnumInt $enumInt = EnumInt::A;


}
