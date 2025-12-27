<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use Composer\Console\Application;
use Composer\Factory;
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
   * {@inheritDoc}
   */
  protected function setUp(): void {
    $this->tempDir = sys_get_temp_dir() . '/composer-test-' . uniqid();
    mkdir($this->tempDir);
    $this->composerJsonPath = $this->tempDir . '/composer.json';
    copy('tests/Fixtures/composer.json', $this->composerJsonPath);
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
   * Helper to create a CommandTester for a given command class.
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  protected function getCommandTester(string $commandClass): CommandTester {
    $io = new NullIO();
    $composer = Factory::create($io, $this->composerJsonPath, TRUE, TRUE);
    $application = new Application();
    $application->setAutoExit(FALSE);

    /** @var \szeidler\ComposerPatchesCLI\Composer\PatchBaseCommand $command */
    $command = new $commandClass();
    $command->setComposer($composer);
    $command->setIO($io);
    $command->setApplication($application);

    return new CommandTester($command);
  }
}
