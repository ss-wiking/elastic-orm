<?php

namespace SsWiking\ElasticOrm;

use ArrayAccess;
use BadMethodCallException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use JsonException;
use JsonSerializable;

/**
 * @mixin Builder
 */
abstract class Model implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The model's collapsed entities
     *
     * @var Collection|array<string, static[]>
     */
    protected Collection $collapsed;

    /**
     * Model index name
     *
     * @var string|null
     */
    protected ?string $index;

    /**
     * Attributes type casts
     *
     * @var array
     */
    protected array $casts = [];

    /**
     * Elasticsearch query builder
     *
     * @var Builder
     */
    protected Builder $builder;

    /**
     * Finite functions after which no model chain is possible
     *
     * @var array|string[]
     */
    protected array $finiteMethods = [
        'index',
        'count',
        'aggr',
        'aggrRaw',
    ];

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->setCollapsed();
        $this->builder = $this->makeBuilder();
    }

    /**
     * Execute the query
     *
     * @return Collection|static[]
     */
    public function get(): Collection
    {
        return $this->builder
            ->get()
            ->map(function (array $document): Model {
                $model = new static($document['_source'] ?? []);
                foreach ($document['inner_hits'] ?? [] as $name => $group) {

                    $group = collect($group['hits']['hits'] ?? [])
                        ->map(fn(array $doc) => new static($doc['_source'] ?? []));

                    $model->addCollapsed($name, $group);
                }

                return $model;
            });
    }

    /**
     * Find document by ID
     *
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?Model
    {
        $doc = $this->builder->find($id);
        if (is_null($doc)) {
            return null;
        }

        return new static($doc['_source'] ?? []);
    }

    /**
     * Find many documents by IDs
     *
     * @param array $ids
     * @return Collection|Model[]
     */
    public function findMany(array $ids): Collection
    {
        return $this->builder->findMany($ids)
            ->map(function (array $doc) {
                if (($doc['found'] ?? false) === false) {
                    return null;
                }

                return new static($doc['_source'] ?? []);
            })
            ->filter()
            ->values();
    }

    /**
     * Returns query builder
     *
     * @return Builder
     */
    public function toBase(): Builder
    {
        return $this->builder;
    }

    /**
     * Set collapsed groups
     *
     * @param array $collapsed
     * @return void
     */
    public function setCollapsed(array $collapsed = []): void
    {
        $this->collapsed = collect($collapsed)->recursive();
    }

    /**
     * Add new collapsed group
     *
     * @param string $name
     * @param Collection $group
     * @return void
     */
    public function addCollapsed(string $name, Collection $group): void
    {
        $this->collapsed[$name] = $group;
    }

    /**
     * Get collapsed group by name
     *
     * @param string $name
     * @return null
     */
    public function getCollapsed(string $name): ?Collection
    {
        return $this->collapsed[$name] ?? null;
    }

    /**
     * Get all collapsed groups
     *
     * @return Collection
     */
    public function getAllCollapsed(): Collection
    {
        return $this->collapsed;
    }

    /**
     * Handle dynamic method calls into the model
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!method_exists($this->builder, $method)) {
            static::throwBadMethodCallException($method);
        }

        $result = $this->builder->{$method}(...$parameters);

        if (in_array($method, $this->finiteMethods, true)) {
            return $result;
        }

        return $this;
    }

    /**
     * Handle dynamic static method calls into the model
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static())->{$method}(...$parameters);
    }

    /**
     * Dynamically retrieve attributes on the model
     *
     * @param string $attribute
     * @return array|string|int|float|null
     */
    public function __get(string $attribute)
    {
        return $this->getAttribute($attribute);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $attribute
     * @param mixed $value
     * @return void
     */
    public function __set(string $attribute, $value): void
    {
        $this->setAttribute($attribute, $value);
    }

    /**
     * Determine if an attribute exists on the model
     *
     * @param string $attribute
     * @return bool
     */
    public function __isset(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * Get an attribute from the model
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute(string $attribute)
    {
        return $this->attributes[$attribute] ?? null;
    }

    /**
     * Set a given attribute on the model
     *
     * @param string $attribute
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $attribute, $value): void
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * Get model index name
     *
     * @return string
     */
    public function getIndex(): string
    {
        return $this->index ?? Str::snake(class_basename(static::class));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'collapsed' => $this->collapsed->toArray(),
        ];
    }

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Make builder instance
     *
     * @return Builder
     */
    protected function makeBuilder(): Builder
    {
        return App::make(Builder::class)->index($this->getIndex());
    }

    /**
     * Throw a bad method call exception for the given method.
     *
     * @param string $method
     * @return void
     *
     * @throws BadMethodCallException
     */
    protected static function throwBadMethodCallException(string $method): void
    {
        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()', static::class, $method
        ));
    }
}
