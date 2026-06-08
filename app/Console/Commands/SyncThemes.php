<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeTrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncThemes extends Command
{
    protected $signature = 'themes:sync';
    protected $description = 'Synchronise les thèmes et leurs tracks depuis l\'API Deezer';

    private const MIN_RANK = 100_000;
    private const TOP_RANK = 500_000;
    private const MIN_DURATION = 25;

    // For artist queries where Deezer's stored name differs from the search string.
    private const ARTIST_FILTER_OVERRIDES = [
        'Bigflo & Oli'    => 'bigflo',
        "Sexion d'Assaut" => 'sexion',
        'Suprême NTM'     => 'ntm',
        "Heuss l'Enfoiré" => 'heuss',
        'IAM'             => 'iam',
    ];

    private const EXCLUDE_KEYWORDS = [
        'karaoke', 'karaoké', 'instrumental', 'tribute', 'made famous',
        'originally performed', 'cover version', 'backing track',
        'concerto', 'symphony', 'nocturne', 'sonata',
    ];

    // Tracks dont l'artiste contient l'un de ces termes sont des compilations/playlists génériques.
    private const ARTIST_BLACKLIST = [
        'top 40', 'top hits', 'musique', 'hits', 'karaoke', 'karaoke',
        'workout', 'fitness', 'running', 'relaxation', 'meditation',
        'sleeping', 'lullaby', 'berceuse', 'compilation', 'various artists',
        'artistes varies', 'now that', 'best of', 'the hit', 'hit parade',
        'charts', 'radio hits', 'club hits', 'party hits', 'dance hits',
        'pop hits compilation',
    ];

    private const RAP_FR_ARTISTS = [
        'Booba', 'Kaaris', 'Jul', 'Ninho', 'SCH', 'Nekfeu', 'PNL', 'Damso', 'Hamza', 'Niska',
        'Maes', 'Soolking', 'Leto', 'Koba LaD', 'Freeze Corleone', 'Lacrim', 'Sofiane', 'Disiz',
        'Oxmo Puccino', 'Akhenaton', 'Kool Shen', 'Joey Starr', 'Lartiste', 'Gradur', 'Seth Gueko',
        "Rim'K", 'Kery James', 'Médine', 'Vald', 'Orelsan', 'Bigflo & Oli', 'Lomepal', 'Lefa',
        'Alpha Wann', 'La Fouine', "Sexion d'Assaut", 'IAM', 'MC Solaar', 'Suprême NTM', '113',
        'Rohff', 'Sinik', 'Youssoupha', 'Gazo', 'MHD', 'Kalash Criminel', 'Naps', 'PLK',
        "Heuss l'Enfoiré", 'Alonzo', 'Bosh', 'Franglish', 'Sadek', 'Bramsito', 'Zola', 'Dinos',
        'Rocé', 'Doums', 'Doomams', 'Jazzy Bazz', 'Landy', 'Gringe', 'Niro', 'Lorenzo',
        'Alkpote', 'Gims', 'Dadju', 'Awa Imani', 'Maska', 'Demi Portion', 'Kanté', 'Freeze Da',
        'Ali', 'Demon One', 'Keny Arkana', 'Nessbeal', 'Sifax', 'Remy', 'Isha', "Shurik'n",
    ];

    private const VARIETE_FR_ARTISTS = [
        'Stromae', 'Zaz', 'Indochine', 'Jean-Jacques Goldman', 'Renaud', 'Francis Cabrel',
        'Patrick Bruel', 'Céline Dion', 'Johnny Hallyday', 'Florent Pagny', 'Mylène Farmer',
        'Vanessa Paradis', 'Edith Piaf', 'Serge Gainsbourg', 'Michel Sardou', 'Charles Aznavour',
        'Georges Brassens', 'Jacques Brel', 'Alain Bashung', 'Julien Clerc', 'Patrick Fiori',
        'Christophe Maé', 'Grégoire', 'Alizée', 'Lara Fabian', 'Bénabar', 'Zazie',
        'Amel Bent', "Shy'm", 'Jenifer', 'Tal', 'Louane', 'Vianney', 'Slimane',
        'Claudio Capéo', 'Kendji Girac', 'Amir', 'Vitaa', 'M. Pokora', "Keen'V",
        'Christophe Willem', 'Coeur de Pirate', 'Yannick Noah', 'Michel Delpech',
        'Alain Souchon', 'Laurent Voulzy', 'Véronique Sanson', 'Isabelle Boulay',
        'Pascal Obispo', 'Grand Corps Malade', 'Soprano', 'Nolwenn Leroy', 'Joyce Jonathan',
        'Hélène Ségara', 'Michel Fugain', 'Joe Dassin', 'Claude François', 'Daniel Balavoine',
        'Jean Ferrat', 'Gilbert Bécaud',
        'Gilbert Montagné', 'Nana Mouskouri', 'Julio Iglesias', 'Enrico Macias', 'Salvatore Adamo',
        'Charles Trenet', 'Yves Montand', 'Luis Mariano', 'Roch Voisine', 'Garou',
        'Dave', 'Stone et Charden', 'Michel Jonasz', 'Bernard Lavilliers', 'Maxime Le Forestier',
        'Nicoletta', 'Sheila', 'Claude Nougaro', 'Barbara', 'Léo Ferré',
    ];

    private const THEMES = [
        [
            'name' => 'Pop International', 'emoji' => '🌍', 'deezer_genre_id' => 132,
            'strategy' => 'chart_and_search',
            'queries' => ['pop hits top', 'pop songs popular'],
        ],
        [
            'name' => 'Rap FR', 'emoji' => '🎤', 'deezer_genre_id' => 116,
            'strategy' => 'artists',
            'queries' => [],
        ],
        [
            'name' => 'Rock', 'emoji' => '🎸', 'deezer_genre_id' => 152,
            'strategy' => 'chart_and_search',
            'queries' => ['rock classic hits', 'rock alternative top'],
        ],
        [
            'name' => 'Années 80', 'emoji' => '🕹️', 'deezer_genre_id' => null,
            'strategy' => 'search_only',
            'queries' => ['80s hits', 'best of 80s', 'eighties pop', 'années 80 tube'],
        ],
        [
            'name' => 'Années 90', 'emoji' => '📼', 'deezer_genre_id' => null,
            'strategy' => 'search_only',
            'queries' => ['90s hits', 'best of 90s', 'eurodance 90s', 'années 90 tube'],
        ],
        [
            'name' => 'Années 2000', 'emoji' => '💿', 'deezer_genre_id' => null,
            'strategy' => 'search_only',
            'queries' => ['2000s hits', 'pop 2000s top', 'rnb 2000s', 'années 2000 tube'],
        ],
        [
            'name' => 'R&B / Soul', 'emoji' => '🎵', 'deezer_genre_id' => 165,
            'strategy' => 'chart_and_search',
            'queries' => ['rnb soul hits', 'soul classic top'],
        ],
        [
            'name' => 'Electro', 'emoji' => '🎧', 'deezer_genre_id' => 106,
            'strategy' => 'chart_and_search',
            'queries' => ['electro dance hits', 'edm top tracks'],
        ],
        [
            'name' => 'Variété FR', 'emoji' => '🇫🇷', 'deezer_genre_id' => 195,
            'strategy' => 'artists',
            'queries' => [],
        ],
        [
            'name' => 'Latin', 'emoji' => '💃', 'deezer_genre_id' => 197,
            'strategy' => 'chart_and_search',
            'queries' => ['latin hits top', 'reggaeton popular'],
        ],
    ];

    public function handle(): void
    {
        // Clean slate — disable FK checks for MySQL/MariaDB compatibility
        try { DB::statement('SET FOREIGN_KEY_CHECKS=0'); } catch (\Throwable) {}
        DB::table('theme_tracks')->truncate();
        try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable) {}

        $totalTracks = 0;
        $totalTop = 0;

        foreach (self::THEMES as $config) {
            $theme = Theme::updateOrCreate(
                ['name' => $config['name']],
                ['emoji' => $config['emoji'], 'deezer_genre_id' => $config['deezer_genre_id']]
            );

            $raw = match ($config['strategy']) {
                'chart_and_search' => $this->fetchChartAndSearch($config),
                'search_only'      => $this->fetchSearchOnly($config),
                'artists'          => $this->fetchByArtists($config['name']),
                default            => [],
            };

            $tracks      = $this->filterAndDeduplicate($raw);
            $beforeDedup = count($tracks);
            $tracks      = $this->deduplicateByContent($tracks);
            $afterDedup  = count($tracks);

            $isTopCount = 0;
            foreach ($tracks as $position => $t) {
                $isTop = ($t['rank'] ?? 0) >= self::TOP_RANK;
                ThemeTrack::create([
                    'theme_id'        => $theme->id,
                    'deezer_track_id' => $t['id'],
                    'title'           => $t['title'],
                    'artist'          => $t['artist']['name'] ?? '',
                    'album'           => $t['album']['title'] ?? null,
                    'preview_url'     => $t['preview'],
                    'cover_url'       => $t['album']['cover_medium'] ?? null,
                    'position'        => $position,
                    'rank'            => $t['rank'] ?? null,
                    'is_top'          => $isTop,
                ]);
                if ($isTop) $isTopCount++;
            }

            $theme->update(['tracks_count' => $afterDedup]);
            $totalTracks += $afterDedup;
            $totalTop += $isTopCount;

            $deduped = $beforeDedup - $afterDedup;
            $dedupNote = $deduped > 0 ? " (-{$deduped} doublons)" : '';
            $this->line("  {$theme->emoji} {$theme->name} — {$beforeDedup} → {$afterDedup} tracks{$dedupNote} ({$isTopCount} is_top)");
        }

        $this->info(count(self::THEMES) . " thèmes synchronisés, {$totalTracks} tracks ({$totalTop} is_top).");
    }

    private function fetchChartAndSearch(array $config): array
    {
        $raw = [];

        if ($config['deezer_genre_id']) {
            $data = $this->fetch("https://api.deezer.com/chart/{$config['deezer_genre_id']}/tracks");
            $raw = array_merge($raw, $data['data'] ?? []);
        }

        foreach ($config['queries'] as $query) {
            $data = $this->fetch('https://api.deezer.com/search?q=' . urlencode($query) . '&order=RANKING&limit=100');
            $raw = array_merge($raw, $data['data'] ?? []);
        }

        return $raw;
    }

    private function fetchSearchOnly(array $config): array
    {
        $raw = [];

        foreach ($config['queries'] as $query) {
            $data = $this->fetch('https://api.deezer.com/search?q=' . urlencode($query) . '&order=RANKING&limit=100');
            $raw = array_merge($raw, $data['data'] ?? []);
        }

        return $raw;
    }

    private function fetchByArtists(string $themeName): array
    {
        $artists = match ($themeName) {
            'Rap FR'      => self::RAP_FR_ARTISTS,
            'Variété FR'  => self::VARIETE_FR_ARTISTS,
            default       => [],
        };

        $all = [];

        foreach ($artists as $artist) {
            $data   = $this->fetch('https://api.deezer.com/search?q=' . urlencode($artist) . '&order=RANKING&limit=50');
            $tracks = $data['data'] ?? [];

            // Strict filter: only keep tracks actually from this artist
            $tracks = array_filter($tracks, fn ($t) => $this->isFromArtist($t, $artist));

            // Keep 10 best by rank per artist
            usort($tracks, fn ($a, $b) => ($b['rank'] ?? 0) <=> ($a['rank'] ?? 0));
            $all[] = array_slice($tracks, 0, 10);
        }

        return $all ? array_merge(...$all) : [];
    }

    private function isFromArtist(array $track, string $queryArtist): bool
    {
        $trackArtist = $this->normalizeForMatch($track['artist']['name'] ?? '');
        $trackTitle  = $this->normalizeForMatch($track['title'] ?? '');
        $keyword     = $this->getArtistKeyword($queryArtist);

        if (str_contains($trackArtist, $keyword)) {
            return true;
        }

        // Accept featuring appearances in the title (e.g. "Song (feat. Jul)")
        foreach (['feat. ' . $keyword, 'ft. ' . $keyword, 'feat ' . $keyword] as $feat) {
            if (str_contains($trackTitle, $feat)) {
                return true;
            }
        }

        return false;
    }

    private function getArtistKeyword(string $artist): string
    {
        if (isset(self::ARTIST_FILTER_OVERRIDES[$artist])) {
            return self::ARTIST_FILTER_OVERRIDES[$artist];
        }

        return $this->normalizeForMatch($artist);
    }

    private function normalizeForMatch(string $str): string
    {
        $str = Str::ascii(mb_strtolower($str));
        // Apostrophes and dots to spaces for consistent matching (Rim'K ↔ Rim K, M. Pokora ↔ M Pokora)
        $str = str_replace(["'", "\u{2019}", '.'], ' ', $str);
        return trim((string) preg_replace('/\s+/', ' ', $str));
    }

    private function filterAndDeduplicate(array $raw): array
    {
        $seen = [];
        $unique = [];
        foreach ($raw as $track) {
            $id = $track['id'] ?? null;
            if ($id && ! isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $track;
            }
        }

        return array_values(array_filter($unique, function ($t) {
            if (empty($t['preview'])) return false;
            if (($t['rank'] ?? 0) < self::MIN_RANK) return false;
            if (($t['duration'] ?? 0) <= self::MIN_DURATION) return false;

            $title      = mb_strtolower($t['title'] ?? '');
            $artist     = mb_strtolower($t['artist']['name'] ?? '');
            $artistNorm = $this->normalizeForMatch($t['artist']['name'] ?? '');

            foreach (self::EXCLUDE_KEYWORDS as $kw) {
                if (str_contains($title, $kw) || str_contains($artist, $kw)) {
                    return false;
                }
            }

            foreach (self::ARTIST_BLACKLIST as $term) {
                if (str_contains($artistNorm, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function deduplicateByContent(array $tracks): array
    {
        $best = [];
        foreach ($tracks as $t) {
            $key = $this->normalizeTitle($t['title'] ?? '') . '||' . $this->normalizeForMatch($t['artist']['name'] ?? '');
            if (! isset($best[$key]) || ($t['rank'] ?? 0) > ($best[$key]['rank'] ?? 0)) {
                $best[$key] = $t;
            }
        }
        return array_values($best);
    }

    private function normalizeTitle(string $str): string
    {
        // Remove parenthetical/bracketed suffixes before normalizing: "(Stay High)", "[Remix]", etc.
        $str = (string) preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $str);
        return $this->normalizeForMatch($str);
    }

    private function fetch(string $url): array
    {
        usleep(200_000); // 200ms — respect Deezer quota 50 req/5s
        try {
            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable) {}

        return [];
    }
}
