<?php

declare(strict_types=1);

namespace BigEnergy\NPlusOne\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class Database
{
    /**
     * One hasMany/belongsTo pair is all the shape an N+1 needs.
     */
    public static function migrate(): void
    {
        Schema::create('authors', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('books', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id');
            $table->string('title');
        });
    }

    public static function seed(): Author
    {
        $author = Author::create(['name' => 'Ursula']);

        Book::create(['author_id' => $author->id, 'title' => 'The Dispossessed']);
        Book::create(['author_id' => $author->id, 'title' => 'The Left Hand of Darkness']);

        return $author;
    }
}
