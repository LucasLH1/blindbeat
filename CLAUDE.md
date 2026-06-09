# Frenzy (nom provisoire, configurable via APP_NAME) — contexte projet

## Stack
- Laravel 13, Livewire 3, Alpine.js, Tailwind CSS
- SQLite (dev), MariaDB (prod)
- Laravel Reverb (WebSockets)
- Pest (tests)
- Audio : previews 30s API Deezer — URLs uniquement, zéro fichier audio stocké
- preview_url stockée dans theme_tracks par SyncThemes (commande de sync) ; StartRoundAction refait aussi un appel Deezer live (fetchDeezerUrls) pour rafraîchir l'URL de preview + la cover au lancement du round, transmises dans le payload RoundStarted

## Conventions
- UUIDs sur toutes les tables (HasUuids)
- Enums PHP natifs pour les colonnes status
- Soft deletes uniquement sur users (themes / theme_tracks n'utilisent pas SoftDeletes)
- Nommage : snake_case DB, PascalCase models, kebab-case routes
- Pas de Repository pattern — Eloquent direct dans les actions/controllers
- Actions simples dans app/Actions/

## Design
- Ultra moderne, hyper clean, pastel light
- Pas de dark mode — @fluxAppearance retiré du layout BlindBeat (layouts/app.blade.php) pour forcer le thème clair
- Composants Livewire pour tout le temps réel

## Modèles & relations
- User → hasMany GamePlayer, belongsToMany Group (group_members), hasMany GroupScore
- Theme → hasMany ThemeTrack, belongsToMany Room (room_themes)
- ThemeTrack → belongsTo Theme (deezer_track_id bigint, pas de fichier)
- Room → belongsToMany Theme (room_themes), hasMany GamePlayer, hasMany Round (code 6 chars unique), belongsToMany Group (group_rooms)
- GamePlayer → belongsTo Room, belongsTo User (nullable), hasMany Answer
- Round → belongsTo Room, belongsTo ThemeTrack, hasMany Answer
- Answer → belongsTo Round, belongsTo GamePlayer

> Note : les migrations sont nommées create_playlists_table / create_playlist_tracks_table pour raisons historiques mais créent en réalité les tables themes / theme_tracks. Il n'existe aucun model Playlist/PlaylistTrack.

## Actions implémentées
- CreateRoomAction → retourne GamePlayer (avec relation room chargée via setRelation)
- JoinRoomAction → retourne GamePlayer
- StartGameAction → crée les Round (tracks shufflées depuis les thèmes, filtre top_only), passe room en playing, dispatch GameStarted, lance le premier round
- StartRoundAction → passe round en playing, stocke started_at + track_title/track_artist, fetchDeezerUrls (preview + cover live), dispatch RoundStarted, schedule ScheduleRoundEnd (delay = round_duration)
- EvaluateAnswerAction → normalisation Unicode (accents) + matching fuzzy multi-niveaux, bonus vitesse linéaire (+500 max), idempotence double-réponse, respect de max_attempts ; déclenche EndRoundAction via Round::shouldEnd() (logique centralisée sur le model Round)
- EndRoundAction → round → revealed, stocke ended_at, dispatch RoundEnded, schedule ProcessRoundEnd (5s delay) — idempotente si round déjà revealed

## Logique de fin de round (centralisée)
- Round::shouldEnd() : charge les answers une seule fois et filtre en mémoire (pas de N+1). Retourne true si tous les joueurs actifs ont trouvé titre+artiste OU épuisé max_attempts, ou s'il ne reste aucun joueur actif.
- Round::playerHasFinished($playerId, $maxAttempts, $answers) : statut d'un joueur sur une collection d'answers déjà chargée.
- Appelée par EvaluateAnswerAction (après chaque réponse) ET GameStage::playerLeft (départ d'un joueur).

## Jobs
- ScheduleRoundEnd → ferme le round à expiration du timer (EndRoundAction) si toujours en playing
- ProcessRoundEnd → passe round en finished, démarre round suivant (StartRoundAction) ou passe room en finished + RecordGroupScoreAction + dispatch GameEnded — idempotent si round déjà finished

## Events (broadcast)
- GameStarted → channel game.{room_id}
- RoundStarted → channel game.{room_id} — payload : round_number, deezer_track_id, preview_url, cover_url, duration
- PlayerAnsweredCorrectly / PlayerAnsweredWrong → channel game.{room_id}
- RoundEnded → channel game.{room_id} — payload : correct_answer (title, artist, cover_url), results + scores
- GameEnded → channel game.{room_id} — payload : leaderboard final
- RoomReplayed → channel game.{room_id} — payload : new_room_code (redirection rejouer)
- PlayerJoined → presence channel room.{code} — payload : id, display_name, score

## Composants Livewire
- Lobby — presence channel room.{code}, isHost = premier joueur joined_at ASC, refreshPlayers via echo listener
- GameStage — états waiting/playing/revealed/finished, timer Alpine.js countdown, submit réponse via fetch POST /api/answers (X-CSRF-TOKEN header), listeners RoundStarted/RoundEnded/GameEnded/RoomReplayed
- GroupNotifications — monté dans layouts/app.blade.php sous @auth, toasts de lancement de partie de groupe (channel privé group.{id})

## Groupes (état actuel)
- Tables : groups, group_members (unique group_id+user_id, role owner/member, joined_at), group_scores (unique group_id+user_id, total_normalized_points, games_played, best_normalized_score, last_played_at), group_rooms (pivot group↔room)
- Enum GroupRole : owner / member
- Actions :
  - CreateGroupAction → crée group + GroupMember owner + GroupScore vide (code 6 chars unique)
  - JoinGroupAction → refuse les doublons (DomainException), crée le score, dispatch GroupMemberJoined
  - LeaveGroupAction → owner seul = suppression du groupe (cascade) ; owner avec d'autres membres = transfert d'ownership au plus ancien ; supprime member + score
  - LaunchGroupGameAction → vérifie l'appartenance, crée la room via CreateRoomAction, attache au groupe, dispatch GroupGameStarted
  - RecordGroupScoreAction → en fin de partie (ProcessRoundEnd) : normalise les scores (score / (total_rounds*1500) * 1000), cumule total_normalized_points / games_played / best_normalized_score par membre
- GroupController (middleware auth) : index, create, store, join, show (abort 403 si non-membre), launch, leave
- Vues : groups/index, groups/create, groups/show
- Events : GroupMemberJoined, GroupGameStarted → PrivateChannel group.{group_id}

## Routes notables
- POST /api/answers → AnswerController@store (session game_player_id, retourne JSON {correct, points_earned, correct_answer})
- GET /rooms/create doit être déclaré AVANT GET /rooms/{code} dans web.php (sinon "create" est capturé comme {code}) ; idem groups/create avant groups/{group}
- POST /rooms/{code}/start → auth middleware, vérifie host via user_id (pas session)
- POST /broadcasting/auth → BroadcastAuthController@authenticate (déclaration UNIQUE dans web.php, withoutMiddleware CSRF). channels: est volontairement retiré de withRouting dans bootstrap/app.php pour empêcher la route framework par défaut ; les channel callbacks (routes/channels.php) sont chargés manuellement dans AppServiceProvider::boot()

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
answers.answer_type : enum title/artist (nullable)

## Broadcasting (Reverb)
- room.{code} — Presence channel (auth guest via session game_player_id dans BroadcastAuthController::presenceRoomAuth)
- game.{room_id} — Broadcast channel
- group.{group_id} — Private channel
- player.{player_id} — Private channel

## Ce projet
"Cerveau" = ce projet Claude (architecture, décisions)
"Mains" = Claude Code CLI (écriture code, commandes)
Chaque prompt Claude Code = une tâche précise, pas de rappel de contexte.
