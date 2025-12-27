<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchAddCommand;

/**
 * Tests the PatchAddCommand.
 */
class PatchAddCommandTest extends PatchCommandTestBase {

  /**
   * Tests that a patch is added to composer.json.
   */
  public function testPatchIsAddedToComposerJson() {
    $tester = $this->getCommandTester(PatchAddCommand::class);

    // Create a patch file.
    $patchFile = $this->tempDir . '/fix.patch';
    file_put_contents($patchFile, 'dummy patch content');

    // Execute patch-add command.
    $tester->execute([
      'package' => 'vendor/package',
      'url' => $patchFile,
      'description' => 'Fix something',
    ]);

    // Run assertations.
    $output = $tester->getDisplay();

    $this->assertStringContainsString('The patch was successfully added.',
      $output);
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayHasKey('vendor/package', $json['extra']['patches']);
    $this->assertArrayHasKey('Fix something',
      $json['extra']['patches']['vendor/package']);
    $this->assertSame($patchFile,
      $json['extra']['patches']['vendor/package']['Fix something']);
  }

  /**
   * Tests that a patch is added to an external patches file.
   */
  public function testPatchIsAddedToExternalFile() {
    $patchesFile = $this->tempDir . '/patches.json';
    file_put_contents($this->composerJsonPath, json_encode([
      'name' => 'test/project',
      'extra' => [
        'patches-file' => 'patches.json',
      ],
    ]));
    file_put_contents($patchesFile, json_encode(['patches' => []]));

    $tester = $this->getCommandTester(PatchAddCommand::class);
    $patchFile = $this->tempDir . '/fix.patch';
    file_put_contents($patchFile, 'content');

    $tester->execute([
      'package' => 'vendor/package',
      'url' => $patchFile,
      'description' => 'External fix',
      '--no-update' => TRUE,
    ]);

    $this->assertStringContainsString('The patch was successfully added.', $tester->getDisplay());
    $json = json_decode(file_get_contents($patchesFile), TRUE);
    $this->assertArrayHasKey('vendor/package', $json['patches']);
    $this->assertEquals($patchFile, $json['patches']['vendor/package']['External fix']);
  }

  /**
   * Tests handling of duplicate patches.
   */
  public function testDuplicatePatchHandling() {
    $patchFile = $this->tempDir . '/fix.patch';
    file_put_contents($patchFile, 'content');

    // Set up composer.json with an existing patch.
    file_put_contents($this->composerJsonPath, json_encode([
      'name' => 'test/project',
      'extra' => [
        'patches' => [
          'vendor/package' => [
            'Existing description' => $patchFile,
          ],
        ],
      ],
    ]));

    $tester = $this->getCommandTester(PatchAddCommand::class);

    // 1. Exact duplicate (Same URL and Description) -> Should return 0 and info message.
    $tester->execute([
      'package' => 'vendor/package',
      'url' => $patchFile,
      'description' => 'Existing description',
    ]);
    $this->assertStringContainsString('The patch already exists.', $tester->getDisplay());

    // 2. Duplicate Description, different URL -> Should throw InvalidArgumentException.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A patch with the same "Description" already exists.');
    $tester->execute([
      'package' => 'vendor/package',
      'url' => 'https://example.com/other.patch',
      'description' => 'Existing description',
    ]);
  }

}
