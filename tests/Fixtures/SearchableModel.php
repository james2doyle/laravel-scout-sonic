<?php

namespace james2doyle\SonicScout\Tests\Fixtures;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class SearchableModel extends Model
{
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id'];

    public function toSearchableArray()
    {
        return [$this->id, 'searchable model'];
    }

    public function getScoutKey() {
        return $this->id;
    }

    public function scoutMetadata()
    {
        return [];
    }
}
