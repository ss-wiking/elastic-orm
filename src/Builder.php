<?php

namespace SsWiking\ElasticOrm;

use DomainException;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JsonException;

class Builder implements Contracts\Builder
{
    /**
     * Base client instance
     *
     * @var Client
     */
    protected Client $connection;

    /**
     * Fields to select
     *
     * @var array
     */
    protected array $columns = [];

    /**
     * 'must' clauses
     *
     * @var array
     */
    protected array $musts = [];

    /**
     * 'must_not' clauses
     *
     * @var array
     */
    protected array $notMusts = [];

    /**
     * 'filter' clauses
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * Query aggregations
     *
     * @var array
     */
    protected array $aggregations = [];

    /**
     * The maximum number of records to return
     *
     * @var int
     */
    protected int $size = -1;

    /**
     * The number of records to skip
     *
     * @var int
     */
    protected int $from = 0;

    /**
     * 'sort' clauses
     *
     * @var array
     */
    protected array $sorts = [];

    /**
     * 'collapse' instructions
     *
     * @var array
     */
    protected array $collapse = [];

    /**
     * Active index name
     *
     * @var string
     */
    protected string $index;

    /**
     * @param ClientBuilder $clientBuilder
     * @param Contracts\Config $config
     */
    public function __construct(ClientBuilder $clientBuilder, Contracts\Config $config)
    {
        $this->connect($clientBuilder, $config);
    }

    /**
     * Dynamically call base client methods
     *
     * @param string $method
     * @param array $arguments
     * @return Client|mixed
     */
    public function __call(string $method, array $arguments)
    {
        if (!method_exists($this->connection, $method)) {
            throw new DomainException("Method [$method] not exists");
        }

        return $this->connection->{$method}(...$arguments);
    }

