<?php

declare(strict_types=1);

namespace Tests\Support;

class FakerFactoryStub
{
    public static function create($locale = null)
    {
        return new class ($locale) {
            public function __construct($locale = null)
            {
            }

            public function __call(string $name, array $arguments)
            {
                if ('randomDigit' === $name) {
                    return 5;
                }
                if ('name' === $name) {
                    return 'Test User';
                }
                if ('email' === $name) {
                    return 'user@example.com';
                }
                if ('safeEmail' === $name) {
                    return 'user@example.com';
                }
                if ('phoneNumber' === $name) {
                    return '+10000000000';
                }
                if ('address' === $name) {
                    return '123 Test St';
                }
                if ('uuid' === $name) {
                    return '00000000-0000-0000-0000-000000000000';
                }
                if ('word' === $name) {
                    return 'word';
                }
                if ('sentence' === $name) {
                    return 'sentence';
                }
                if ('text' === $name) {
                    return 'text';
                }
                if ('city' === $name) {
                    return 'City';
                }
                if ('country' === $name) {
                    return 'Country';
                }
                if ('postcode' === $name) {
                    return '00000';
                }
                if ('randomElement' === $name) {
                    return $arguments[0][0] ?? null;
                }
                if ('randomNumber' === $name) {
                    return 42;
                }

                return $arguments[0] ?? (string) $name;
            }
        };
    }
}
