<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchMigrateCommand;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateConfigurationExistsException;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateNoConfigurationFoundException;

/**
 * Tests the PatchMigrateCommand class.
 */
class PatchMigrateCommandTest extends PatchCommandTestBase {

  /**
   * Tests migrating patches from the root composer.json.
   */
  public function testMigrateRoot() {
    $composer_json = [
      'name' => 'test/project',
      'extra' => [
        'patches' => [
          'vendor/package' => [
            'description' => 'https://example.com/patch.patch',
          ],
        ],
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
        'composer-exit-on-patch-failure' => true,
        'enable-patching' => true,
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($composer_json, JSON_PRETTY_PRINT));

    $commandTester = $this->getCommandTester(PatchMigrateCommand::class);
    $commandTester->execute([]);

    $this->assertStringContainsString('Migrating patches from root composer.json...', $commandTester->getDisplay());
    $this->assertStringContainsString('Migration completed successfully.', $commandTester->getDisplay());
    $this->assertStringContainsString('Relocking patches...', $commandTester->getDisplay());
    $this->assertStringContainsString('Repatching dependencies...', $commandTester->getDisplay());

    $updated_composer_json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    
    $this->assertArrayNotHasKey('patches', $updated_composer_json['extra']);
    $this->assertArrayHasKey('composer-patches', $updated_composer_json['extra']);
    $this->assertEquals($composer_json['extra']['patches'], $updated_composer_json['extra']['composer-patches']['patches']);
    
    $this->assertArrayNotHasKey('patches-ignore', $updated_composer_json['extra']);
    $this->assertEquals(['dependency/package'], $updated_composer_json['extra']['composer-patches']['ignore-dependency-patches']);
    
    $this->assertArrayNotHasKey('patchLevel', $updated_composer_json['extra']);
    $this->assertEquals(['vendor/package' => 2], $updated_composer_json['extra']['composer-patches']['package-depths']);
    
    $this->assertArrayNotHasKey('composer-exit-on-patch-failure', $updated_composer_json['extra']);
    $this->assertTrue($updated_composer_json['extra']['composer-patches']['exit-on-patch-failure']);

    $this->assertArrayNotHasKey('enable-patching', $updated_composer_json['extra']);
  }

  /**
   * Tests migrating patches from an external patches file.
   */
  public function testMigrateFile() {
    $patches_file = 'patches.json';
    $composer_json = [
      'name' => 'test/project',
      'extra' => [
        'patches-file' => $patches_file,
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($composer_json, JSON_PRETTY_PRINT));

    $patches_json = [
      'patches' => [
        'vendor/package' => [
          'description' => 'https://example.com/patch.patch',
        ],
      ],
    ];
    file_put_contents($this->tempDir . '/' . $patches_file, json_encode($patches_json, JSON_PRETTY_PRINT));

    $commandTester = $this->getCommandTester(PatchMigrateCommand::class);
    $commandTester->execute([]);

    $this->assertStringContainsString("Migrating patches from $patches_file...", $commandTester->getDisplay());
    $this->assertStringContainsString('Migration completed successfully.', $commandTester->getDisplay());
    $this->assertStringContainsString('Relocking patches...', $commandTester->getDisplay());
    $this->assertStringContainsString('Repatching dependencies...', $commandTester->getDisplay());

    $updated_composer_json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayNotHasKey('patches-file', $updated_composer_json['extra']);
    $this->assertEquals($patches_file, $updated_composer_json['extra']['composer-patches']['patches-file']);
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
