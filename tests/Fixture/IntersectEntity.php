<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectClassA;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectInterface1;
use Rammewerk\Component\Hydrator\Tests\Fixture\Dependencies\IntersectInterface2;

class IntersectEntity {

    public IntersectInterface1&IntersectInterface2 $interface;

    public function __construct() {
        $this->interface = new IntersectClassA();
    }

}