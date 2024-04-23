<?php

declare(strict_types=1);

/**
 * Contains the PropertyValue class.
 *
 * @copyright   Copyright (c) 2018 Attila Fulop
 * @author      Attila Fulop
 * @license     MIT
 * @since       2018-12-08
 *
 */

namespace Vanilo\Properties\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Properties\Contracts\PropertyValue as PropertyValueContract;

/**
 * @property \Vanilo\Properties\Contracts\Property $property
 * @property string                                $value      The value as stored in the db @see getCastedValue()
 * @property string                                $title
 * @property integer                               $priority
 * @property array|null                            $settings
 *
 * @method static Builder byProperty(int|Property $property)
 * @method Builder sort()
 * @method Builder sortReverse()
 *
 */
class PropertyValue extends Model implements PropertyValueContract
{
    use Sluggable;
    use SluggableScopeHelpers;

    protected $table = 'property_values';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'settings' => 'array'
    ];

    public static function findByPropertyAndValue(string $propertySlug, mixed $value): ?PropertyValueContract
    {
        if (null === $property = PropertyProxy::findBySlug($propertySlug)) {
            return null;
        }

        return static::byProperty($property)->whereSlug($value)->first();
    }

    /**
     * @example ['color' => 'blue', 'shape' => 'heart']
     * @param array<string, mixed> $conditions The keys of the entries = the property slug, the values = the scalar property value
     */
    public static function getByScalarPropertiesAndValues(array $conditions): Collection
    {
        if (empty($conditions)) {
            return new Collection([]);
        }

        $query = self::query()
            ->select('property_values.*')
            ->join('properties', 'property_values.property_id', '=', 'properties.id');

        $count = 0;
        foreach ($conditions as $property => $value) {
            match ($count) {
                0 => $query->where(fn ($q) => $q->where('properties.slug', '=', $property)->where('property_values.value', '=', $value)),
                default => $query->orWhere(fn ($q) => $q->where('properties.slug', '=', $property)->where('property_values.value', '=', $value)),
            };
            $count++;
        }

        return $query->get();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(PropertyProxy::modelClass());
    }

    public function scopeSort($query)
    {
        return $query->orderBy('priority');
    }

    public function scopeSortReverse($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeByProperty($query, $property)
    {
        $id = is_object($property) ? $property->id : $property;

        return $query->where('property_id', $id);
    }

    /**
     * Returns the transformed value according to the underlying type
     */
    public function getCastedValue(): mixed
    {
        return $this->property->getType()->transformValue((string) $this->value, $this->settings);
    }

    public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, $attribute, $config, $slug)
    {
        return $query->where('property_id', $model->property->id);
    }

    public function sluggable(): array
    {
        return [
            'value' => [
                'source' => 'title'
            ]
        ];
    }
}
