<?php

namespace Doctrine\ODM\MongoDB\Query;

final class BSONHelper
{
    /**
     * Formats the supplied array as BSON.
     *
     * @param array $array All or part of a query array
     *
     * @return string A BSON object
     */
    static public function fromArray(array $array)
    {
        $parts = array();

        $isArray = true;
        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                $isArray = false;
            }

            if (is_bool($value)) {
                $formatted = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $formatted = '"'.$value.'"';
            } elseif (is_array($value)) {
                $formatted = self::fromArray($value);
            } elseif ($value instanceof \MongoId) {
                $formatted = 'ObjectId("'.$value.'")';
            } elseif ($value instanceof \MongoDate) {
                $formatted = 'new Date("'.date('r', $value->sec).'")';
            } elseif ($value instanceof \DateTime) {
                $formatted = 'new Date("'.date('r', $value->getTimestamp()).'")';
            } elseif ($value instanceof \MongoRegex) {
                $formatted = 'new RegExp("'.$value->regex.'", "'.$value->flags.'")';
            } elseif ($value instanceof \MongoMinKey) {
                $formatted = 'new MinKey()';
            } elseif ($value instanceof \MongoMaxKey) {
                $formatted = 'new MaxKey()';
            } elseif ($value instanceof \MongoBinData) {
                $formatted = 'new BinData("'.$value->bin.'", "'.$value->type.'")';
            } else {
                $formatted = (string) $value;
            }

            $parts['"'.$key.'"'] = $formatted;
        }

        if (0 == count($parts)) {
            return $isArray ? '[ ]' : '{ }';
        }

        if ($isArray) {
            return '[ '.implode(', ', $parts).' ]';
        } else {
            $mapper = function($key, $value)
            {
                return $key.': '.$value;
            };

            return '{ '.implode(', ', array_map($mapper, array_keys($parts), array_values($parts))).' }';
        }
    }

    private function __construct() { }
}
