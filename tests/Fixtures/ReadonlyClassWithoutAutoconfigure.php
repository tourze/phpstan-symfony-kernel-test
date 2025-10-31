<?php

readonly class ReadonlyClassWithoutAutoconfigure
{
    public function __construct(
        public string $value,
    ) {
    }
}
