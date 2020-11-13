<?php

namespace james2doyle\SonicScout\Engines;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Psonic\Control;
use Psonic\Ingest;
use Psonic\Search;

class SonicSearchEngine extends Engine
{
    /**
     * The Sonic search client.
     *
     * @var Search
     */
    protected $search;

    /**
     * The Sonic index/push client.
     *
     * @var Ingest
     */
    protected $ingest;

    /**
     * The Sonic index/push client.
     *
     * @var Control
     */
    protected $control;

    /**
     * Create a new engine instance.
     *
     * @param  Ingest  $ingest
     * @param  Search  $search
     * @param  Control  $control
     * @param  string  $password
     * @throws \Psonic\Exceptions\ConnectionException
     */
    public function __construct(Ingest $ingest, Search $search, Control $control, string $password = 'secretPassword')
    {
        $this->ingest = $ingest;
        $this->search = $search;
        $this->control = $control;

        $this->ingest->connect($password);
        $this->search->connect($password);
        $this->control->connect($password);
    }

    /**
     * Tear down the clients
     */
    public function __destruct()
    {
        $this->ingest->disconnect();
        $this->search->disconnect();
        $this->control->disconnect();
    }
    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     *
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->ingest->ping();

        $self = $this;

        $messages = $models->map(function ($model) use ($self) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            $collection = $self->getCollectionFromModel($model);
            $bucket = $self->getBucketFromModel($model);

            $message = [
                $collection,
                $bucket,
                $model->getScoutKey(),
                is_array($searchableData) ? implode(' ', array_values($searchableData)) : $searchableData,
            ];

            if (method_exists($model, 'getSonicLocale')) {
                $locale = $model->getSonicLocale();
                if ($locale) {
                    $message[] = $locale;
                }
            }

            return $message;
        })->filter()->all();

        if (! empty($messages)) {
            foreach ($messages as $message) {
                $this->ingest->push(...$message);
            }

            // save to disk
            $this->control->consolidate();
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $this->ingest->ping();

        $self = $this;

        $models->map(function ($model) use ($self) {
            $collection = $self->getCollectionFromModel($model);
            $bucket = $self->getBucketFromModel($model);
            $this->ingest->flusho($collection, $bucket, $model->getScoutKey());
        })->values()->all();
    }

    /**
     * Perform the given search on the engine.
     *
     * @return array
     */
    private function performSearch(Builder $builder, int $limit = null, int $offset = null)
    {
        $this->search->ping();

        $collection = $this->getCollectionFromModel($builder->model);
        $bucket = $this->getBucketFromModel($builder->model);
        return $this->search->query($collection, $bucket, $builder->query, $limit, $offset);
    }

    /**
     * Generate the collection name based on the model
     *
     * @param Model $model
     * @return string
     */
    private function getCollectionFromModel($model)
    {
        return Str::plural($this->getBucketFromModel($model));
    }

    /**
     * Generate the collection name based on the model
     *
     * @param Model $model
     * @return string
     */
    private function getBucketFromModel($model)
    {
        return $model->searchableAs();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     *
     * @return array
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, $builder->limit);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return array
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, $perPage, $page - 1);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  array  $results
     * @return array
     */
    public function mapIds($results)
    {
        return $results;
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results) === 1 && empty(reset($results))) {
            return $model->newCollection();
        }

        $objectIdPositions = array_flip($results);

        $result = $model->getScoutModelsByIds($builder, $results)
            ->filter(function ($model) use ($results) {
                return in_array($model->getScoutKey(), $results);
            });

        // sonic has no way to understand filters/wheres so we fake it on the collection
        foreach ($builder->wheres as $key => $value) {
            $result = $result->where($key, $value);
        }

        return $result->sortBy(function($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        });
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return count($results);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $this->delete(\collect($model));
    }
}
