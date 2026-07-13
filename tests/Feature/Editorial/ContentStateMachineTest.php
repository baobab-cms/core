<?php

use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

function contentTypeWithWorkflow(bool $workflow): ContentType
{
    return new ContentType(['key' => 'Car', 'blueprint' => ['workflow' => $workflow]]);
}

it('allows the direct transitions from draft without workflow', function () {
    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(false);

    expect($machine->availableTransitions($type, 'draft'))
        ->toBe(['publish', 'schedule', 'archive']);
});

it('opens submit/pending/approve/reject only when workflow is enabled', function () {
    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(true);

    expect($machine->availableTransitions($type, 'draft'))->toContain('submit')
        ->and($machine->availableTransitions($type, 'pending'))->toBe(['approve', 'reject', 'publish', 'schedule', 'archive']);

    $machine->assertAllowed($type, 'draft', 'submit');
});

it('rejects submit when workflow is disabled — pending is unreachable', function () {
    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(false);

    $machine->assertAllowed($type, 'draft', 'submit');
})->throws(InvalidContentTransitionException::class);

it('allows unpublish/archive from published and scheduled, and publish/schedule to reprogram a scheduled item', function () {
    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(false);

    expect($machine->availableTransitions($type, 'published'))->toBe(['unpublish', 'archive'])
        ->and($machine->availableTransitions($type, 'scheduled'))->toBe(['unpublish', 'archive', 'schedule', 'publish'])
        ->and($machine->availableTransitions($type, 'archived'))->toBe(['restore']);
});

it('rejects a transition absent from the closed graph', function () {
    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(false);

    $machine->assertAllowed($type, 'archived', 'publish');
})->throws(InvalidContentTransitionException::class);

it('is extensible via the baobab.content.transitions filter hook', function () {
    Hook::modify('baobab.content.transitions', function (array $graph): array {
        $graph['pending'] ??= [];
        $graph['pending'][] = 'in_translation';

        return $graph;
    });

    $machine = app(ContentStateMachine::class);
    $type = contentTypeWithWorkflow(true);

    expect($machine->availableTransitions($type, 'pending'))->toContain('in_translation');
});
