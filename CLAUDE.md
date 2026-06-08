Crée un fichier CLAUDE.md à la racine du projet avec ce contenu exact :

# BlindBeat — contexte projet

## Stack
- Laravel 13, Livewire 3, Alpine.js, Tailwind CSS
- SQLite (dev), MariaDB (prod)
- Laravel Reverb (WebSockets)
- Pest (tests)
- Audio : previews 30s API Deezer — URLs uniquement, zéro fichier audio stocké
- Preview URL et cover URL récupérées via API Deezer au moment de StartRoundAction, transmises dans le payload RoundStarted — jamais stockées en DB

## Conventions
- UUIDs sur toutes les tables (HasUuids)
- Enums PHP natifs pour les colonnes status
- Soft deletes uniquement sur users et playlists
- Nommage : snake_case DB, PascalCase models, kebab-case routes
- Pas de Repository pattern — Eloquent direct dans les actions/controllers
- Actions simples dans app/Actions/

## Design
- Ultra moderne, hyper clean, pastel light
- Pas de dark mode
- Composants Livewire pour tout le temps réel

## Modèles & relations
- User → hasMany Playlist, hasMany GamePlayer
- Playlist → belongsTo User, hasMany PlaylistTrack, hasMany Room
- PlaylistTrack → belongsTo Playlist (deezer_track_id bigint, pas de fichier)
- Room → belongsTo Playlist, hasMany GamePlayer, hasMany Round (code 6 chars unique)
- GamePlayer → belongsTo Room, belongsTo User (nullable), hasMany Answer
- Round → belongsTo Room, belongsTo PlaylistTrack, hasMany Answer
- Answer → belongsTo Round, belongsTo GamePlayer

## Actions implémentées
- CreateRoomAction → retourne GamePlayer (avec relation room chargée via setRelation)
- JoinRoomAction → retourne GamePlayer
- StartRoundAction → passe round en playing, stocke started_at, dispatch RoundStarted
- EvaluateAnswerAction → normalisation Unicode (accents), bonus vitesse linéaire (+500 max), idempotence double-réponse, déclenche EndRoundAction si dernier joueur actif
- EndRoundAction → round → revealed, stocke ended_at, dispatch RoundEnded, schedule ProcessRoundEnd (5s delay) — idempotente si round déjà revealed

## Jobs
- ProcessRoundEnd → passe round en finished, démarre round suivant (StartRoundAction) ou passe room en finished + dispatch GameEnded — idempotent si round déjà finished

## Events (broadcast)
- GameStarted → channel game.{room_id}
- RoundStarted → channel game.{room_id} — payload : round_number, deezer_track_id, duration
- RoundEnded → channel game.{room_id} — payload : correct_answer (title, artist, cover_url), answers + scores
- GameEnded → channel game.{room_id} — payload : leaderboard final
- PlayerJoined → presence channel room.{code} — payload : id, display_name, score

## Composants Livewire
- Lobby — presence channel room.{code}, isHost = premier joueur joined_at ASC, refreshPlayers via echo listener
- GameStage — états waiting/playing/revealed/finished, timer Alpine.js countdown, submit réponse via fetch POST /api/answers (X-CSRF-TOKEN header), listeners RoundStarted/RoundEnded/GameEnded

## Routes notables
- POST /api/answers → AnswerController@store (session game_player_id, retourne JSON {correct, points_earned, correct_answer})
- GET /rooms/create doit être déclaré AVANT GET /rooms/{code} dans web.php (sinon "create" est capturé comme {code})
- POST /rooms/{code}/start → auth middleware, vérifie host via user_id (pas session)

## Conventions établies
- forceDelete() pour la suppression de compte utilisateur (SoftDeletes = récupération admin, pas auto-suppression)
- ?string pour les IDs UUID nullables dans les type hints (pas ?int)
- x-bind:attr="alpine_expr" dans les composants Flux/Blade (pas :attr qui est évalué comme PHP)
- RefreshDatabase activé globalement dans tests/Pest.php (->use(RefreshDatabase::class)->in('Feature'))
- ProfileValidationRules::profileRules() et emailRules() acceptent ?string $userId (UUID)

## Schéma DB (colonnes clés)
rooms.status : enum waiting/playing/finished
game_players.status : enum active/disconnected
game_players.user_id : nullable (joueurs anonymes)
game_players.guest_name : nullable
rounds.status : enum waiting/playing/revealed/finished
answers.response_time_ms : nullable int (départage égalité)

## Broadcasting (Reverb)
- room.{code} — Presence channel
- game.{room_id} — Broadcast channel
- player.{player_id} — Private channel

## Ce projet
"Cerveau" = ce projet Claude (architecture, décisions)
"Mains" = Claude Code CLI (écriture code, commandes)
Chaque prompt Claude Code = une tâche précise, pas de rappel de contexte.