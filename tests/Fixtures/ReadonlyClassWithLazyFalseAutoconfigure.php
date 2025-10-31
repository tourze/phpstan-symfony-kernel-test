<?php

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(lazy: false)]
readonly class ReadonlyClassWithLazyFalseAutoconfigure
{
    public function __construct(
        public string $value,
    ) {
    }
}
