<?php

namespace Rammewerk\Component\Hydrator\Generator;


class SqlToEntityConverter extends EntityGenerator {


    /**
     * @param string $createTableQuery
     * @param string $entityName
     * @param string|null $namespace
     * @param bool $includeComment
     *
     * @return never
     */
    public function generate(string $createTableQuery, string $entityName = 'undefined', ?string $namespace = null, bool $includeComment = true): never {
        $data = [];
        preg_match('/\((.*)\)/s', $createTableQuery, $matches); // Extract column definitions

        if (isset($matches[1])) {
            $columns = explode(',', $matches[1]);
            foreach ($columns as $column) {
                $column = trim($column);
                preg_match('/`([^`]+)`\s+([a-zA-Z]+)/', $column, $colMatches);
                array_shift($colMatches);
                if (isset($colMatches[0], $colMatches[1])) {
                    [$name, $type] = $colMatches;
                    $data[$name] = $this->mapSqlTypeToExampleValue($type);
                }
            }
        }

        $this->generateEntity($data, $entityName, $namespace, $includeComment);
    }



    private function mapSqlTypeToExampleValue(string $sqlType): string|int|false|float {
        $sqlType = strtolower($sqlType);

        if (str_contains($sqlType, 'int')) {
            return 0;
        }

        if (in_array($sqlType, ['float', 'double', 'decimal',], true)) {
            return 0.0;
        }

        if (in_array($sqlType, ['bool', 'boolean',], true)) {
            return false;
        }

        // Will become a string
        return $sqlType;

    }


}