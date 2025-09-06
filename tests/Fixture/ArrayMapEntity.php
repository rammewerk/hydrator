<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

use Rammewerk\Component\Hydrator\Attribute\ArrayOf;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectClassA;

class ArrayMapEntity {

    /** @var IntersectClassA[] */
    #[ArrayOf(IntersectClassA::class)]
    public array $intersectClasses = [];


}