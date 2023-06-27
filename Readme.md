### Request -> DTO

Create data objects without requests & safe validate.

Example:

```php

use NBelikov\RequestDTO\Attributes\ArrayValidation;
use NBelikov\RequestDTO\Attributes\Validation;
use NBelikov\RequestDTO\DataTransferObject;

class TestDTO extends DataTransferObject
{
    #[Validation('required', 'string')]
    public string $title;

    /**
     * @var TestItemDTO[]
     */
    #[ArrayValidation(TestItemDTO::class)]
    public array $items;

    public TestItemDTO $item;
}
```
