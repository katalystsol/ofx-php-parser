<?php

namespace KatalystSolutions\OFX;

class Institute
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
