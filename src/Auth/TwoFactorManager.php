<?php

declare(strict_types=1);

namespace Baobab\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Baobab\Users\Models\User;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorManager
{
    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => strtoupper(bin2hex(random_bytes(10))),
            range(1, 8),
        );
    }

    public function getQrCodeSvg(User $user): string
    {
        $url = $this->engine->getQRCodeUrl(
            config('app.name', 'Baobab CMS'),
            $user->email,
            (string) $user->two_factor_secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }

    public function verify(User $user, string $code): bool
    {
        return (bool) $this->engine->verifyKey(
            (string) $user->two_factor_secret,
            $code,
        );
    }
}
