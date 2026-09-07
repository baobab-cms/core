<?php

declare(strict_types=1);

namespace Baobab\Admin\Mail\Http\Controllers;

use Baobab\Mail\Actions\SendTestMail;
use Baobab\Mail\Actions\UpdateMailSettings;
use Baobab\Mail\Models\MailSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran de réglages du transport e-mail (spec 13 §2.1, suivi n° 187).
 * Accès gouverné par `baobab.system.mail.configure` au niveau de la route
 * (routes/admin.php), patron exact `ApiSettingsController`.
 *
 * SMTP seul pour cette passe (arbitrage n° 187) : `mailer` reste une colonne
 * ouverte à d'autres valeurs demain (SES, Postmark, Resend, Mailgun), mais
 * ni la validation ni cet écran n'en proposent d'autre aujourd'hui.
 */
final class MailSettingsController
{
    public function index(): View
    {
        $setting = MailSetting::current();

        return view('baobab::admin.mails.settings', [
            'setting' => $setting,
            'hasPassword' => ($setting->credentials['password'] ?? '') !== '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'scheme' => ['nullable', 'string', 'in:,smtps'],
        ]);

        app(UpdateMailSettings::class)([
            'mailer' => 'smtp',
            'from_address' => $validated['from_address'] ?? null,
            'from_name' => $validated['from_name'] ?? null,
            'credentials' => [
                'host' => $validated['host'],
                'port' => $validated['port'],
                'username' => $validated['username'] ?? null,
                'password' => $validated['password'] ?? '',
                'scheme' => $validated['scheme'] ?? '',
            ],
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.mail_settings.updated')]);

        return redirect()->route('admin.mails.settings');
    }

    public function test(Request $request, SendTestMail $send): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email']]);

        $send($data['recipient']);

        return redirect()
            ->route('admin.mails.settings')
            ->with('status', __('baobab::admin.mails.test_sent', ['recipient' => $data['recipient']]));
    }
}
