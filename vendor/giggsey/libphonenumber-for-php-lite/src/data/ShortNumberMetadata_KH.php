<?php

/**
 * libphonenumber-for-php-lite data file
 * This file has been @generated from libphonenumber data
 * Do not modify!
 * @internal
 */

return [
    'id' => 'KH',
    'countryCode' => 0,
    'generalDesc' => [
        'pattern' => '[146]\\d\\d(?:\\d{2})?',
        'posLength' => [
            3,
            5,
        ],
    ],
    'tollFree' => [
        'pattern' => '11[7-9]|666',
        'example' => '117',
        'posLength' => [
            3,
        ],
    ],
    'premiumRate' => [
        'posLength' => [
            -1,
        ],
    ],
    'emergency' => [
        'pattern' => '11[7-9]|666',
        'example' => '117',
        'posLength' => [
            3,
        ],
    ],
    'shortCode' => [
        'pattern' => '11[7-9]|40404|666',
        'example' => '117',
    ],
    'standardRate' => [
        'posLength' => [
            -1,
        ],
    ],
    'carrierSpecific' => [
        'pattern' => '404\\d\\d',
        'example' => '40400',
        'posLength' => [
            5,
        ],
    ],
    'smsServices' => [
        'pattern' => '404\\d\\d',
        'example' => '40400',
        'posLength' => [
            5,
        ],
    ],
    'internationalPrefix' => '',
    'numberFormat' => [],
];
