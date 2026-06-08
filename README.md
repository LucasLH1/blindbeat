# Blindtest

Application de blind test multijoueur en temps réel, construite avec Laravel, Livewire et Laravel Reverb.

## Prérequis

- PHP 8.5+
- Node.js (LTS)
- Composer

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Démarrage en développement

Lancer les 4 processus suivants en parallèle (4 terminaux) :

```bash
# 1. Serveur HTTP
php artisan serve

# 2. Worker de queue — OBLIGATOIRE
php artisan queue:work

# 3. Serveur WebSocket (Reverb)
php artisan reverb:start

# 4. Assets front-end (Vite)
npm run dev
```

> **Important :** `queue:work` est indispensable. Sans lui, les jobs `ProcessRoundEnd` ne s'exécutent pas et les transitions entre manches sont bloquées — la partie ne progresse plus après la première manche.
>
> `RecordGroupScoreAction` (enregistrement des scores de groupe normalisés) est appelée **à l'intérieur** de `ProcessRoundEnd`, lorsque la room passe en `finished`. Elle dépend donc elle aussi du worker : sans `queue:work`, les scores des groupes ne sont jamais mis à jour en fin de partie.
