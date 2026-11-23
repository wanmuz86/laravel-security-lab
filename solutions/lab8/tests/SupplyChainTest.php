<?php

// Lab 8 reference tests: cheap, offline guards that stop the remediation from regressing.
// (The live advisory check is `composer audit`; these tests only read local files.)

function lockedPackages(): array
{
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);

    $versions = [];
    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        $versions[$package['name']] = ltrim($package['version'], 'v');
    }

    return $versions;
}

test('composer.lock exists, parses and lists the framework', function () {
    expect(lockedPackages())->toHaveKey('laravel/framework');
});

test('guzzle is locked at a release that fixes the published advisories', function () {
    // Advisories in docs/lab8-audit-baseline.json affect guzzle < 7.15.2.
    expect(version_compare(lockedPackages()['guzzlehttp/guzzle'], '7.15.2', '>='))->toBeTrue();
});

test('the unused vulnerable pdf library is no longer installed', function () {
    // dompdf had advisories up to 3.1.6 and the application never used it, so it was removed.
    expect(lockedPackages())->not->toHaveKey('dompdf/dompdf');
});

test('config/services.php contains no hard-coded credentials', function () {
    $source = file_get_contents(config_path('services.php'));

    expect(preg_match("/'(?:key|secret|api_key|token)'\s*=>\s*'[^']{8,}'/i", $source))->toBe(0);
});

test('the sbom, when generated, is valid CycloneDX', function () {
    $sbom = json_decode(file_get_contents(base_path('reports/sbom.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($sbom['bomFormat'])->toBe('CycloneDX')
        ->and($sbom['components'])->not->toBeEmpty();
})->skip(fn () => ! file_exists(base_path('reports/sbom.json')), 'reports/sbom.json has not been generated yet');

test('dependabot watches composer dependencies', function () {
    expect(file_get_contents(base_path('.github/dependabot.yml')))
        ->toContain('package-ecosystem: "composer"');
})->skip(fn () => ! file_exists(base_path('.github/dependabot.yml')), 'dependabot.yml has not been created yet');

test('the security workflow uses read-only token permissions', function () {
    expect(file_get_contents(base_path('.github/workflows/dependency-security.yml')))
        ->toContain('contents: read');
})->skip(fn () => ! file_exists(base_path('.github/workflows/dependency-security.yml')), 'the workflow has not been created yet');
