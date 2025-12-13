<?php

namespace App\Scout;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ElasticsearchEngine extends Engine
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Update the given model in the index.
     */
    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $params = ['body' => []];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'index' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey(),
                ]
            ];

            $params['body'][] = array_merge(
                $model->toSearchableArray(),
                $model->scoutMetadata()
            );
        });

        if (!empty($params['body'])) {
            $this->client->bulk($params);
        }
    }

    /**
     * Remove the given model from the index.
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $params = ['body' => []];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey(),
                ]
            ];
        });

        $this->client->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'filters' => $this->filters($builder),
            'size' => $builder->limit,
        ]);
    }

    /**
     * Perform the given search on the engine.
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'filters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);
    }

    /**
     * Perform the given search on the engine.
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $params = [
            'index' => $builder->model->searchableAs(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $builder->query ?? '*',
                                    'fields' => ['*'],
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                ]
                            ]
                        ],
                        'filter' => $options['filters'] ?? [],
                    ]
                ]
            ]
        ];

        if (empty($builder->query)) {
            $params['body']['query'] = ['match_all' => (object)[]];
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size']) && $options['size'] !== null) {
            $params['body']['size'] = $options['size'];
        }

        if ($builder->callback) {
            return call_user_func($builder->callback, $this->client, $builder->query, $params);
        }

        try {
            return $this->client->search($params);
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
            }
            throw $e;
        }
    }

    /**
     * Get the filter array for the query.
     */
    protected function filters(Builder $builder): array
    {
        $filters = [];

        foreach ($builder->wheres as $field => $value) {
            $filters[] = ['term' => [$field => $value]];
        }

        return $filters;
    }

    /**
     * Pluck and return the primary keys of the given results.
     */
    public function mapIds($results): Collection
    {
        $hits = $results['hits']['hits'] ?? [];

        return collect($hits)->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        $hits = $results['hits']['hits'] ?? [];

        if (count($hits) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($hits)->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })
            ->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })
            ->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        $hits = $results['hits']['hits'] ?? [];

        if (count($hits) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($hits)->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })
            ->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })
            ->values();
    }

    /**
     * Get the total count from the given results.
     */
    public function getTotalCount($results): int
    {
        $total = $results['hits']['total'] ?? 0;

        return is_array($total) ? ($total['value'] ?? 0) : $total;
    }

    /**
     * Flush all of the model's records from the engine.
     */
    public function flush($model): void
    {
        $index = $model->searchableAs();

        try {
            $this->client->indices()->delete(['index' => $index]);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Create a search index.
     */
    public function createIndex($name, array $options = []): void
    {
        $params = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
        ];

        if (!empty($options['mappings'])) {
            $params['body']['mappings'] = $options['mappings'];
        }

        try {
            $this->client->indices()->create($params);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 400) { // Index already exists
                throw $e;
            }
        }
    }

    /**
     * Delete a search index.
     */
    public function deleteIndex($name): void
    {
        try {
            $this->client->indices()->delete(['index' => $name]);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }
}
