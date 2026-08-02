<?php

declare(strict_types=1);

use App\Site\PostBody;

test('the body splits into paragraphs on blank lines', function (?string $body, array $paragraphs): void {
    // The view renders one escaped <p> per entry rather than nl2br over the whole
    // column, which keeps {!! !!} off a page built from tenant-authored text.
    expect(new PostBody($body)->paragraphs())->toBe($paragraphs);
})->with([
    'nothing written' => [null, []],
    'one paragraph' => ['Just the one.', ['Just the one.']],
    'two paragraphs' => ["First.\n\nSecond.", ['First.', 'Second.']],
    'a single newline is not a break' => ["First\nstill first.", ["First\nstill first."]],
    'runs of blank lines collapse' => ["First.\n\n\n\nSecond.", ['First.', 'Second.']],
    'trailing whitespace is trimmed away' => ["  First.  \n\n  \n\nSecond.\n\n", ['First.', 'Second.']],
]);
