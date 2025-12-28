<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchRemoveCommand;

/**
 * Tests the PatchRemoveCommand.
 */
class PatchRemoveCommandTest extends PatchCommandTestBase {

  /**
   * Tests that a patch is removed from composer.json.
   */
  public function testPatchIsRemovedFromComposerJson() {
    file_put_contents($this->composerJsonPath, json_encode([
      'name' => 'test/project',
      'extra' => [
        'composer-patches' => [
          'patches' => [
            'vendor/package' => [
              'Fix bug' => 'path/to/fix.patch',
            ],
          ],
        ],
      ],
    ]));

    $tester = $this->getCommandTester(PatchRemoveCommand::class);
    $tester->execute([
      'package' => 'vendor/package',
      'description' => 'Fix bug',
    ]);

    $this->assertStringContainsString('The patch was successfully removed.', $tester->getDisplay());
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayNotHasKey('vendor/package', $json['extra']['composer-patches']['patches']);
  }

  /**
   * Tests that a patch is removed from an external patches file.
   */
  public function testPatchIsRemovedFromExternalFile() {
    $patchesFile = $this->tempDir . '/patches.json';
    file_put_contents($this->composerJsonPath, json_encode([
      'name' => 'test/project',
      'extra' => ['composer-patches' => ['patches-file' => 'patches.json']],
    ]));
    file_put_contents($patchesFile, json_encode([
      'patches' => [
        'vendor/package' => [
          'External fix' => 'path/to/fix.patch',
          'Keep this' => 'path/to/keep.patch',
        ],
      ],
    ]));

    $tester = $this->getCommandTester(PatchRemoveCommand::class);
    $tester->execute([
      'package' => 'vendor/package',
      'description' => 'External fix',
    ]);

    $this->assertStringContainsString('The patch was successfully removed.', $tester->getDisplay());
    $json = json_decode(file_get_contents($patchesFile), TRUE);
    $this->assertArrayNotHasKey('External fix', $json['patches']['vendor/package']);
    $this->assertArrayHasKey('Keep this', $json['patches']['vendor/package']);
  }

  /**
   * Tests removing a patch that does not exist.
   */
  public function testRemoveNonExistentPatchThrowsException() {
    file_put_contents($this->composerJsonPath, json_encode([
      'name' => 'test/project',
      'extra' => ['composer-patches' => ['patches' => ['vendor/package' => ['Real patch' => 'path/to/patch']]]],
    ]));

    $tester = $this->getCommandTester(PatchRemoveCommand::class);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The given patch description does not exist for this package.');

    $tester->execute([
      'package' => 'vendor/package',
      'description' => 'Non-existent patch',
    ]);
  }
}
