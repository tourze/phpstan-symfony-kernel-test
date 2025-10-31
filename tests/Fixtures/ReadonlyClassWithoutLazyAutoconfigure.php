<?php

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure]
readonly class ReadonlyClassWithoutLazyAutoconfigure
{
    public function __construct(
        public string $value,
    ) {
    }
}
