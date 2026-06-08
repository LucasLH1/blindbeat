<?php

namespace App\Http\Controllers;

use App\Actions\CreateGroupAction;
use App\Actions\JoinGroupAction;
use App\Actions\LaunchGroupGameAction;
use App\Actions\LeaveGroupAction;
use App\Models\Group;
use App\Models\GroupScore;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        $groups = auth()->user()->groups()->with(['members', 'scores'])->get();

        $myScores = GroupScore::where('user_id', auth()->id())
            ->get()
            ->keyBy('group_id');

        return view('groups.index', compact('groups', 'myScores'));
    }

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(Request $request, CreateGroupAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $group = $action->execute(auth()->user(), $validated['name']);

        return redirect()->route('groups.show', $group);
    }

    public function join(Request $request, JoinGroupAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $member = $action->execute(auth()->user(), $validated['code']);
        } catch (\DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('groups.show', $member->group_id);
    }

    public function show(Group $group): View
    {
        abort_unless($this->isMember($group), 403);

        $group->load(['groupMembers.user', 'scores.user']);

        $leaderboard = $group->scores()
            ->with('user')
            ->orderByDesc('total_normalized_points')
            ->get();

        $recentRooms = $group->rooms()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $themes = Theme::withCount('tracks')->get();

        return view('groups.show', compact('group', 'leaderboard', 'recentRooms', 'themes'));
    }

    public function launch(Request $request, Group $group, LaunchGroupGameAction $action): RedirectResponse
    {
        abort_unless($this->isMember($group), 403);

        $validated = $request->validate([
            'theme_ids' => ['required', 'array', 'min:1'],
            'theme_ids.*' => ['exists:themes,id'],
            'max_players' => ['integer', 'min:2', 'max:16'],
            'round_duration' => ['integer', 'min:15', 'max:60'],
            'total_rounds' => ['integer', 'min:5', 'max:20'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
            'top_only' => ['boolean'],
        ]);

        try {
            $room = $action->execute(auth()->user(), $group, $validated['theme_ids'], $validated);
        } catch (\DomainException $e) {
            return back()->withErrors(['launch' => $e->getMessage()]);
        }

        $hostPlayer = $room->gamePlayers()
            ->where('user_id', auth()->id())
            ->latest('joined_at')
            ->first();

        session(['game_player_id' => $hostPlayer->id]);

        return redirect()->route('rooms.lobby', $room->code);
    }

    public function leave(Group $group, LeaveGroupAction $action): RedirectResponse
    {
        try {
            $action->execute(auth()->user(), $group);
        } catch (\DomainException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return redirect()->route('groups.index');
    }

    private function isMember(Group $group): bool
    {
        return $group->groupMembers()
            ->where('user_id', auth()->id())
            ->exists();
    }
}
