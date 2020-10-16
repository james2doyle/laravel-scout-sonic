<?php

namespace james2doyle\SonicScout\Tests\Fixtures;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class SearchableModelWithLocale extends SearchableModel
{

    public function getSonicLocale() {
        return 'none';
    }

}
