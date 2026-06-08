<?php

namespace App\Http\Controllers;

use App\Models\GroupScore;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        if (! auth()->check()) {
            return view('home');
        }

        $user = auth()->user();

        $groups = $user->groups()->with('members')->get();

        $myScores = GroupScore::where('user_id', $user->id)->get();

        $stats = [
            'groups_count' => $groups->count(),
            'games_played' => (int) $myScores->sum('games_played'),
            'total_points' => round($myScores->sum('total_normalized_points'), 1),
            'best_score'   => round((float) $myScores->max('best_normalized_score'), 1),
        ];

        return view('home.auth', compact('groups', 'stats'));
    }
}
