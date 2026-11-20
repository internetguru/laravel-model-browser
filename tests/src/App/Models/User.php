<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    // A limited relation, which a relation filter must not turn into a limited match
    public function recentPosts()
    {
        return $this->hasMany(Post::class)->latest('id')->limit(1);
    }

    public function getPostCountAttribute(): int
    {
        return $this->posts->count();
    }

    public static function summary()
    {
        return self::query();
    }

    public static function withPosts()
    {
        return self::with('posts');
    }

    // One row per post, so a user with two posts is listed twice
    public static function perPost()
    {
        return self::join('posts', 'posts.user_id', '=', 'users.id')->select('users.*');
    }
}
