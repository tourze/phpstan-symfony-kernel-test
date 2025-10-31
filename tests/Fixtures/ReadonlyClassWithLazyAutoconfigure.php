<?php

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(lazy: true)]
readonly class ReadonlyClassWithLazyAutoconfigure
{
    public function __construct(
        public string $value,
    ) {
    }
}
