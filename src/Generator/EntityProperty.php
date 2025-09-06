<?php

namespace Rammewerk\Component\Hydrator\Generator;

class EntityProperty {

    private const int EXAMPLE_MAX_LENGTH = 100;

    public string $name = '';
    private bool $nullable = false;
    private(set) bool $isLocked = false;
    private(set) bool $isArray = false;

    /** @var string[] */
    private array $types = [];

    /** @var string[] */
    private array $examples = [];



    public function setClass(string $propertyName, string $baseEntity, bool $isArray = false): string {
        $name = explode('_', $propertyName);
        /** @noinspection SpellCheckingInspection */
        $name = array_map('ucfirst', $name);
        $name = implode('', $name);
        $name = $baseEntity . str_replace($baseEntity, '', $name);
        if ($isArray) {
            $this->examples[] = "/** @var {$name}[] */";
            $this->types[] = 'array';
        } else {
            $this->types[] = $name;
            $this->nullable = true;
        }
        $this->isLocked = true;
        return $name;
    }



    public function addType(mixed $value): void {
        $type = strtolower(gettype($value));
        $type = match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            'double'  => 'float',
            'object'  => throw new \LogicException('Objects are not supported. Use only arrays.'),
            default   => $type,
        };
        if (strtolower($type) === 'null') {
            $this->nullable = true;
        } else if (!in_array($type, $this->types, true)) {
            $this->types[] = $type;
        }
    }



    public function getType(): string {
        $types = array_values(array_unique($this->types));
        $count = count($types);
        $type = match ($count) {
            0       => 'mixed',
            1       => $types[0],
            default => implode('|', $types)
        };
        if ($this->nullable && $type !== 'mixed') {
            $type = '?' . $type;
        }
        if ($type === 'array') {
            $this->isArray = true;
        }
        return $type;
    }



    public function getDefaultValue(): string {
        $types = array_values(array_unique($this->types));
        $type = count($types) === 1 ? $types[0] : 'mixed';
        return match ($type) {
            'string' => '""',
            'int'    => '0',
            'float'  => '0.0',
            'bool'   => 'false',
            'array'  => '[]',
            default  => 'null',
        };
    }



    public function addExample(mixed $value): void {

        // No need to add examples if value is null or empty string
        if ($value === null || $value === '') return;

        // Limit examples to 10
        if (count($this->examples) >= 20) {
            $this->isLocked = true;
            return;
        }

        if (is_array($value)) {

            // Don't add examples if value is empty array
            if (count($value) === 0) return;

            // Don't add example if is array, and examples are already added
            if (count($this->examples) > 0) return;

            $value = array_slice($value, 0, 20);
            $value = '[' . implode(', ', array_map(function ($v) {
                    return $this->formatValueForExample($v);
                }, $value)) . ']';

            $this->examples[] = $value;
            return;

        }

        // Limit length of examples
        if (is_string($value) && isset($value[self::EXAMPLE_MAX_LENGTH * 2])) {
            $value = substr($value, 0, self::EXAMPLE_MAX_LENGTH * 2);
        }

        $this->examples[] = $this->formatValueForExample($value);

        // Clean up examples, only unique values
        $this->examples = array_unique($this->examples);

    }



    public function getExample(): string {
        if (empty($this->examples)) return '';
        $example = trim(implode(", ", $this->examples));

        if (str_starts_with($example, "/**")) {
            return $example;
        }

        if (in_array('array', $this->types, true)) {
            $example = rtrim($this->truncateAtNearestComma($example), ']') . ']';
        } else {
            $example = $this->truncateAtNearestComma($example);
            if (isset($example[self::EXAMPLE_MAX_LENGTH])) {
                $example = substr($example, 0, self::EXAMPLE_MAX_LENGTH) . '...';
            }
        }

        if ($this->isArray) {
            $example .= "\n\t* @var mixed[]";
        }
        return $example;
    }



    private function formatValueForExample(mixed $value): string {

        // Format values
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';

        }

        if (is_null($value) && empty($this->examples)) {
            return 'null';

        }

        $value = var_export($value, true);
        $value = str_replace("\n", '', $value);
        if (isset($value[50])) {
            $value = substr($value, 0, 50);
        }

        return $value;

    }



    private function truncateAtNearestComma(string $text): string {
        if (strlen($text) <= self::EXAMPLE_MAX_LENGTH) {
            return $text; // No need to truncate
        }
        $cutPosition = strrpos(substr($text, 0, self::EXAMPLE_MAX_LENGTH), ',');
        if ($cutPosition === false) {
            return substr($text, 0, self::EXAMPLE_MAX_LENGTH); // No comma found, just cut at limit
        }
        return substr($text, 0, $cutPosition);
    }


}