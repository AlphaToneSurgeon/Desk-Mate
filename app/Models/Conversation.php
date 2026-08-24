<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'title', 'custom_instructions', 'active_leaf_message_id'];
    
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function messages(){
        return $this->hasMany(Message::class);
    }
    public function activeLeaf(){
        return $this->belongsTo(Message::class,'active_leaf_message_id');
    }
    public function tags(){
        return $this ->belongsToMany(Tag::class,'conversation_tag');
    }
    public function activeThread(): array
    {
        return $this->threadEndingAt($this->active_leaf_message_id);
    }

    /**
     * Comme activeThread(), mais en remontant depuis N'IMPORTE quel message
     * de cette conversation plutôt que forcément la feuille active. Sert à
     * reconstruire "l'historique tel qu'il était" au moment de régénérer une
     * réponse ou de modifier un message plus haut dans l'arbre.
     */
    public function threadEndingAt(?int $leafMessageId): array
    {
        // Une seule requête pour toute la conversation : on évite le N+1
        // qu'on aurait en remontant message par message avec une requête à chaque fois.
        $messages = $this->messages()->get()->keyBy('id');

        $thread = [];
        $current = $leafMessageId ? $messages->get($leafMessageId) : null;

        while ($current) {
            $thread[] = $current;
            $current = $current->parent_message_id
                ? $messages->get($current->parent_message_id)
                : null;
        }

        // On a construit feuille → racine, l'affichage veut racine → feuille.
        return array_reverse($thread);
    }
}
