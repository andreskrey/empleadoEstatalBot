<?php

namespace empleadoEstatalBot;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['subreddit', 'thing', 'url', 'status', 'tries'];
    protected $table = 'posts';
}