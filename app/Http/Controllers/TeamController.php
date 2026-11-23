<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * REFERENCE SOLUTION (instructor): bonus finding - admins may only manage their own organization.
 */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $members = User::where('organization_id', $request->user()->organization_id)->orderBy('name')->get();

        return view('team.index', compact('members'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $data = $request->validate(['role' => ['required', 'in:admin,member']]);

        $user->update(['role' => $data['role']]);

        return back()->with('status', "Role updated for {$user->name}.");
    }
}
