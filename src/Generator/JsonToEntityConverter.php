<?php

namespace Rammewerk\Component\Hydrator\Generator;

use JsonException;

class JsonToEntityConverter extends EntityGenerator {


    /**
     * @param string|mixed[] $jsonOrArray
     * @param string $entityName
     * @param string|null $namespace
     * @param bool $includeComment
     *
     * @return never
     */
    public function generate(string|array $jsonOrArray, string $entityName = 'undefined', ?string $namespace = null, bool $includeComment = true): never {

        // Convert JSON to array
        $data = is_string($jsonOrArray) ? $this->convertJsonToArray($jsonOrArray) : $jsonOrArray;

        $this->generateEntity($data, $entityName, $namespace, $includeComment);
    }



    /**
     * @param string $jsonResponse
     *
     * @return mixed[]
     */
    private function convertJsonToArray(string $jsonResponse): array {
        try {
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                die("JSON response must be an object (associative array).");
            }
            return $data;
        } catch (JsonException $e) {
            die("Invalid JSON: " . $e->getMessage());
        }
    }



}