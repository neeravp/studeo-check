<?php

use Studeo\Check\Engine\Baseline;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\Violation;

it('formats a baseline key without line numbers as rule file subject', function () {
    $violation = new Violation('step-count', 'app/Services/Order.php', 42, 'Order::place', Severity::Error, 'Too many steps.');

    expect((new Baseline)->key($violation))->toBe('step-count app/Services/Order.php Order::place');
});

it('lists unique sorted keys from violations', function () {
    $v1 = new Violation('step-count', 'b.php', 10, 'B::b', Severity::Error, 'msg');
    $v2 = new Violation('file-length', 'a.php', 1, 'file', Severity::Warning, 'msg');
    $v3 = new Violation('step-count', 'b.php', 20, 'B::b', Severity::Error, 'msg');

    expect(Baseline::fromViolations([$v1, $v2, $v3])->entries)->toBe([
        'file-length a.php file',
        'step-count b.php B::b',
    ]);
});

it('reads baseline from file and ignores blank lines and whitespace', function () {
    $path = tempnam(sys_get_temp_dir(), 'baseline_');
    file_put_contents($path, "  rule2 file2 subject2  \n\nrule1 file1 subject1\n");
    register_shutdown_function(fn () => @unlink($path));

    $baseline = Baseline::fromFile($path);

    expect($baseline->entries)->toBe([
        'rule1 file1 subject1',
        'rule2 file2 subject2',
    ]);
});

it('throws when baseline file does not exist', function () {
    expect(fn () => Baseline::fromFile('/path/does/not/exist.txt'))
        ->toThrow(RuntimeException::class, 'Baseline file [/path/does/not/exist.txt] was not found.');
});

it('computes differences between actual violations and baseline', function () {
    $baseline = new Baseline(['rule1 file1 subject1', 'rule2 file2 subject2']);
    $v1 = new Violation('rule1', 'file1', 1, 'subject1', Severity::Error, 'msg');
    $v3 = new Violation('rule3', 'file3', 1, 'subject3', Severity::Error, 'msg');

    $diff = $baseline->differences([$v1, $v3]);

    expect($diff['new'])->toBe(['rule3 file3 subject3'])
        ->and($diff['stale'])->toBe(['rule2 file2 subject2']);
});

it('applies baseline to filter active violations and report stale entries', function () {
    $baseline = new Baseline(['rule1 file1 subject1', 'rule2 file2 subject2']);
    $v1 = new Violation('rule1', 'file1', 1, 'subject1', Severity::Error, 'msg');
    $v3 = new Violation('rule3', 'file3', 1, 'subject3', Severity::Error, 'msg');

    $applied = $baseline->apply([$v1, $v3]);

    expect($applied['active'])->toBe([$v3])
        ->and($applied['stale'])->toBe(['rule2 file2 subject2']);
});

it('saves baseline entries sorted with trailing newline or empty file when empty', function () {
    $path = tempnam(sys_get_temp_dir(), 'baseline_save_');
    register_shutdown_function(fn () => @unlink($path));

    $empty = new Baseline([]);
    $empty->save($path);
    expect(file_get_contents($path))->toBe('');

    $baseline = new Baseline(['rule1 file1 subject1', 'rule2 file2 subject2']);
    $baseline->save($path);
    expect(file_get_contents($path))->toBe("rule1 file1 subject1\nrule2 file2 subject2\n");
});

it('keys() of violations with a duplicate in the middle returns array_is_list true', function () {
    $v1 = new Violation('rule1', 'file1', 1, 'subject1', Severity::Error, 'msg1');
    $v2 = new Violation('rule2', 'file2', 2, 'subject2', Severity::Error, 'msg2');
    $v3 = new Violation('rule2', 'file2', 2, 'subject2', Severity::Error, 'msg2');
    $v4 = new Violation('rule3', 'file3', 3, 'subject3', Severity::Error, 'msg3');

    $baseline = Baseline::fromViolations([$v1, $v2, $v3, $v4]);

    expect(array_is_list($baseline->entries))->toBeTrue()
        ->and($baseline->entries)->toHaveCount(3);
});

it('filter() with a baseline entry covering the MIDDLE of three violations returns array_is_list true and 2 elements', function () {
    $v1 = new Violation('rule1', 'file1', 1, 'subject1', Severity::Error, 'msg1');
    $v2 = new Violation('rule2', 'file2', 2, 'subject2', Severity::Error, 'msg2');
    $v3 = new Violation('rule3', 'file3', 3, 'subject3', Severity::Error, 'msg3');

    $baseline = new Baseline(['rule2 file2 subject2']);
    $applied = $baseline->apply([$v1, $v2, $v3]);

    expect(array_is_list($applied['active']))->toBeTrue()
        ->and($applied['active'])->toHaveCount(2);
});
