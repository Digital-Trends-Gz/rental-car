<?php

namespace App\Http\Controllers\Client;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Rules\DigitsOnly;
use App\Rules\LettersOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user && $user->role === UserRole::CLIENT, 403);

        return Inertia::render('Client/Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'civil_number' => $user->civil_number,
                'phone' => $user->phone,
                'whatsapp' => $user->whatsapp,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->role === UserRole::CLIENT, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new LettersOnly()],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $user->tenant_id))
                    ->ignore($user->id),
            ],
            'civil_number' => [
                'required',
                'string',
                'max:255',
                new DigitsOnly(),
                Rule::unique('users', 'civil_number')
                    ->where(fn ($query) => $query->where('tenant_id', $user->tenant_id))
                    ->ignore($user->id),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'civil_number' => trim((string) $validated['civil_number']),
            'phone' => trim((string) $validated['phone']),
            'whatsapp' => $this->nullableString($validated['whatsapp'] ?? null),
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Auth::setUser($user->fresh());

        return back()->with('success', 'Profile updated successfully.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
