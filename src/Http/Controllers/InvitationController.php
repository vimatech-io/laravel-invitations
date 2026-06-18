<?php

declare(strict_types=1);

namespace Vimatech\Invitation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Vimatech\Invitation\Exceptions\InvitationAlreadyAcceptedException;
use Vimatech\Invitation\Exceptions\InvitationCancelledException;
use Vimatech\Invitation\Exceptions\InvitationDeclinedException;
use Vimatech\Invitation\Exceptions\InvitationExpiredException;
use Vimatech\Invitation\Exceptions\InvitationNotFoundException;
use Vimatech\Invitation\InvitationManager;

class InvitationController extends Controller
{
    public function preview(Request $request, string $token): View|RedirectResponse
    {
        /** @var InvitationManager $manager */
        $manager = app(InvitationManager::class);

        try {
            $invitation = $manager->findByToken($token);
        } catch (InvitationNotFoundException) {
            return redirect()->back()->withErrors(['invitation' => __('Invalid invitation token.')]);
        }

        return view('invitation::preview', [
            'token' => $token,
            'invitation' => $invitation,
            'user' => $request->user(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            if (Route::has('login')) {
                return redirect()
                    ->route('login', ['invitation_token' => $token])
                    ->with('message', 'Please log in to accept the invitation.');
            }

            return redirect()->back()
                ->withErrors(['invitation' => 'Please log in to accept the invitation.']);
        }

        try {
            /** @var InvitationManager $manager */
            $manager = app(InvitationManager::class);
            /** @var Model $user */
            $manager->accept($token, $user);

            return redirect()->back()->with('status', 'Invitation accepted successfully.');
        } catch (InvitationNotFoundException) {
            return redirect()->back()->withErrors(['invitation' => 'Invalid invitation token.']);
        } catch (InvitationExpiredException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has expired.']);
        } catch (InvitationAlreadyAcceptedException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        } catch (InvitationCancelledException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has been cancelled.']);
        }
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        try {
            /** @var InvitationManager $manager */
            $manager = app(InvitationManager::class);
            $manager->decline($token);

            return redirect()->back()->with('status', 'Invitation declined.');
        } catch (InvitationNotFoundException) {
            return redirect()->back()->withErrors(['invitation' => 'Invalid invitation token.']);
        } catch (InvitationExpiredException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has expired.']);
        } catch (InvitationAlreadyAcceptedException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        } catch (InvitationCancelledException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has been cancelled.']);
        } catch (InvitationDeclinedException) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has already been declined.']);
        }
    }
}
