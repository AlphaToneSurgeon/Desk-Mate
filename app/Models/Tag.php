<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['user_id', 'name', 'color'];
  
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function conversations(){
        return $this->belongsToMany(Conversation::class,'conversation_tag');
    }

}
