<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /** Header language switch: remembered on the account when signed in, else in a cookie. */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::isSupported($locale), 404);

        $user = $request->user();
        if ($user instanceof User) {
            $user->forceFill(['preferences' => array_merge($user->preferences ?? [], ['locale' => $locale])])->save();
        }

        Cookie::queue(Cookie::forever(Locale::COOKIE, $locale));

        return redirect()->back(fallback: route('home'));
    }
}
