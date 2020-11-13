Laravel Scout Sonic Driver
==========================

Search Eloquent Models using [Sonic](https://github.com/valeriansaliou/sonic) indexes.

1. [Implementation](#implementation)
2. [Installation](#installation)
3. [Usage](#usage)

Implementation <div id="implementation"></div>
-------------

When implementing the `toSearchableArray` method, you need to provide an array that will be coerced into a string (the engine just joins with a `' '`) as Sonic can only index strings. So you need to provide a "stringified" (index string) version of your model. The default `toArray` works but it is probably too much noise for reasonable usage.

Here is an example of the string I used when I was developing this Engine:

```php
public function toSearchableArray()
{
    return array_filter([$this->display_name, $this->first_name, $this->last_name]);
}
```

For me, this builds a nice string for search that can match on a "name". In my application, the concept of "name" is either the User display name or first/last name.

---

If the locale is known, you can also create the `getSonicLocale()` method on your model, which returns the locale. It will then get passed to the Sonic `PUSH` calls:

```php
// an ISO 639-3 locale code eg. eng for English (if set, the locale must be a valid ISO 639-3 code; if set to none, lexing will be disabled; if not set, the locale will be guessed from text)
public function getSonicLocale() {
    return 'none';
}
```

Installation <div id="installation"></div>
------------

If you haven't already you should [install Laravel Scout](https://laravel.com/docs/5.8/scout#installation) to
your project and apply the `Laravel\Scout\Searchable` trait to any Eloquent models you would like to make searchable.

Install this package via **Composer**

`composer require james2doyle/laravel-scout-sonic`

**Note: if you have Laravel >= 5.5 you can skip this step because of Package Auto-Discovery.**

Next add the ServiceProvider to the Package Service Providers in `config/app.php`


```php
/*
 * Package Service Providers...
 */
james2doyle\SonicScout\Providers\SonicScoutServiceProvider::class,
```

Append the default configuration to `config/scout.php`

```php
/*
|--------------------------------------------------------------------------
| Sonic Configuration
|--------------------------------------------------------------------------
|
| Here you may configure your Sonic settings.
|
*/

'sonic' => [
    'address' => \env('SONIC_ADDRESS', 'localhost'),
    'port' => \env('SONIC_PORT', 1491),
    'password' => \env('SONIC_PASSWORD'),
    'connection_timeout' => \env('SONIC_CONNECTION_TIMEOUT', 10),
    'read_timeout' => \env('SONIC_READ_TIMEOUT',  5)
],
```

Set `SCOUT_DRIVER=sonic` in your `.env` file

In addition there is no need to use the `php artisan scout:import` command.


Usage <div id="usage"></div>
-----

Simply call the `search()` method on your `Searchable` models:

`$users = App\User::search('bro')->get();`

Simple constraints can be applied using the `where()` builder method:

`$users = App\User::search('bro')->where('active', 1)->get();`

**Note: Sonic does not support the concept of "where", so the where is applied at the collection level not the query!**

### Pagination

Sonic cannot support real pagination because Sonic does not return proper paging or total information. It simply returns all the results for a given query.

There is a naive implementation of pagination in place but it probably isn't perfect as it doesn't take into account the "where" filter.

For more usage information see the [Laravel Scout Documentation](https://laravel.com/docs/5.3/scout).
