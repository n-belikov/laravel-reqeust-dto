<?php

namespace NBelikov\RequestDTO;

use NBelikov\RequestDTO\Attributes\ArrayValidation;
use NBelikov\RequestDTO\Attributes\Validation;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\ValidatesWhenResolved;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

abstract class DataTransferObject implements ValidatesWhenResolved, Arrayable
{

    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect;

    /**
     * The route to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     * The controller action to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectAction;

    /**
     * The key to be used for the view error bag.
     *
     * @var string
     */
    protected $errorBag = 'default';

    protected array $data;

    public function __construct(
        private Redirector $redirector,
        private Container  $container,
        private bool       $isRullable = true
    )
    {
    }

    public function validateResolved()
    {
        if ($this->isRullable) {
            $rules = $this->rules();

            /** @var ValidationFactory $factory */
            $factory = $this->container->make(ValidationFactory::class);

            /** @var Validator $validator */
            $validator = $factory->make($this->container->get('request')->all(), $rules);

            if ($validator->fails()) {
                $this->failedValidation($validator);
            }

            $this->merge($validator->validated());
        }
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->getProperties() as $property) {
            if ($validator = $this->getValidation(Validation::class, $property)) {
                $rules[$property->getName()] = $validator->getRules();
            } elseif ($validator = $this->getValidation(ArrayValidation::class, $property)) {
                $rules = array_merge(
                    $rules,
                    $this->prepareTargetRule($validator->getTarget(), $property->getName().'.*')
                );
            } else {
                $type = $property->getType()?->getName();
                if ($type && is_subclass_of($type, AttributeFormRequest::class)) {
                    $rules = array_merge(
                        $rules,
                        $this->prepareTargetRule($type, $property->getName())
                    );
                }
            }
        }

        return $rules;
    }

    /**
     * Get reflection property of current class instance
     *
     * @return \ReflectionProperty[]
     */
    protected function getProperties(): array
    {
        return (new \ReflectionClass($this))
            ->getProperties(\ReflectionProperty::IS_PUBLIC);
    }


    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $url = $this->redirector->getUrlGenerator();

        if ($this->redirect) {
            return $url->to($this->redirect);
        } elseif ($this->redirectRoute) {
            return $url->route($this->redirectRoute);
        } elseif ($this->redirectAction) {
            return $url->action($this->redirectAction);
        }

        return $url->previous();
    }

    protected function prepareTargetRule(string $item, string $prefix): array
    {
        $rules = [];

        /** @var AttributeFormRequest $resolve */
        $resolve = \resolve($item, ['isRullable' => false]);
        foreach ($resolve->rules() as $key => $rule) {
            $key         = rtrim("{$prefix}.{$key}", ".");
            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * @template Type
     * @param class-string<Type>|null $item
     * @return Type
     */
    protected function getValidation(string $item, \ReflectionProperty $property): ?object
    {
        return Arr::first($property->getAttributes($item))?->newInstance();
    }

    protected function resolveTargetData(string $class, array $data): AttributeFormRequest
    {
        /** @var AttributeFormRequest $item */
        $item = \resolve($class, ['isRullable' => false]);

        $item->merge($data);

        return $item;
    }

    protected function merge(array $data): void
    {
        $this->data = $properties = [];
        foreach ($this->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        foreach ($data as $key => $value) {
            $property = $properties[$key] ?? null;
            if (!$property) {
                continue;
            }

            $this->data[$key] = $value;

            if (is_array($value)) {
                $type = $property->getType()?->getName();

                if (!$type || $type === "array") {
                    if ($validator = $this->getValidation(ArrayValidation::class, $property)) {
                        $value = array_map(function (array $item) use ($validator) {
                            return $this->resolveTargetData($validator->getTarget(), $item);
                        }, $value);
                    }
                } elseif (is_subclass_of($type, AttributeFormRequest::class)) {
                    $value = $this->resolveTargetData($type, $value);
                }
            }

            $this->{$key} = $value;
        }
    }

    public function toArray()
    {
        return $this->data;
    }
}
