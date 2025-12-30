<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use Composer\Factory;
use Composer\IO\BufferIO;
use cweagans\Composer\Plugin\Patches;
use szeidler\ComposerPatchesCLI\Composer\PatchMigrateCommand;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateConfigurationExistsException;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateNoConfigurationFoundException;

/**
 * Tests the PatchMigrateCommand class.
 */
class PatchMigrateCommandTest extends PatchCommandTestBase {

  /**
   * Helper to get a CommandTester with real CP2 commands.
   */
  protected function getMigrateCommandTester() {
    $io = new BufferIO();
    $this->composer = Factory::create($io, $this->composerJsonPath);
    $application = new \Composer\Console\Application();
    $application->setAutoExit(FALSE);
    
    // Register real CP2 commands and plugin if using Composer Patches 2.
    if (class_exists('cweagans\Composer\Command\RelockCommand')) {
      $application->add(new \cweagans\Composer\Command\RelockCommand());
      $application->add(new \cweagans\Composer\Command\RepatchCommand());

      // Register the Patches plugin with the composer instance.
      $plugin = new Patches();
      $this->composer->getPluginManager()->addPlugin($plugin);
    }

    $command = new class extends PatchMigrateCommand {
      protected function runRepatch(): void {
        // Mock repatch to avoid full Installer run which requires real packages.
        $this->getIO()->write('<info>Repatching dependencies...</info>');
      }
    };
    $command->setComposer($this->composer);
    $command->setIO($io);
    $command->setApplication($application);

    return new \Symfony\Component\Console\Tester\CommandTester($command);
  }

  /**
   * Helper to create a dummy patch file.
   */
  protected function createDummyPatch(string $filename) {
    file_put_contents($this->tempDir . '/' . $filename, "--- a/file\n+++ b/file\n@@ -1,1 +1,1 @@\n-old\n+new\n");
  }

  /**
   * Tests migrating patches from the root composer.json.
   */
  public function testMigrateRoot() {
    $this->createDummyPatch('test.patch');
    copy(__DIR__ . '/Fixtures/composer-migrate-root.json', $this->composerJsonPath);
    copy(__DIR__ . '/Fixtures/composer-migrate-root.lock', $this->tempDir . '/composer.lock');

    $commandTester = $this->getMigrateCommandTester();
    $commandTester->execute([]);

    $this->assertStringContainsString('Migrating patches from root composer.json...', $commandTester->getDisplay());
    $this->assertStringContainsString('Migration completed successfully.', $commandTester->getDisplay());
    $this->assertStringContainsString('Relocking patches...', $commandTester->getDisplay());
    $this->assertStringContainsString('Repatching dependencies...', $commandTester->getDisplay());

    $updated_composer_json = json_decode(file_get_contents($this->composerJsonPath), TRUE);

    $this->assertArrayHasKey('composer-patches', $updated_composer_json['extra']);
    $expected_patches = [
      'vendor/package' => [
        'test patch' => 'test.patch',
      ],
    ];
    $this->assertEquals($expected_patches, $updated_composer_json['extra']['composer-patches']['patches']);
    $this->assertArrayNotHasKey('patches', $updated_composer_json['extra']);

    $this->assertArrayNotHasKey('patchLevel', $updated_composer_json['extra']);
    $this->assertEquals(['drupal/core' => 2], $updated_composer_json['extra']['composer-patches']['package-depths']);

    $this->assertArrayNotHasKey('enable-patching', $updated_composer_json['extra']);
    $this->assertArrayNotHasKey('composer-exit-on-patch-failure', $updated_composer_json['extra']);
  }

  /**
   * Tests migrating patches from an external patches file.
   */
  public function testMigrateFile() {
    $this->createDummyPatch('test.patch');
    $patches_file = 'patches.json';
    $composer_json = [
      'name' => 'test/project',
      'require' => [
        'cweagans/composer-patches' => '^2.0',
      ],
      'extra' => [
        'patches-file' => $patches_file,
        'patches-ignore' => [
          'dependency/package' => [
            'vendor/package' => [
                'ignored patch' => 'https://example.com/ignored.patch'
            ]
          ],
        ],
        'patchLevel' => [
          'vendor/package' => '-p2',
        ],
        'composer-patches-skip-reporting' => true,
        'composer-exit-on-patch-failure' => true,
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($composer_json, JSON_PRETTY_PRINT));

    $patches_json = [
      'patches' => [
        'vendor/package' => [
          'description' => 'test.patch',
        ],
      ],
    ];
    file_put_contents($this->tempDir . '/' . $patches_file, json_encode($patches_json, JSON_PRETTY_PRINT));
    $this->createLockFile(['vendor/package' => '1.0.0']);

    $commandTester = $this->getMigrateCommandTester();
    $commandTester->execute([]);

    $this->assertStringContainsString("Migrating patches from $patches_file...", $commandTester->getDisplay());
    $this->assertStringContainsString('Migration completed successfully.', $commandTester->getDisplay());
    $this->assertStringContainsString('Relocking patches...', $commandTester->getDisplay());
    $this->assertStringContainsString('Repatching dependencies...', $commandTester->getDisplay());

    $updated_composer_json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayNotHasKey('patches-file', $updated_composer_json['extra']);
    $this->assertEquals($patches_file, $updated_composer_json['extra']['composer-patches']['patches-file']);

    $this->assertArrayNotHasKey('patches-ignore', $updated_composer_json['extra']);
    $this->assertEquals(['dependency/package'], $updated_composer_json['extra']['composer-patches']['ignore-dependency-patches']);

    $this->assertArrayNotHasKey('patchLevel', $updated_composer_json['extra']);
    $this->assertEquals(['vendor/package' => 2], $updated_composer_json['extra']['composer-patches']['package-depths']);

    $this->assertArrayNotHasKey('composer-patches-skip-reporting', $updated_composer_json['extra']);
    $this->assertArrayNotHasKey('composer-exit-on-patch-failure', $updated_composer_json['extra']);
  }

  /**
   * Tests that an exception is thrown when no migration is needed.
   */
  public function testNoMigrationNeeded() {
    $composer_json = [
      'name' => 'test/project',
      'extra' => [],
    ];
    file_put_contents($this->composerJsonPath, json_encode($composer_json, JSON_PRETTY_PRINT));

    $commandTester = $this->getCommandTester(PatchMigrateCommand::class);
    $this->expectException(PatchMigrateNoConfigurationFoundException::class);
    $commandTester->execute([]);
  }

  /**
   * Tests that an exception is thrown when CP2 configuration already exists.
   */
  public function testAlreadyHasCP2() {
    $composer_json = [
      'name' => 'test/project',
      'extra' => [
        'composer-patches' => [
          'patches' => [],
        ],
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($composer_json, JSON_PRETTY_PRINT));

    $commandTester = $this->getCommandTester(PatchMigrateCommand::class);
    $this->expectException(PatchMigrateConfigurationExistsException::class);
    $commandTester->execute([]);
  }
}
