<?php

namespace SsWiking\ElasticOrm\Contracts;

use Elasticsearch\Client;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

interface Builder extends Arrayable, Jsonable
{
    /**
     * Execute the query
     *
     * @return Collection
     */
    public function get(): Collection;

    /**
     * Get count of documents
     *
     * @return int
     */
    public function count(): int;

    /**
     * Find document by ID
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array;

    /**
     * Find many documents by IDs
     *
     * @param array $ids
     * @return Collection
     */
    public function findMany(array $ids): Collection;

    /**
     * @param string|array $columns
     * @return Builder
     */
    public function select($columns): Builder;

    /**
     * Set the maximum number of records to return
     *
     * @param int $limit
     * @return Builder
     */
    public function limit(int $limit): Builder;

    /**
     * Set the number of records to skip
     *
     * @param int $offset
     * @return Builder
     */
    public function offset(int $offset): Builder;

    /**
     * 'sort' clause
     *
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function orderBy(string $column, string $direction = 'asc'): Builder;

    /**
     * 'sort' raw clause
     *
     * @param array $sort
     * @return Builder
     */
    public function orderByRaw(array $sort): Builder;

    /**
     * 'collapse' instructions
     *
     * @param string $column
     * @param int $size
     * @param string|null $sortBy
     * @param string $sortDir
     * @return Builder
     */
    public function groupBy(string $column, int $size = 1, string $sortBy = null, string $sortDir = 'desc'): Builder;

    /**
     * Raw 'collapse' instructions
     *
     * @param array $collapse
     * @return Builder
     */
    public function groupByRaw(array $collapse): Builder;

    /**
     * Where column has provided value
     *
     * @param string $column
     * @param string|int|float|null $value
     * @return Builder
     */
    public function where(string $column, $value): Builder;

    /**
     * Where column has not provided value
     *
     * @param string $column
     * @param string|int|float|null $value
     * @return Builder
     */
    public function whereNot(string $column, $value): Builder;

    /**
     * Where column value is like provided string
     *
     * @param string $column
     * @param string $value
     * @return Builder
     */
    public function whereLike(string $column, string $value): Builder;

    /**
     * Where column value is not like provided string
     *
     * @param string $column
     * @param string $value
     * @return Builder
     */
    public function whereNotLike(string $column, string $value): Builder;

    /**
     * Where column in list of values
     *
     * @param string $column
     * @param array $values
     * @return Builder
     */
    public function whereIn(string $column, array $values): Builder;

    /**
     * Where column not in list of values
     *
     * @param string $column
     * @param array $values
     * @return Builder
     */
    public function whereNotIn(string $column, array $values): Builder;

    /**
     * Where doc has column
     *
     * @param string $column
     * @return Builder
     */
    public function whereHas(string $column): Builder;

    /**
     * Where doc has not column
     *
     * @param string $column
     * @return Builder
     */
    public function whereHasNot(string $column): Builder;

    /**
     * Where column greater than provided value
     *
     * @param string $column
     * @param float $value
     * @return Builder
     */
    public function whereGreaterThan(string $column, float $value): Builder;

    /**
     * Where column greater than or equal to provided value
     *
     * @param string $column
     * @param float $value
     * @return Builder
     */
    public function whereGreaterThanOrEqual(string $column, float $value): Builder;

    /**
     * Where column less than provided value
     *
     * @param string $column
     * @param float $value
     * @return Builder
     */
    public function whereLessThan(string $column, float $value): Builder;

    /**
     * Where column less than or equal to provided value
     *
     * @param string $column
     * @param float $value
     * @return Builder
     */
    public function whereLessThanOrEqual(string $column, float $value): Builder;

    /**
     * Where doc fits the requirements
     *
     * @param array $clause
     * @return mixed
     */
    public function whereRaw(array $clause): Builder;

    /**
     * Add aggregation
     *
     * @param string $name
     * @param string $column
     * @param int|null $size
     * @return mixed
     */
    public function aggr(string $name, string $column, ?int $size = null): Builder;

    /**
     * Add raw aggregation
     *
     * @param string $name
     * @param array $aggregation
     * @return mixed
     */
    public function aggrRaw(string $name, array $aggregation): Builder;

    /**
     * Set index name
     *
     * @param string $index
     * @return Builder
     */
    public function index(string $index): Builder;

    /**
     * Get index name
     *
     * @return string
     */
    public function getIndex(): string;

    /**
     * Erase all query bindings and return clear builder
     *
     * @return Builder
     */
    public function newQuery(): Builder;

    /**
     * Get base client
     *
     * @return Client
     */
    public function getConnection(): Client;
}
