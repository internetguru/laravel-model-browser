<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A to-many relation off the test user, so relation filters have something to reach for.
 */
class Post extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'title',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
