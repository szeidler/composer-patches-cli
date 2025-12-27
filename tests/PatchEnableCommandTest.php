<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchEnableCommand;

/**
 * Tests the PatchEnableCommand.
 */
class PatchEnableCommandTest extends PatchCommandTestBase {

  /**
   * Tests that patching is enabled in composer.json.
   */
  public function testEnableBasic() {
    $tester = $this->getCommandTester(PatchEnableCommand::class);

    $tester->execute([]);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('The composer patches functionality was enabled successfully.', $output);

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertTrue($json['extra']['enable-patching']);
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
    $this->assertTrue($json['extra']['enable-patching']);
    $this->assertEquals($patchesFilename, $json['extra']['patches-file']);
    $this->assertFileExists($this->tempDir . '/' . $patchesFilename);

    $patchesJson = json_decode(file_get_contents($this->tempDir . '/' . $patchesFilename), TRUE);
    $this->assertArrayHasKey('patches', $patchesJson);
  }

  /**
   * Tests that it fails if patches-file is already defined.
   */
  public function testEnableAlreadyDefined() {
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['patches-file'] = 'existing.json';
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchEnableCommand::class);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Patch file was already defined in your composer.json.');

    $tester->execute([]);
  }

}
