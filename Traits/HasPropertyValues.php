<?php

declare(strict_types=1);

/**
 * Contains the HasPropertyValues trait.
 *
 * @copyright   Copyright (c) 2019 Attila Fulop
 * @author      Attila Fulop
 * @license     MIT
 * @since       2019-02-03
 *
 */

namespace Vanilo\Properties\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Vanilo\Properties\Contracts\Property;
use Vanilo\Properties\Contracts\PropertyValue;
use Vanilo\Properties\Exceptions\UnknownPropertyException;
use Vanilo\Properties\Models\PropertyProxy;
use Vanilo\Properties\Models\PropertyValueProxy;

trait HasPropertyValues
{
    public function assignPropertyValue(string|Property $property, mixed $value): void
    {
        if ($value instanceof PropertyValue) {
            $this->addPropertyValue($value);

            return;
        }

        $this->addPropertyValue($this->findOrCreateByPropertyValue($property, $value));
    }

    public function assignPropertyValues(iterable $propertyValues): void
    {
        foreach ($propertyValues as $property => $value) {
            $this->assignPropertyValue($property, $value);
        }
    }

    /**
     * @param array<string, mixed> The key of the array is the property slug, the value is the scalar property value
     */
    public function replacePropertyValuesByScalar(array $propertyValues): void
    {
        $valuesToSet = PropertyValueProxy::getByScalarPropertiesAndValues($propertyValues);
        if (count($propertyValues) !== count($valuesToSet)) {
            foreach ($propertyValues as $property => $value) {
                if (!$valuesToSet->contains(fn (PropertyValue $pv) => $pv->value == $value && $pv->property->slug === $property)) {
                    $valuesToSet[] = $this->findOrCreateByPropertyValue($property, $value);
                }
            }
        }

        $this->replacePropertyValues(...$valuesToSet);
    }

    public function replacePropertyValues(PropertyValue ...$propertyValues): void
    {
        $this->propertyValues()->sync(collect($propertyValues)->pluck('id'));
    }

    public function valueOfProperty(string|Property $property): ?PropertyValue
    {
        $propertySlug = is_string($property) ? $property : $property->slug;
        foreach ($this->propertyValues as $propertyValue) {
            if ($propertySlug === $propertyValue->property->slug) {
                return $propertyValue;
            }
        }

        return null;
    }

    public function propertyValues(): MorphToMany
    {
        return $this->morphToMany(
            PropertyValueProxy::modelClass(),
            'model',
            'model_property_values',
            'model_id',
            'property_value_id'
        );
    }

    public function addPropertyValue(PropertyValue $propertyValue): void
    {
        $this->propertyValues()->attach($propertyValue);
    }

    public function addPropertyValues(iterable $propertyValues)
    {
        foreach ($propertyValues as $propertyValue) {
            if (!$propertyValue instanceof PropertyValue) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Every element passed to addPropertyValues must be a PropertyValue object. Given `%s`.',
                        is_object($propertyValue) ? get_class($propertyValue) : gettype($propertyValue)
                    )
                );
            }
        }

        return $this->propertyValues()->saveMany($propertyValues);
    }

    public function removePropertyValue(PropertyValue $propertyValue)
    {
        return $this->propertyValues()->detach($propertyValue);
    }

    protected function findOrCreateByPropertyValue(string|Property $property, mixed $value): PropertyValue
    {
        $result = PropertyValueProxy::findByPropertyAndValue($property, $value);
        if (null === $result) {
            if (null === $propertyId = $property instanceof Property ? $property->id : PropertyProxy::findBySlug($property)?->id) {
                throw UnknownPropertyException::createFromSlug($property);
            }
            $result = PropertyValueProxy::create([
                'property_id' => $propertyId,
                'value' => $value,
                'title' => $value,
            ]);
        }

        return $result;
    }
}
