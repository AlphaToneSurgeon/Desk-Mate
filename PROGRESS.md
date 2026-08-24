# PROGRESS.md — État du projet "Desk Mate"

> Ce fichier sert de point de reprise pour une nouvelle conversation Claude Code.
> Instruction pour Claude : lis ce fichier en entier avant de reprendre le travail.

## Contexte du projet

- **Cours** : Projet de développement SGBD (IDV4/IDV3), établissement belge (cours.tsix.be)
- **Sujet imposé** : reconstruire un ChatGPT-like avec une vraie base de données derrière, thème + personnalité au choix
- **Thème choisi** : "Desk Mate" — assistant d'aide aux devoirs pour élèves du secondaire (a remplacé un premier thème "LexBot" légal, abandonné au jour 4)
- **Deadline officielle** : samedi 20 juin 2026 23h59 (large marge — on avance en sprint pour rattraper du retard)
- **Consignes complètes** : https://cours.tsix.be/cours/projet-de-developpement-sgbd/mini-chatgpt/consignes-examen.html
- **Livrables attendus** : repo GitHub public + rapport PDF (template séparé) + cahier métacognitif d'usage de l'IA (template séparé) + soutenance orale ~15 min
- **⚠️ Le repo n'est PAS ENCORE poussé sur GitHub** — actuellement local uniquement. À faire avant la deadline.

## Politique d'usage de l'IA (IMPORTANT — mode de collaboration)

Consigne officielle du cours :
| Usage | Autorisé ? |
|---|---|
| IA pour la rédaction du rapport | ✅ Oui |
| IA pour l'assistance au code (tab completion, chat) | ✅ Oui |
| Agents de programmation qui réalisent le projet à la place de l'étudiant | ❌ Non |

**Mode de travail établi depuis le jour 1** : Claude agit comme tuteur, pas comme exécutant.
- Claude explique les concepts, fait la plomberie mécanique/répétitive (config, scaffolding, refactors techniques sans décision métier) et vérifie/teste.
- L'étudiant écrit le code métier réel (logique des contrôleurs, migrations, relations Eloquent, UI) après une explication détaillée, souvent avec un plan numéroté.
- Claude relit systématiquement le code écrit par l'étudiant, cherche les vrais bugs, explique le "pourquoi" (utile pour la soutenance orale).
- Toujours tester de bout en bout (navigateur réel ou tinker + vérif en base MySQL) avant de commit.
- Commit à chaque jalon fonctionnel, avec message clair.
- **Garde ce mode de collaboration dans la nouvelle conversation.**

## Environnement local (Windows + Laragon) — pièges déjà résolus

