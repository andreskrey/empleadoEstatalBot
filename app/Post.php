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
    protected $fillable = ['subreddit', 'thing', 'url', 'status', 'tries', 'comment_id', 'parent_id'];
    protected $table = 'posts';

    public function children() {
        return $this->hasMany('empleadoEstatalBot\Post','parent_id');
    }
    public function parent() {
        return $this->belongsTo('empleadoEstatalBot\Post','parent_id');
    }
}