<?php

namespace NBelikov\RequestDTO\Attributes;

#[\Attribute]
class ArrayValidation
{
    public function __construct(private string $target)
    {
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }
}
