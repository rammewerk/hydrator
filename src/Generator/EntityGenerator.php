<?php

namespace Rammewerk\Component\Hydrator\Generator;

abstract class EntityGenerator {


    /** @var array<string, array<string, EntityProperty>> */
    private array $entityStore = [];



    /**
     * @param mixed[] $data
     * @param string $entityName
     * @param string|null $namespace
     * @param bool $includeComment
     *
     * @return never
     */
    protected function generateEntity(array $data, string $entityName, ?string $namespace, bool $includeComment): never {

        // Normalize an array so that it's always an array of entities
        $data = $this->isArrayOfEntities($data) ? $data : [$data];

        // Capture all properties from an array
        /** @var array<int, array<string, mixed>> $data */
        $this->captureAllProperties($entityName, $data, $entityName);

        echo "<pre>", $this->generateOutput($namespace, $includeComment), "</pre>";
        exit;
    }



    /**
     * Generate output for the entity generator
     *
     * @param string|null $namespace
     * @param bool $includeComment
     *
     * @return string
     */
    private function generateOutput(?string $namespace, bool $includeComment): string {

        $output = htmlspecialchars('<?php', ENT_QUOTES, 'UTF-8') . "\n\n";
        $output .= "declare(strict_types=1);\n\n";
        $output .= ($namespace) ? "namespace $namespace;\n\n" : '';

        foreach ($this->entityStore as $className => $properties) {
            $output .= "class $className {\n\n";
            foreach ($properties as $property) {
                $output .= $this->processProperty($property, $includeComment);
            }
            $output .= "}\n\n\n";
        }

        return $output;

    }



    /**
     * @param string $entity
     * @param array<int, array<string, mixed>> $array
     * @param string $baseEntity The base entity name
     *
     * @return void
     */
    private function captureAllProperties(string $entity, array $array, string $baseEntity): void {

        // If we already have a class with this name, we need to create a child class
        if (isset($this->entityStore[$entity])) {
            $entity .= 'Child';
            $this->captureAllProperties($entity, $array, $baseEntity);
            return;
        }

        foreach ($array as $item) {
            foreach ($item as $property_name => $value) {

                // Set property if not already defined
                if (isset($this->entityStore[$entity][$property_name])) {
                    /** @var EntityProperty $property */
                    $property = $this->entityStore[$entity][$property_name];
                    if (!$property->isLocked) {
                        $property->addType($value);
                        $property->addExample($value);
                    }
                    continue;
                }

                // Generate new property
                $property = $this->entityStore[$entity][$property_name] = new EntityProperty();
                $property->name = $property_name;

                if (is_array($value) && $this->isAssociativeArray($value)) {
                    $childEntityName = $property->setClass($property_name, $baseEntity);
                    /** @var array<string, mixed> $value */
                    $this->captureAllProperties($childEntityName, [$value], $baseEntity);
                    continue;
                }

                if (is_array($value) && $this->isArrayOfEntities($value)) {
                    $childEntityName = $property->setClass($property_name, $baseEntity, true);
                    /** @var array<int, array<string, mixed>> $value */
                    $this->captureAllProperties($childEntityName, $value, $baseEntity);
                    continue;
                }


                $property->addType($value);
                $property->addExample($value);

            }
        }


    }



    /*
    |--------------------------------------------------------------------------
    | Process
    |--------------------------------------------------------------------------
    */

    private function processProperty(EntityProperty $property, bool $includeComment): string {
        $type = $property->getType();
        $name = $property->name;
        $defaultValue = $property->getDefaultValue();
        
        if ($includeComment) {
            $example = $property->getExample();
            if (str_starts_with($example, "/**")) {
                $example = "\t$example\n";
            } else {
                $example = $example ? "\t/** Example: $example */\n" : "";
            }
            return "$example\tpublic $type \$$name = $defaultValue;\n\n";
        }

        return "\tpublic $type \$$name = $defaultValue;\n";

    }








    /*
    |--------------------------------------------------------------------------
    | Helpers for understanding the schema
    |--------------------------------------------------------------------------
    */

    /**
     * Check if array is associative
     *
     * @param mixed[] $array
     *
     * @return bool
     */
    private function isAssociativeArray(array $array): bool {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }



    /**
     * Check if array is array of entities
     *
     * @param mixed[] $array
     *
     * @return bool
     */
    private function isArrayOfEntities(array $array): bool {
        // Array of entities are never associative
        if ($this->isAssociativeArray($array)) return false;
        // Keep track of how many entities we have
        $count = 0;
        // Loop through array and count entities
        foreach ($array as $item) {
            if ($count > 2) break;
            if (is_array($item) && $this->isAssociativeArray($item)) {
                $count++;
            }
        }
        return (bool)$count;
    }



}