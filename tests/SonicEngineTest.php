<?php

namespace james2doyle\SonicScout\Tests;

use james2doyle\SonicScout\Tests\Fixtures\SearchableModelWithLocale;
use Psonic\Control;
use Psonic\Ingest;
use Psonic\Search;
use Illuminate\Support\Str;
use stdClass;
use Mockery;
use Laravel\Scout\Builder;
use PHPUnit\Framework\TestCase;
use james2doyle\SonicScout\Engines\SonicSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use james2doyle\SonicScout\Tests\Fixtures\SearchableModel;

class SonicEngineTest extends TestCase
{

    protected function tearDown(): void
    {
        Mockery::close();
    }

    protected function mockChannels(): array
    {
        $ingest = Mockery::mock(Ingest::class);
        $ingest->shouldReceive('connect')->withAnyArgs()->once();
        $ingest->shouldReceive('disconnect')->withNoArgs()->once();

        $search = Mockery::mock(Search::class);
        $search->shouldReceive('connect')->withAnyArgs()->once();
        $search->shouldReceive('disconnect')->withNoArgs()->once();

        $control = Mockery::mock(Control::class);
        $control->shouldReceive('connect')->withAnyArgs()->once();
        $control->shouldReceive('disconnect')->withNoArgs()->once();

        return compact('ingest', 'search', 'control');
    }

    public function testItCanPushObjectsToTheIndex()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());

        $ingest->shouldReceive('ping')->withNoArgs()->once();
        $ingest->shouldReceive('push')->once();
        $control->shouldReceive('consolidate')->withNoArgs()->once();

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $engine->update(Collection::make([new SearchableModel(['id' => 1])]));
    }

    /** @test */
    public function itCanAddObjectsToTheIndexWithLocale()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());

        $ingest->shouldReceive('ping')->withNoArgs()->once();
        $ingest->shouldReceive('push')->withArgs(function () {
            $args = func_get_args();
            $expected = [
                'SearchableModels',
                'SearchableModel',
                '1',
                '1 searchable model',
                'none'
            ];

            return $args == $expected;
        });
        $control->shouldReceive('consolidate')->withNoArgs()->once();

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $engine->update(Collection::make([new SearchableModelWithLocale(['id' => 1])]));
    }

    public function testItCanDeleteObjectsFromTheIndex()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());
        $ingest->shouldReceive('ping')->withNoArgs()->once();
        $ingest->shouldReceive('flusho')->withArgs(function () {
            $args = func_get_args();
            $expected = [
                'SearchableModels',
                'SearchableModel',
                1,
            ];
            return $args === $expected;
        });

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $engine->delete(Collection::make([new SearchableModel(['id' => 1])]));
    }

    /** @test */
    public function testItCanSearchTheIndex()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());
        $search->shouldReceive('ping')->withNoArgs()->once();

        $search->shouldReceive('query')->withArgs(function () {
            $args = func_get_args();
            $expected = [
                'SearchableModels',
                'SearchableModel',
                'searchable',
                null,
                null,
            ];

            return $args === $expected;
        });

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $builder = new Builder(new SearchableModel, 'searchable');
        $engine->search($builder);
    }

    /** @test */
    public function testItCanSearchTheIndexWithTakeLimit()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());
        $search->shouldReceive('ping')->withNoArgs()->once();

        $search->shouldReceive('query')->withArgs(function () {
            $args = func_get_args();
            $expected = [
                'SearchableModels',
                'SearchableModel',
                'searchable',
                3,
                null,
            ];

            return $args === $expected;
        });

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $builder = (new Builder(new SearchableModel, 'searchable'))->take(3);
        $engine->search($builder);
    }

    /** @test */
    public function testItCanMapCorrectlyToTheModels()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive('getScoutModelsByIds')->andReturn($models = Collection::make([
            new SearchableModel(['id' => 1]),
        ]));
        $builder = Mockery::mock(Builder::class);
        $results = $engine->map($builder, [1], $model);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function testItCanMapCorrectlyToTheModelsWhenFiltered()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());
        $model = Mockery::mock(stdClass::class);

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $model->shouldReceive('getScoutModelsByIds')->andReturn($models = Collection::make([
            new SearchableModel(['id' => 1]),
            new SearchableModel(['id' => 2]),
            new SearchableModel(['id' => 3]),
            new SearchableModel(['id' => 4]),
        ]));

        $builder = Mockery::mock(Builder::class);
        $builder->wheres = ['id' => 1];
        $results = $engine->map($builder, [1, 2, 3, 4], $model);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function testItCanHandleDefaultSearchableArray()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());

        $ingest->shouldReceive('ping')->withNoArgs()->once();
        $ingest->shouldReceive('push')->once();
        $control->shouldReceive('consolidate')->withNoArgs()->once();

        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive('getScoutKey')->andReturn(1);
        $model->shouldReceive('toSearchableArray')->andReturn(['id' => 1, 'email' => 'hello@example.com']);
        $model->shouldReceive('searchableAs')->andReturn('SearchableModel');
        $engine = new SonicSearchEngine($ingest, $search, $control);
        $engine->update(Collection::make([$model]));
    }

    public function testItCanHandleAnEmptyResultset()
    {
        /**
         * @var Search|Mockery\MockInterface $search
         * @var Ingest|Mockery\MockInterface $ingest
         * @var Control|Mockery\MockInterface $control
         */
        extract($this->mockChannels());
        $model = Mockery::mock(stdClass::class);

        $engine = new SonicSearchEngine($ingest, $search, $control);
        $model->shouldReceive('newCollection')->andReturn($models = Collection::make([
            new Collection()
        ]));

        $builder = Mockery::mock(Builder::class);
        $builder->wheres = ['id' => 1];
        $results = $engine->map($builder, [0 => ""], $model);
        $this->assertEmpty($results->first());
    }
}
