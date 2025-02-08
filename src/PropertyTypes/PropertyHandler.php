<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use LogicException;

abstract class PropertyHandler {

    private ?Closure $converter = null;

    public string $name = '';
    public bool $promoted = false;
    public mixed $default = null;
    public bool $nullable = false;
    public string $className = '';
    public string $type = '';



    public function generateConverter(): void {
        $this->converter = $this->getConverter();
    }



    public function convert(mixed $value): mixed {
        return $this->converter
            ? ($this->converter)($value)
            : throw new LogicException('Type converter not generated');
    }



    abstract protected function getConverter(): Closure;


}