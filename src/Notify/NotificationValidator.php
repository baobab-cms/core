<?php

declare(strict_types=1);

namespace Baobab\Notify;

use Baobab\Modules\ModuleManifest;
use Baobab\Notify\Exceptions\InvalidNotificationException;

/**
 * Validation sémantique des notifications déclarées au manifeste (spec 11
 * §6) — au-delà de la conformité structurelle déjà vérifiée par
 * `ManifestValidator` : si le canal `mail` est déclaré, `mail_template` doit
 * référencer un template résolvable, soit dans le même manifeste (`mails`),
 * soit parmi les templates Core (`config('baobab.mail.templates')`). Pas de
 * lookup en base : le module n'est pas encore actif à l'installation, même
 * limite que `MailTemplateValidator`. Appelée par `InstallModule`, avant
 * toute écriture en base — échoue fort, comme le reste du manifeste.
 */
final class NotificationValidator
{
    public function assertValid(ModuleManifest $manifest): void
    {
        foreach ($manifest->notifications() as $notification) {
            $this->assertMailTemplateResolvable($notification, $manifest);
        }
    }

    /**
     * @param  array{key: string, channels: list<string>, mail_template?: string}  $notification
     */
    private function assertMailTemplateResolvable(array $notification, ModuleManifest $manifest): void
    {
        if (! in_array('mail', $notification['channels'], true)) {
            return;
        }

        $mailTemplate = $notification['mail_template'];

        $declaredByModule = collect($manifest->mails())->contains(fn (array $mail) => $mail['key'] === $mailTemplate);

        if ($declaredByModule) {
            return;
        }

        /** @var list<array{key: string}> $coreTemplates */
        $coreTemplates = config('baobab.mail.templates', []);
        $declaredByCore = collect($coreTemplates)->contains(fn (array $mail) => $mail['key'] === $mailTemplate);

        if (! $declaredByCore) {
            throw InvalidNotificationException::unknownMailTemplate($notification['key'], $mailTemplate);
        }
    }
}
