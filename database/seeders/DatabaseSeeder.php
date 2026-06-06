<?php

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::create([
            'name' => 'Test Host',
            'email' => 'test@test.com',
            'password' => Hash::make('password'),
        ]);

        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => 'Hits 2024',
            'is_public' => true,
        ]);

        $tracks = [
            ['deezer_track_id' => 3135556,  'title' => 'Lose Yourself',          'artist' => 'Eminem',          'album' => '8 Mile'],
            ['deezer_track_id' => 916424,   'title' => 'Smells Like Teen Spirit', 'artist' => 'Nirvana',         'album' => 'Nevermind'],
            ['deezer_track_id' => 2609762,  'title' => 'Blinding Lights',         'artist' => 'The Weeknd',      'album' => 'After Hours'],
            ['deezer_track_id' => 1109731,  'title' => 'Shape of You',            'artist' => 'Ed Sheeran',      'album' => '÷ (Divide)'],
            ['deezer_track_id' => 1473209,  'title' => 'Rolling in the Deep',     'artist' => 'Adele',           'album' => '21'],
        ];

        foreach ($tracks as $position => $track) {
            PlaylistTrack::create([
                'playlist_id' => $playlist->id,
                'deezer_track_id' => $track['deezer_track_id'],
                'title' => $track['title'],
                'artist' => $track['artist'],
                'album' => $track['album'],
                'preview_url' => "https://cdns-preview-d.dzcdn.net/stream/c-{$track['deezer_track_id']}.mp3",
                'position' => $position,
            ]);
        }
    }
}
