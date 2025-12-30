<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Base class for Patch Command tests.
 */
abstract class PatchCommandTestBase extends TestCase {

  /**
   * Path to composer.json.
   *
   * @var string
   */
  protected $composerJsonPath;

  /**
   * Path to the temporary directory.
   *
   * @var string
   */
  protected $tempDir;

  /**
   * The Composer instance.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    $this->tempDir = sys_get_temp_dir() . '/composer-test-' . uniqid();
    mkdir($this->tempDir);
    $this->composerJsonPath = $this->tempDir . '/composer.json';
    if (file_exists('tests/Fixtures/composer.json')) {
      copy('tests/Fixtures/composer.json', $this->composerJsonPath);
    }
    chdir($this->tempDir);
  }

  /**
   * {@inheritDoc}
   */
  protected function tearDown(): void {
    if (is_dir($this->tempDir)) {
      $this->recursiveRmdir($this->tempDir);
    }
  }

  /**
   * Recursively deletes a directory.
   *
   * @param string $dir
   *   The directory to delete.
   */
  protected function recursiveRmdir(string $dir): void {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $path = "$dir/$file";
      is_dir($path) ? $this->recursiveRmdir($path) : unlink($path);
    }
    rmdir($dir);
  }

  /**
   * Helper to create a dummy composer.lock file.
   */
  protected function createLockFile(array $packages = []) {
    $packagesData = [];
    foreach ($packages as $name => $version) {
      if (is_int($name)) {
        $name = $version;
        $version = '1.0.0';
      }
      $packagesData[] = [
        'name' => $name,
        'version' => $version,
        'source' => [
          'type' => 'git',
          'url' => 'https://example.com/' . $name . '.git',
          'reference' => 'dummy',
        ],
        'dist' => [
          'type' => 'zip',
          'url' => 'https://example.com/' . $name . '.zip',
          'reference' => 'dummy',
          'shasum' => '',
        ],
        'type' => 'library',
      ];
    }

    $lockData = [
      '_readme' => ['This file is dummy.'],
      'content-hash' => 'dummy',
      'packages' => $packagesData,
      'packages-dev' => [],
      'aliases' => [],
      'minimum-stability' => 'stable',
      'stability-flags' => [],
      'prefer-stable' => false,
      'prefer-lowest' => false,
      'platform' => [],
      'platform-dev' => [],
    ];
    file_put_contents($this->tempDir . '/composer.lock', json_encode($lockData, JSON_PRETTY_PRINT));
  }

  /**
   * Helper to create a CommandTester for a given command class.
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  protected function getCommandTester(string $commandClass): CommandTester {
    $io = new BufferIO();
    $this->composer = Factory::create($io, $this->composerJsonPath, TRUE, TRUE);
    $application = new Application();
    $application->setAutoExit(FALSE);

    /** @var \szeidler\ComposerPatchesCLI\Composer\PatchBaseCommand $command */
    $command = new $commandClass();
    $command->setComposer($this->composer);
    $command->setIO($io);
    $command->setApplication($application);

    return new CommandTester($command);
  }
}