- Projet réel : `C:\Users\adrit\Desktop\IFOSUP\projet sgdb`
- Alias Laragon (jonction NTFS, pas un lien symbolique — pas besoin de droits admin) : `C:\Users\adrit\Desktop\IFOSUP\Web Dev\Projets\deskmate` → pointe vers le dossier ci-dessus. **Laragon surveille ce dossier `Web Dev\Projets`, PAS `C:\laragon\www`** sur cette machine.
- URL locale : **http://deskmate.test/** (nécessite Apache + MySQL démarrés dans Laragon — voir plus bas)
- Après création/renommage d'une jonction, il faut un **Reload** manuel dans Laragon (clic droit icône barre système) pour que le domaine `.test` soit généré — pas automatique en ligne de commande.
- Base de données MySQL : `deskmate` (a été renommée depuis `lexbot` au jour 4)
- **MySQL et Apache s'arrêtent parfois entre les sessions** → si erreur "connexion refusée", demander à l'utilisateur de faire "Start All" dans Laragon.
- **PHP du PATH par défaut est cassé** (pas d'openssl) — toujours utiliser explicitement `/c/laragon/bin/php/php-8.3.26-Win32-vs16-x64/php.exe` pour les commandes artisan/composer.
- Composer : `/c/laragon/bin/composer/composer.phar` (+ bin global `/c/Users/adrit/AppData/Roaming/Composer/vendor/bin` pour la commande `laravel`)
- Client MySQL CLI : `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -h 127.0.0.1 deskmate -e "..."`
- Build frontend : `npm run build` (Vite 8 / Rolldown — assez récent, quelques quirks, voir bugs plus bas)
- Preview alternative : `.claude/launch.json` config `lexbot-serve` (nom historique) lance `php artisan serve` sur le port 8000

## Stack technique

Laravel 12/13 + Inertia + **Vue 3 Composition API uniquement** (Options API = éliminatoire selon les consignes) + TypeScript + Tailwind v4 + shadcn-vue (via reka-ui) + Vite 8.

LLM : **OpenRouter** (pas Anthropic en direct — clé fournie par le prof). Format de requête compatible OpenAI : `POST https://openrouter.ai/api/v1/chat/completions`, header `Authorization: Bearer <clé>`, le `system` fait partie du tableau `messages` (contrairement à l'API Anthropic native qui a un paramètre `system` séparé). Modèles identifiés par des slugs préfixés fournisseur : `anthropic/claude-sonnet-4.5`, `openai/gpt-4o`, `google/gemini-2.5-flash`, `x-ai/grok-4.5`.

## Schéma de données (complet, migré, stable)

- `users` (+ colonne `custom_instructions` ajoutée)
- `models` (Eloquent : classe `AiModel`, pas `Model` — collision avec la classe de base Eloquent) : slug, display_name, provider, prix input/output par million de tokens, is_active
- `conversations` : user_id, title, custom_instructions, active_leaf_message_id (FK ajoutée dans une migration séparée — dépendance circulaire avec messages)
- `messages` : conversation_id, **parent_message_id (auto-référence — cœur du mécanisme de fork)**, model_id, role, content, input_tokens, output_tokens, created_at (PAS de updated_at — `const UPDATED_AT = null;` sur le modèle)
- `tags`, `conversation_tag` (pivot N-N) — **tables créées et migrées, mais AUCUN contrôleur/UI encore** (à faire)

Mécanisme central : `Conversation::activeThread()` / `Conversation::threadEndingAt(?int $leafId)` — remonte la chaîne `parent_message_id` depuis n'importe quelle feuille jusqu'à la racine, puis inverse pour l'ordre d'affichage. Utilisé partout (construction du contexte envoyé au LLM, affichage UI, régénération, édition).

## Avancement par jour

- **Jour 1** : scaffold Laravel (starter kit Vue officiel), schéma + migrations, modèles Eloquent avec relations (dont l'auto-référence)
- **Jour 2** : `OpenRouterClient` (appel non-streamé), `ConversationController` + `MessageController` (CRUD, premier appel LLM)
- **Jour 3** : streaming SSE (`OpenRouterClient::streamMessage`, `MessageController::stream`, `response()->stream()`), UI de chat Vue (`Show.vue`/`Index.vue`) avec lecture SSE côté client (`fetch` + `ReadableStream`), rendu Markdown (`marked` + `DOMPurify` pour la sécurité XSS)
- **Jour 4** : rebrand complet LexBot → Desk Mate, thème coloré bleu/orange pour public jeune (`resources/css/app.css`), logo GraduationCap, personnalité par défaut pédagogique (`config/assistant.php` — "guide, ne donne jamais la réponse toute faite"), page de réglages "Instructions personnalisées" (`settings/Assistant.vue` + `AssistantController`)
- **Jour 5 — TERMINÉ** : ✅ fork de conversation (régénérer/modifier = créent un frère en base). ✅ Tags (créer/attacher/détacher, N-N). ✅ Dashboard coûts/tokens (`DashboardController`, agrégation SQL groupée par modèle). ✅ Tests Pest (46/46 passent — reconstruction du fil, autorisation, fork via `Http::fake()`).

## Fonctionnalités obligatoires (éliminatoires) — TOUTES FAITES ✅

- Sélecteur de modèles ✅
- Historique + titre auto-généré ✅
- Streaming SSE ✅
- Instructions personnalisées (réglage global + override par conversation + persona par défaut) ✅
- Composition API partout ✅

## Fonctionnalités bonus — état

- ✅ **Fork/arbre de conversation** (régénérer + modifier, jour 5)
- ✅ **Tags/dossiers** (créer/attacher/détacher, N-N)
- ✅ **Suivi coûts/tokens** — dashboard avec agrégation SQL par modèle (`DashboardController`)
- ✅ **Tests Pest** — `tests/Feature/ConversationThreadTest.php`, `ConversationAuthorizationTest.php`, `MessageForkTest.php` (46/46 passent, `php artisan test`)
- ⬜ Partage de conversations — pas commencé
- ⬜ Outils LLM (calculatrice, recherche...) — pas commencé

## Bugs rencontrés et corrigés (utile pour la soutenance + éviter de refaire les mêmes)

1. PHP du PATH ≠ PHP de Laragon (openssl manquant) → toujours chemin explicite
2. Composer/`laravel new` échouaient sur le rate-limit de l'API GitHub non authentifiée → `composer config -g github-oauth.github.com <token>`
3. Dépendance circulaire `conversations.active_leaf_message_id` ↔ `messages` → résolue en 3 migrations (créer conversations sans la contrainte, créer messages, ajouter la contrainte après)
4. Mass assignment Eloquent : `$fillable` doit être mis à jour à chaque nouvelle colonne — oubli sur `User::custom_instructions` a fait échouer silencieusement la sauvegarde (aucune erreur visible, juste `NULL` en base)
5. `<textarea />` auto-fermant casse le compilateur Vue (élément "raw text" HTML) → toujours `<textarea></textarea>`
6. Un commentaire JS contenant le texte littéral `</script>` casse le parsing du bloc `<script>` d'un composant Vue
7. `defineOptions()` est hoisté hors du `setup()` → ne peut pas référencer `props`
8. Laragon sur cette machine surveille `Web Dev\Projets`, pas `www` — vérifier avant de créer une jonction

## Prochaines étapes

**Jour 6 (tout ce qui reste)** :
- Polish UI (facultatif si le temps manque — le socle obligatoire + bonus sont déjà tous faits)
- Nettoyage du repo (vérifier `.gitignore`, retirer les données de test, revoir les commits)
- **Push GitHub (public)** — pas encore fait, obligatoire pour la remise
- Rédaction du rapport PDF + cahier métacognitif d'usage de l'IA (templates à récupérer sur la page consignes du cours : `rapport_template_idv3.docx`, `cahier_metacognitif_idv3.docx`)
- Révision soutenance orale (~15 min) : schéma/relations/intégrité, implémentation du streaming, choix techniques, difficultés rencontrées + 2 questions théoriques tirées au sort (ORM, eager loading/N+1, migrations/seeders/factories, contrainte d'intégrité référentielle/CASCADE, index, transaction, WebSockets vs SSE, Composition API)
- Optionnel si temps disponible : partage de conversations, outils LLM (calculatrice, recherche)

**État global** : les 4 fonctionnalités obligatoires + 3 fonctionnalités bonus (fork, tags, coûts) + tests sont fonctionnels et testés. Le projet est en bon état pour viser une note élevée ; l'essentiel du travail restant est administratif (rapport, cahier métacognitif, publication GitHub) plutôt que technique.
