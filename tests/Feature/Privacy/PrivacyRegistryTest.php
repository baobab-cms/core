<?php

use Baobab\Facades\Privacy;
use Baobab\Privacy\Contracts\PersonalDataProvider;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Exceptions\DuplicatePrivacyProviderException;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

function privacyRegistryProbe(string $key): PersonalDataProvider
{
    return new class($key) implements PersonalDataProvider
    {
        public function __construct(private readonly string $key) {}

        public function key(): string
        {
            return $this->key;
        }

        public function describe(): DataDeclaration
        {
            return new DataDeclaration('Sonde', 'nature', 'finalité', 'base', 'rétention');
        }

        public function locate(Subject $subject): bool
        {
            return false;
        }
    };
}

it('registers the seven core providers at boot (spec 16 §2.1)', function () {
    expect(array_keys(Privacy::all()))->toEqualCanonicalizing([
        'core.users',
        'core.content_authorship',
        'core.media',
        'core.mail_log',
        'core.audit_log',
        'forms.submissions',
        'core.privacy_requests',
    ]);
});

it('lets a module register its own provider through the facade', function () {
    Privacy::register(privacyRegistryProbe('privacyprobe.records'));

    expect(Privacy::has('privacyprobe.records'))->toBeTrue()
        ->and(app(PrivacyRegistry::class)->all())->toHaveKey('privacyprobe.records');
});

it('refuses two providers under the same key', function () {
    Privacy::register(privacyRegistryProbe('privacyprobe.duplicate'));

    Privacy::register(privacyRegistryProbe('privacyprobe.duplicate'));
})->throws(DuplicatePrivacyProviderException::class);

it('requires a user or an e-mail to build a subject', function () {
    new Subject;
})->throws(InvalidArgumentException::class);

it('normalizes the e-mail of a subject', function () {
    expect(Subject::forEmail('  Jane@Example.COM ')->email)->toBe('jane@example.com');
});

it('builds a subject from a user with both identifiers', function () {
    $user = User::create(['name' => 'Subject User', 'email' => 'Subject.User@example.com', 'password' => 'secret']);

    $subject = Subject::forUser($user);

    expect($subject->userId)->toBe($user->id)
        ->and($subject->email)->toBe('subject.user@example.com');
});
