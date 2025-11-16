<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchListCommand;

/**
 * Tests the PatchListCommand.
 */
class PatchListCommandTest extends PatchCommandTestBase {

  /**
   * Tests listing all patches.
   */
  public function testListAll() {
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['patches'] = [
      'vendor/package1' => [
        'Fix 1' => 'https://example.com/fix1.patch',
      ],
      'vendor/package2' => [
        'Fix 2' => 'https://example.com/fix2.patch',
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchListCommand::class);
    $tester->execute([]);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('Package: vendor/package1', $output);
    $this->assertStringContainsString('Fix 1', $output);
    $this->assertStringContainsString('https://example.com/fix1.patch', $output);
    $this->assertStringContainsString('Package: vendor/package2', $output);
    $this->assertStringContainsString('Fix 2', $output);
    $this->assertStringContainsString('https://example.com/fix2.patch', $output);
  }

  /**
   * Tests listing patches for a specific package.
   */
  public function testListPackage() {
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['patches'] = [
      'vendor/package1' => [
        'Fix 1' => 'https://example.com/fix1.patch',
      ],
      'vendor/package2' => [
        'Fix 2' => 'https://example.com/fix2.patch',
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchListCommand::class);
    $tester->execute(['package' => 'vendor/package1']);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('Package: vendor/package1', $output);
    $this->assertStringContainsString('Fix 1', $output);
    $this->assertStringContainsString('https://example.com/fix1.patch', $output);
    $this->assertStringNotContainsString('Package: vendor/package2', $output);
    $this->assertStringNotContainsString('Fix 2', $output);
  }

  /**
   * Tests listing patches for a non-existent package.
   */
  public function testListNonExistentPackage() {
    $tester = $this->getCommandTester(PatchListCommand::class);
    $tester->execute(['package' => 'non/existent']);

    $output = $tester->getDisplay();
    $this->assertStringContainsString('No patches found!', $output);
    $this->assertStringContainsString('There were not patches found for the given package: non/existent', $output);
  }

  /**
   * Tests listing patches when none are defined.
   */
  public function testListEmpty() {
    $tester = $this->getCommandTester(PatchListCommand::class);
    $tester->execute([]);

    $output = $tester->getDisplay();
    $this->assertEquals('', trim($output));
  }

}
