<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchEnableCommand;
use Composer\Package\Package;

/**
 * Tests the PatchEnableCommand.
 */
class PatchEnableCommandTest extends PatchCommandTestBase {

  /**
   * Tests that patching is enabled in composer.json (Composer Patches 2 style).
   */
  public function testEnableComposerPatches2() {
    // Ensure we start without patches
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    unset($json['extra']['composer-patches']['patches']);
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchEnableCommand::class);

    // Mock Composer Patches 2 version
    $package = new Package('cweagans/composer-patches', '2.0.0.0', '2.0.0');
    $this->composer->getRepositoryManager()->getLocalRepository()->addPackage($package);

    $tester->execute([]);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('The composer patches functionality was enabled successfully.', $output);

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayHasKey('patches', $json['extra']['composer-patches']);
    $this->assertEquals([], $json['extra']['composer-patches']['patches']);
  }

  /**
   * Tests that patching is enabled in composer.json (Composer Patches 1 style).
   */
  public function testEnableComposerPatches1() {
    // Mock Composer Patches 1 in requires
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['require']['cweagans/composer-patches'] = '1.7.3';
    // Remove existing patch definition from fixture to allow enabling it again
    unset($json['extra']['composer-patches']['patches']);
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchEnableCommand::class);

    $tester->execute([]);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('The composer patches functionality was enabled successfully.', $output);

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertArrayHasKey('patches', $json['extra']);
    $this->assertEquals([], $json['extra']['patches']);
  }

  /**
   * Tests that patching is enabled with an external file.
   */
  public function testEnableWithExternalFile() {
    $tester = $this->getCommandTester(PatchEnableCommand::class);
    $patchesFilename = 'patches.json';

    $tester->execute([
      '--file' => $patchesFilename,
    ]);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('The composer patches file was created.', $output);
    $this->assertStringContainsString('The composer patches functionality was enabled successfully.', $output);

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertEquals($patchesFilename, $json['extra']['composer-patches']['patches-file']);
    $this->assertFileExists($this->tempDir . '/' . $patchesFilename);

    $patchesJson = json_decode(file_get_contents($this->tempDir . '/' . $patchesFilename), TRUE);
    $this->assertArrayHasKey('patches', $patchesJson);
  }

  /**
   * Tests that it fails if patches-file is already defined.
   */
  public function testEnableAlreadyDefined() {
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['composer-patches']['patches-file'] = 'existing.json';
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchEnableCommand::class);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Patch file was already defined in your composer.json.');

    $tester->execute([]);
  }

}