    /**
     * @inheritDoc
     */
    public function get(): Collection
    {
        $payload = $this->makePayload();

        $this->clearQueryAfterRequest();

        $response = $this->connection->search($payload);

        if (!empty($this->aggregations)) {
            return collect($response['aggregations'] ?? [])
                ->map(fn($item) => $item['buckets'] ?? []);
        }

        return collect($response['hits']['hits'] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        $payload = [
            'index' => $this->index,
            'body' => $this->prepareBody(false, false),
        ];

        $this->clearQueryAfterRequest();

        return $this->connection->count($payload)['count'] ?? 0;
    }

    /**
     * @inheritDoc
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public function find(int $id): ?array
    {
        $payload = [
            'index' => $this->index,
            '_source' => true,
            'id' => $id,
        ];

        try {
            $document = $this->connection->get($payload);
        } catch (Missing404Exception $e) {
            return null;
        }

        $this->clearQueryAfterRequest();

        return $document;
    }

    /**
     * @inheritDoc
     */
    public function findMany(array $ids): Collection
    {
        $payload = [
            'index' => $this->index,
            '_source' => true,
            'body' => [
                'ids' => $ids
            ],
        ];

        $this->clearQueryAfterRequest();

        return collect($this->connection->mget($payload)['docs'] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function select($columns): Builder
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->columns += $columns;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function limit(int $limit): Builder
    {
        $this->size = $limit;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(int $offset): Builder
    {
        $this->from = $offset;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderBy(string $column, string $direction = 'asc'): Builder
    {
        $direction = $direction === 'asc' ? $direction : 'desc';

        $this->sorts[] = [$column => ['order' => $direction]];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderByRaw(array $sort): Builder
    {
        $this->sorts[] = $sort;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function groupBy(string $column, int $size = 1, string $sortBy = null, string $sortDir = 'desc'): Builder
    {
        $collapse = [
            'field' => $column,
            'inner_hits' => [
                'name' => $column,
                'size' => $size,
                'sort' => [
                    $sortBy ?? $column => $sortDir === 'desc' ? $sortDir : 'asc',
                ],
            ]
        ];

        if (!empty($this->columns)) {
            $collapse['inner_hits']['_source'] = $this->columns;
        }

        $this->collapse = $collapse;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function groupByRaw(array $collapse): Builder
    {
        $this->collapse = $collapse;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function where(string $column, $value): Builder
    {
        $this->musts[] = [
            'term' => [
                $column => $value,
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereNot(string $column, $value): Builder
    {
        $this->notMusts[] = [
            'term' => [
                $column => $value,
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereLike(string $column, string $value): Builder
    {
        $this->musts[] = [
            'wildcard' => [
                $column => $value,
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereNotLike(string $column, string $value): Builder
    {
        $this->notMusts[] = [
            'wildcard' => [
                $column => $value,
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereIn(string $column, array $values): Builder
    {
        $this->musts[] = [
            'terms' => [
                $column => array_values($values),
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereNotIn(string $column, array $values): Builder
    {
        $this->notMusts[] = [
            'terms' => [
                $column => array_values($values),
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereHas(string $column): Builder
    {
        $this->musts[] = [
            'exists' => [
                'field' => $column
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereHasNot(string $column): Builder
    {
        $this->notMusts[] = [
            'exists' => [
                'field' => $column
            ],
        ];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereGreaterThan(string $column, float $value): Builder
    {
        $this->filters = $this->makeRange($column, 'gt', $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereGreaterThanOrEqual(string $column, float $value): Builder
    {
        $this->filters = $this->makeRange($column, 'gte', $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereLessThan(string $column, float $value): Builder
    {
        $this->filters = $this->makeRange($column, 'lt', $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereLessThanOrEqual(string $column, float $value): Builder
    {
        $this->filters = $this->makeRange($column, 'lte', $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function whereRaw(array $clause): Builder
    {
        $this->musts[] = $clause;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function aggr(string $name, string $column, ?int $size = null): Builder
    {
        $this->aggregations[$name] = ['terms' => ['field' => $column]];
        if ($size) {
            $this->aggregations[$name]['field'] = $size;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function aggrRaw(string $name, array $aggregation): Builder
    {
        $this->aggregations[$name] = $aggregation;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function index(string $index): Builder
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @inheritDoc
     */
    public function newQuery(): Builder
    {
        return App::make(static::class)->index($this->getIndex());
    }

    /**
     * @inheritDoc
     */
    public function getConnection(): Client
    {
        return $this->connection;
    }

    /**
     * Get base body
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->makePayload();
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
     * Prepare request body
     *
     * @param bool $withSize
     * @param bool $withFrom
     * @return array
     */
    protected function prepareBody(bool $withSize = true, bool $withFrom = true): array
    {
        $query = [
            'filter' => $this->filters,
            'must' => $this->musts,
            'must_not' => $this->notMusts,
        ];

        $body = [
            'sort' => $this->sorts,
            'query' => ['bool' => $query]
        ];

        if (!empty($this->collapse)) {
            $body['collapse'] = $this->collapse;
        }

        if ($withFrom) {
            $body['from'] = $this->from;
        }

        if ($withSize) {
            $body['size'] = $this->size;
        }

        return $body;
    }

    /**
     * Create base connection instance
     *
     * @param ClientBuilder $clientBuilder
     * @param Contracts\Config $config
     * @return void
     */
    protected function connect(ClientBuilder $clientBuilder, Contracts\Config $config): void
    {
        $this->connection = $clientBuilder
            ->setHosts($config->hosts())
            ->setBasicAuthentication($config->username(), $config->password())
            ->build();
    }

    /**
     * Make payload for search API
     *
     * @return array
     */
    protected function makePayload(): array
    {
        $payload = [
            'index' => $this->index,
            'body' => $this->prepareBody(),
        ];

        if (!empty($this->columns)) {
            $payload['body']['_source'] = $this->columns;
        }

        if (!empty($this->aggregations)) {
            $payload['body']['aggs'] = $this->aggregations;
            $payload['body']['_source'] = false;
        }

        return $payload;
    }

    /**
     * Clear all query bindings
     *
     * @return void
     */
    protected function clearQueryAfterRequest(): void
    {
        $this->columns = [];
        $this->musts = [];
        $this->notMusts = [];
        $this->filters = [];
        $this->aggregations = [];
        $this->size = -1;
        $this->from = 0;
        $this->sorts = [];
        $this->collapse = [];
    }

    /**
     * Make 'range' clause
     *
     * @param string $column
     * @param string $operand
     * @param float $value
     * @return array
     */
    protected function makeRange(string $column, string $operand, float $value): array
    {
        return [
            'range' => [
                $column => [
                    $operand => $value
                ]
            ],
        ];
    }
}
