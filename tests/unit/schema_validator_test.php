<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

require_once dirname(__DIR__) . '/support/assertions.php';
mcp_test_bootstrap();

mcp_test_case('schema validator accepts nested objects, arrays, enum, and numeric string inputs', function () {
    $validator = new \OpenCartMcp\SchemaValidator();
    $schema = array(
        'type' => 'object',
        'properties' => array(
            'product_id' => array('type' => 'integer', 'minimum' => 1),
            'status' => array('type' => 'string', 'enum' => array('draft', 'active')),
            'images' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'image' => array('type' => 'string', 'maxLength' => 64),
                    ),
                    'required' => array('image'),
                    'additionalProperties' => false,
                ),
            ),
        ),
        'required' => array('product_id', 'status', 'images'),
        'additionalProperties' => false,
    );

    $errors = $validator->validate($schema, array(
        'product_id' => '42',
        'status' => 'active',
        'images' => array(array('image' => 'catalog/products/a.png')),
    ));

    mcp_assert_same(array(), $errors, 'valid nested input should pass');
});

mcp_test_case('schema validator reports missing, invalid enum, range, and additional-property errors', function () {
    $validator = new \OpenCartMcp\SchemaValidator();
    $schema = array(
        'type' => 'object',
        'properties' => array(
            'email' => array('type' => 'string', 'format' => 'email'),
            'quantity' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 10),
            'mode' => array('type' => 'string', 'enum' => array('dry_run', 'execute')),
        ),
        'required' => array('email', 'quantity', 'mode'),
        'additionalProperties' => false,
    );

    $errors = $validator->validate($schema, array(
        'quantity' => 11,
        'mode' => 'delete',
        'extra' => true,
    ));

    mcp_assert_true(count($errors) >= 4, 'invalid input should report all relevant schema failures');
    mcp_assert_true(in_array('$.email is required', $errors, true), 'missing required property should be reported');
    mcp_assert_true(in_array('$.extra is not allowed', $errors, true), 'additional property should be rejected');
});

mcp_test_case('schema validator accepts empty list arrays', function () {
    $validator = new \OpenCartMcp\SchemaValidator();
    $schema = array(
        'type' => 'object',
        'properties' => array(
            'items' => array('type' => 'array', 'items' => array('type' => 'string')),
        ),
        'required' => array('items'),
        'additionalProperties' => false,
    );

    mcp_assert_same(array(), $validator->validate($schema, array('items' => array())), 'empty arrays should validate as lists');
});
