<?php

use Baobab\Support\LineDiff;

/**
 * @param  list<array{type: string, line: string}>|null  $diff
 * @return list<string>
 */
function diffOf(?array $diff, string $type): array
{
    return array_values(array_map(
        static fn (array $line): string => $line['line'],
        array_filter($diff ?? [], static fn (array $line): bool => $line['type'] === $type),
    ));
}

it('reports nothing changed between two identical files', function () {
    $diff = LineDiff::compare("a\nb\nc", "a\nb\nc");

    expect(diffOf($diff, 'removed'))->toBe([])
        ->and(diffOf($diff, 'added'))->toBe([])
        ->and(diffOf($diff, 'kept'))->toBe(['a', 'b', 'c']);
});

it('isolates an inserted line, keeping the surrounding ones', function () {
    $diff = LineDiff::compare("a\nc", "a\nb\nc");

    expect(diffOf($diff, 'added'))->toBe(['b'])
        ->and(diffOf($diff, 'removed'))->toBe([])
        ->and(diffOf($diff, 'kept'))->toBe(['a', 'c']);
});

it('isolates a removed line', function () {
    $diff = LineDiff::compare("a\nb\nc", "a\nc");

    expect(diffOf($diff, 'removed'))->toBe(['b'])
        ->and(diffOf($diff, 'added'))->toBe([]);
});

it('reads a modified line as one removal and one addition', function () {
    $diff = LineDiff::compare("a\nold\nc", "a\nnew\nc");

    expect(diffOf($diff, 'removed'))->toBe(['old'])
        ->and(diffOf($diff, 'added'))->toBe(['new'])
        ->and(diffOf($diff, 'kept'))->toBe(['a', 'c']);
});

it('keeps the lines in file order, not reversed', function () {
    $diff = LineDiff::compare("a\nb", "a\nb\nc\nd");

    expect(array_map(static fn (array $line): string => $line['line'], $diff ?? []))
        ->toBe(['a', 'b', 'c', 'd']);
});

it('handles the three newline conventions alike', function () {
    expect(diffOf(LineDiff::compare("a\r\nb", "a\nb"), 'removed'))->toBe([])
        ->and(diffOf(LineDiff::compare("a\rb", "a\nb"), 'added'))->toBe([]);
});

/**
 * Le coût est quadratique : au-delà du plafond on renonce et on le dit, plutôt
 * que de faire ramer une requête admin sur des millions de cellules.
 */
it('gives up rather than compare a file beyond the line ceiling', function () {
    $big = implode("\n", array_fill(0, LineDiff::MAX_LINES + 1, 'x'));

    expect(LineDiff::compare($big, 'x'))->toBeNull()
        ->and(LineDiff::compare('x', $big))->toBeNull();
});
