<?php

use Baobab\Branding\Support\ContrastSafeColor;

it('leaves a fill unchanged when it already meets the target contrast', function () {
    expect(ContrastSafeColor::ensureContrast('#9E4415', '#FFFFFF'))->toBe('#9e4415');
});

it('darkens a fill that fails AA contrast against a light foreground', function () {
    // #C2571B sur blanc donne 4,49:1 (spec 18 §5.5) — sous le seuil de 4,5:1.
    $safe = ContrastSafeColor::ensureContrast('#C2571B', '#FFFFFF');

    expect($safe)->not->toBe('#c2571b');

    [$r, $g, $b] = sscanf($safe, '#%02x%02x%02x');
    $luminance = fn (int $c) => ($c / 255 <= 0.03928) ? ($c / 255) / 12.92 : ((($c / 255) + 0.055) / 1.055) ** 2.4;
    $rel = 0.2126 * $luminance($r) + 0.7152 * $luminance($g) + 0.0722 * $luminance($b);
    $ratio = (1.0 + 0.05) / ($rel + 0.05);

    expect($ratio)->toBeGreaterThanOrEqual(4.5);
});

it('lightens a fill toward white when the foreground is dark', function () {
    $safe = ContrastSafeColor::ensureContrast('#FDE8DC', '#2E2B24');

    [$r, $g, $b] = sscanf($safe, '#%02x%02x%02x');
    // Éclaircir vers le blanc augmente chaque canal, jamais ne le diminue.
    expect($r)->toBeGreaterThanOrEqual(0xFD)
        ->and($g)->toBeGreaterThanOrEqual(0xE8)
        ->and($b)->toBeGreaterThanOrEqual(0xDC);
});

it('expands 3-digit hex shorthand', function () {
    expect(ContrastSafeColor::ensureContrast('#fff', '#fff'))->not->toBeEmpty();
});
