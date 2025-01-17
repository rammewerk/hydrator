<?php

namespace Rammewerk\Component\Hydrator\Tests\Fixture;

class DateEntity {

    public ?\DateTime $dateTimeOrNull = null;
    public ?\DateTimeZone $dateTimeZoneOrNull = null;
    public ?\DateTimeImmutable $dateTimeImmutableOrNull = null;

    public \DateTime $dateTime;
    public \DateTimeZone $dateTimeZone;
    public \DateTimeImmutable $dateTimeImmutable;



    public function __construct() {
        $this->dateTime = new \DateTime('now');
        $this->dateTimeZone = new \DateTimeZone('Europe/Amsterdam');
        $this->dateTimeImmutable = new \DateTimeImmutable('now');
    }


}