<?php

namespace james2doyle\SonicScout\Tests;

use stdClass;
use Mockery;
use Laravel\Scout\Builder;
use PHPUnit\Framework\TestCase;
use james2doyle\SonicScout\Engines\SonicSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use james2doyle\SonicScout\Tests\Fixtures\SearchableModel;

class SonicEngineTest extends TestCase
{

    /**
     * @var SonicSearchEngine|null
     */
    private $engine;

    protected function setUp(): void
    {
        $this->engine = new SonicSearchEngine();
    }
    /** @test */
    public function itCanInitiateThesearchEngine()
    {
        $this->assertInstanceOf(SonicSearchEngine::class, $this->engine);
    }
    /** @test */
    public function itCanPushObjectsToTheIndex()
    {
        $this->engine->update(Collection::make([new SearchableModel(['id' => 1])]));
    }

    /** @test */
    public function itCanDeleteObjectsFromTheIndex()
    {
        $this->engine->delete(Collection::make([new SearchableModel(['id' => 1])]));
    }

    /** @test */
    public function itCanSearchTheIndex()
    {
        $builder = new Builder(new SearchableModel, 'searchable');
        $this->engine->search($builder);
    }

    /** @test */
    public function itCanMapCorrectlyToTheModels()
    {
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive('getScoutModelsByIds')->andReturn($models = Collection::make([
            new SearchableModel(['id' => 1]),
        ]));
        $builder = Mockery::mock(Builder::class);
        $results = $this->engine->map($builder, [1], $model);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function itCanMapCorrectlyToTheModelsWhenFiltered()
    {
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive('getScoutModelsByIds')->andReturn($models = Collection::make([
            new SearchableModel(['id' => 1]),
            new SearchableModel(['id' => 2]),
            new SearchableModel(['id' => 3]),
            new SearchableModel(['id' => 4]),
        ]));

        $builder = Mockery::mock(Builder::class);
        $builder->wheres = ['id' => 1];
        $results = $this->engine->map($builder, [1, 2, 3, 4], $model);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function itCanHandleDefaultSearchableArray()
    {
        $model = Mockery::mock(stdClass::class);
        $model->shouldReceive('getScoutKey')->andReturn(1);
        $model->shouldReceive('toSearchableArray')->andReturn(['id' => 1, 'email' => 'hello@example.com']);

        $this->engine->update(Collection::make([$model]));
    }
}
