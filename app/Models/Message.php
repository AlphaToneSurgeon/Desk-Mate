<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'parent_message_id', 'model_id', 'role', 'content', 'input_tokens', 'output_tokens'];
    
    public function conversation (){
        return $this->belongsTo(Conversation::class);
    }
    public function model(){
        return $this->belongsTo(AiModel::class, 'model_id');
}
    public function parent(){
        return $this->belongsTo(Message::class, 'parent_message_id');
    }
    public function children (){
        return $this->hasMany(Message::class,'parent_message_id');
    }
}
