<?php declare(strict_types=1);

use SanderMuller\RectorPreset\Internal\ComposerProject;
use SanderMuller\RectorPreset\Internal\ProjectRoot;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

it('finds the installation that contains this package', function (): void {
    $project = ComposerProject::current();

    expect($project->rootName)->toBe(ComposerProject::PACKAGE_NAME)
        ->and(realpath($project->rootPath))->toBe(realpath(dirname(__DIR__, 3)));
});

it('finds the project whichever Composer installation registered first', function (string $first, string $second): void {
    // Rector bundles its own Composer installation. Depending on the load order,
    // InstalledVersions::getRootPackage() returns rector/rector-src instead of the project.
    // Done in a child process, so this test does not change the autoloaders of the test run.
    $root = dirname(__DIR__, 3);
    $script = <<<'PHP'
        require $argv[1] . $argv[2];
        require $argv[1] . $argv[3];
        $project = SanderMuller\RectorPreset\Internal\ComposerProject::current();
        echo $project->rootName, "\n", realpath($project->rootPath);
        PHP;

    $process = new Process([(string) new PhpExecutableFinder()->find(), '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED), '-r', $script, $root, $first, $second]);
    $process->mustRun();

    [$projectRoot, $projectPath] = explode("\n", trim($process->getOutput()));

    expect($projectRoot)->toBe(ComposerProject::PACKAGE_NAME)
        ->and($projectPath)->toBe(realpath($root));
})->with([
    'project first' => ['/vendor/autoload.php', '/vendor/rector/rector/vendor/autoload.php'],
    'rector first' => ['/vendor/rector/rector/vendor/autoload.php', '/vendor/autoload.php'],
]);

it('shows why getRootPackage() is not enough: it can return Rector itself', function (): void {
    $root = dirname(__DIR__, 3);
    $script = <<<'PHP'
        require $argv[1] . '/vendor/autoload.php';
        require $argv[1] . '/vendor/rector/rector/vendor/autoload.php';
        echo Composer\InstalledVersions::getRootPackage()['name'];
        PHP;

    $process = new Process([(string) new PhpExecutableFinder()->find(), '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED), '-r', $script, $root]);
    $process->mustRun();

    expect(trim($process->getOutput()))->toBe('rector/rector-src');
});

it('reports installed dependencies but never the root package', function (): void {
    $project = new ComposerProject('acme/extension', '/project', [
        'acme/extension' => ['version' => '1.0.0.0', 'install_path' => '/project'],
        'acme/dependency' => ['version' => '2.0.0.0', 'install_path' => '/project/vendor/acme/dependency'],
        'acme/replaced' => ['replaced' => ['*']],
    ]);

    expect($project->isInstalled('acme/dependency'))->toBeTrue()
        ->and($project->isInstalled('acme/extension'))->toBeFalse()
        ->and($project->isInstalled('acme/replaced'))->toBeFalse()
        ->and($project->isInstalled('acme/missing'))->toBeFalse();
});

it('resolves the project root from the given installation', function (): void {
    $project = new ComposerProject('acme/app', dirname(__DIR__, 3) . '/tests/../', []);

    expect(ProjectRoot::resolve(project: $project))->toBe(realpath(dirname(__DIR__, 3)));
});
