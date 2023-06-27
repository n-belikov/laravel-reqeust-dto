<?php

namespace NBelikov\RequestDTO\Attributes;

#[\Attribute]
class Validation
{
    /** @var array */
    private array $rules;

    public function __construct(mixed ...$rules)
    {
        if (count($rules) === 1) {
            if (strpos($rules[0], '|') !== false) {
                $rules = explode("|", $rules[0]);
            }
        }

        $this->rules = $rules;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
