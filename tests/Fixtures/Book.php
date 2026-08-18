<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $author_id
 * @property string $title
 * @property-read Author $author
 */
final class Book extends Model
{
    /** @var bool */
    public $timestamps = false;

    /** @var string */
    protected $table = 'books';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
