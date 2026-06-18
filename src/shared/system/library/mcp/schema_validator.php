<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
namespace OpenCartMcp;

class SchemaValidator {
    public function validate($schema, $value) {
        $errors = array();
        $this->validateNode($schema, $value, '$', $errors);
        return $errors;
    }

    private function validateNode($schema, $value, $path, &$errors) {
        if (!is_array($schema)) {
            return;
        }

        if (isset($schema['type']) && !$this->matchesType($schema['type'], $value)) {
            $errors[] = $path . ' must be ' . $schema['type'];
            return;
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = $path . ' must be one of: ' . implode(', ', $schema['enum']);
        }

        if (($schema['type'] ?? '') === 'object') {
            $this->validateObject($schema, $value, $path, $errors);
        }

        if (($schema['type'] ?? '') === 'array' && isset($schema['items']) && is_array($value)) {
            foreach ($value as $index => $item) {
                $this->validateNode($schema['items'], $item, $path . '[' . $index . ']', $errors);
            }
        }

        if (is_string($value)) {
            if (isset($schema['maxLength']) && strlen($value) > (int)$schema['maxLength']) {
                $errors[] = $path . ' exceeds maxLength ' . (int)$schema['maxLength'];
            }
            if (($schema['format'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $path . ' must be a valid email';
            }
        }

        if (is_numeric($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = $path . ' must be >= ' . $schema['minimum'];
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = $path . ' must be <= ' . $schema['maximum'];
            }
        }
    }

    private function validateObject($schema, $value, $path, &$errors) {
        if (!is_array($value)) {
            return;
        }

        $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : array();
        foreach ($required as $field) {
            if (!array_key_exists($field, $value)) {
                $errors[] = $path . '.' . $field . ' is required';
            }
        }

        $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : array();
        foreach ($value as $field => $item) {
            if (!array_key_exists($field, $properties)) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = $path . '.' . $field . ' is not allowed';
                }
                continue;
            }

            $this->validateNode($properties[$field], $item, $path . '.' . $field, $errors);
        }
    }

    private function matchesType($type, $value) {
        if (is_array($type)) {
            foreach ($type as $item) {
                if ($this->matchesType($item, $value)) {
                    return true;
                }
            }
            return false;
        }

        switch ($type) {
            case 'object':
                return is_array($value);
            case 'array':
                return is_array($value) && array_keys($value) === range(0, count($value) - 1);
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value));
            case 'number':
                return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
            case 'boolean':
                return is_bool($value);
            case 'null':
                return $value === null;
            default:
                return true;
        }
    }
}

