<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Tests\Unit;

use Usamamuneerchaudhary\CommandPalette\Support\SafeHtml;

test('it keeps inline badge markup', function () {
    $html = SafeHtml::label('<span class="fi-badge">Paid</span> Invoice 42');

    $this->assertStringContainsString('Paid', $html);
    $this->assertStringContainsString('Invoice 42', $html);
});

test('it strips scripts and event handlers', function () {
    $html = SafeHtml::label('<img src=x onerror="alert(1)"><script>alert(1)</script>Safe');

    $this->assertStringContainsString('Safe', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('onerror', $html);
    $this->assertStringNotContainsString('alert(1)', $html);
});
