<?php

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(lazy: true)]
class NonReadonlyClassWithLazyAutoconfigure
{
    public function __construct(
        public string $value,
    ) {
    }
}
