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
        'published_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Grouped rows carry no key of their own
    public static function perAuthor()
    {
        return self::selectRaw('user_id, count(*) as posts')->groupBy('user_id');
    }
}
