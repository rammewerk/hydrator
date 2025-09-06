<?php

declare(strict_types=1);

namespace Rammewerk\Component\Hydrator\PropertyTypes;

use Closure;
use LogicException;

abstract class PropertyHandler {

    private ?Closure $converter = null;

    public string $className = '';
    public string $name = '';
    public string $type = '';
    public bool $promoted = false;
    public bool $nullable = false;
    public mixed $default = null;



    public function generateConverter(): void {
        $this->converter = $this->getConverter();
    }



    public function convert(mixed $value): mixed {
        return $this->converter
            ? ($this->converter)($value)
            : throw new LogicException('Type converter not generated');
    }


    /** Allow Hydrator to override the converter via attributes */
    public function useCustomConverter(\Closure $converter): void {
        $this->converter = $converter;
    }


    abstract protected function getConverter(): Closure;


}