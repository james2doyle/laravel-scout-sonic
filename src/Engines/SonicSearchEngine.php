<?php

namespace james2doyle\SonicScout\Engines;

use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Engines\Engine;
use SonicSearch\ChannelFactory;

class SonicSearchEngine extends Engine
{
    /**
     * The Sonic search client.
     *
     * @var \SonicSearch\SearchChannel
     */
    protected $search;

    /**
     * The Sonic index/push client.
     *
     * @var \SonicSearch\IngestChannel
     */
    protected $ingest;

    /**
     * The Sonic index/push client.
     *
     * @var \SonicSearch\ControlChannel
     */
    protected $control;

    /**
     * Create a new engine instance.
     *
     * @throws \SonicSearch\NoConnectionException If connecting to the sonic instance failed.
     * @throws \SonicSearch\AuthenticationException If the given password was wrong.
     * @throws \SonicSearch\ProtocolException If Sonic misbehaved or announced an unsupported protocol version.
     *
     * @return void
     */
    public function __construct(ChannelFactory $factory)
    {
        $this->ingest = $factory->newIngestChannel();
        $this->search = $factory->newSearchChannel();
        $this->control = $factory->newControlChannel();

        $this->ingest->connect();
        $this->search->connect();
        $this->control->connect();
    }

    /**
     * Tear down the clients
     */
    public function __destruct()
    {
        $this->ingest->quit();
        $this->search->quit();
        $this->control->quit();
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @throws \SonicSearch\NoConnectionException If the connection to Sonic has been lost in the meantime.
     * @throws \SonicSearch\CommandFailedException If execution of the command failed for which-ever reason.
     * @throws \SonicSearch\InvalidArgumentException If the given set of terms could not fit into Sonics receive buffer.
     *
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->ingest->ping();

        $messages = $models->map(function ($model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            $bucket = class_basename($model);

            return [
                str_plural($bucket),
                $bucket,
                $model->getScoutKey(),
                is_array($searchableData) ? implode(' ', array_values($searchableData)) : $searchableData,
            ];
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
     * @throws \SonicSearch\NoConnectionException If the connection to Sonic has been lost in the meantime.
     * @throws \SonicSearch\CommandFailedException If execution of the command failed for which-ever reason.
     *
     * @return void
     */
    public function delete($models)
    {
        $this->ingest->ping();

        $models->map(function ($model) {
            $bucket = class_basename($model);
            $this->ingest->flush(str_plural($bucket), $bucket, $model->getScoutKey());
        })->values()->all();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int                     $limit
     * @param  int                     $offset
     * @throws \SonicSearch\NoConnectionException If the connection to Sonic has been lost in the meantime.
     * @throws \SonicSearch\CommandFailedException If execution of the command failed for which-ever reason.
     *
     * @return array
     */
    private function performSearch(Builder $builder, int $limit = null, int $offset = null)
    {
        $this->search->ping();

        $bucket = class_basename($builder->model);
        return $this->search->query(str_plural($bucket), $bucket, $builder->query, $limit, $offset);
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
        if (count($results) === 0) {
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
        });;
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
