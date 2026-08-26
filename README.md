# Desk Mate 🎓

Assistant IA d'aide aux devoirs pour élèves du secondaire — un clone de ChatGPT thématisé, avec une vraie base de données derrière (arbre de conversations, tags, suivi des coûts).

Projet réalisé dans le cadre du cours **Projet de développement SGBD**.

## Fonctionnalités

**Obligatoires**
- Sélecteur de modèles (Claude, GPT, Gemini, Grok... via [OpenRouter](https://openrouter.ai))
- Historique des conversations avec titre généré automatiquement
- Réponses en streaming (Server-Sent Events)
- Instructions personnalisées (réglage global + par conversation)

**Bonus**
- 🌳 **Arbre de conversations** : régénérer une réponse ou modifier un message crée une nouvelle branche sans jamais perdre l'ancienne (auto-référence en base, `messages.parent_message_id`)
- 🏷️ Tags sur les conversations (relation N-N)
- 💰 Dashboard de suivi des coûts et de la consommation de tokens, par modèle
- ✅ Suite de tests Pest (reconstruction du fil, autorisations, fork)

## Stack technique

- [Laravel](https://laravel.com) + [Inertia.js](https://inertiajs.com)
- [Vue 3](https://vuejs.org) (Composition API, TypeScript)
- [Tailwind CSS](https://tailwindcss.com) v4 + [shadcn-vue](https://www.shadcn-vue.com)
- MySQL
- [OpenRouter](https://openrouter.ai) (API compatible OpenAI, multi-fournisseurs)

## Schéma de données

Le cœur du modèle : `messages.parent_message_id` est une auto-référence qui transforme la table en arbre. Éditer un message ou régénérer une réponse insère un nouveau message avec le même parent plutôt que de modifier l'existant — deux branches coexistent, rien n'est jamais perdu. `conversations.active_leaf_message_id` retient la branche actuellement affichée.

```
users ──< conversations ──< messages >── models
                │                │
                └──< conversation_tag >── tags
```

## Installation locale

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configurer dans `.env` :
- Les identifiants de la base de données MySQL
- `OPENROUTER_API_KEY` — une clé depuis [openrouter.ai/settings/keys](https://openrouter.ai/settings/keys)

```bash
php artisan migrate --seed
composer run dev
```

L'application est alors disponible sur `http://localhost:8000`.

## Tests

```bash
php artisan test
```
