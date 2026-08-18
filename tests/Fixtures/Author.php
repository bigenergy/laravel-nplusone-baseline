<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Fixtures;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property-read Collection<int, Book> $books
 */
final class Author extends Model
{
    /** @var bool */
    public $timestamps = false;

    /** @var string */
    protected $table = 'authors';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
