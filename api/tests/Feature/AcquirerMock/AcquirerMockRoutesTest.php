<?php

declare(strict_types=1);

it('does not expose the acquirer mock routes in the payments API', function () {
    $this->postJson('/acquirer/authorize', [])->assertNotFound();
});
