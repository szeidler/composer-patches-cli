<?php

namespace szeidler\ComposerPatchesCLI\Tests;

use szeidler\ComposerPatchesCLI\Composer\PatchMoveToLocalCommand;

/**
 * Tests the PatchMoveToLocalCommand.
 */
class PatchMoveToLocalCommandTest extends PatchCommandTestBase {

  /**
   * Tests moving a remote patch to local.
   */
  public function testMoveToLocal() {
    // Create a "remote" patch file.
    // The command checks for 'merge_requests/' in the URL.
    // To trigger the filename prefixing logic, it needs '/-/merge_requests/'.
    $remoteDir = $this->tempDir . '/remote/-/merge_requests';
    mkdir($remoteDir, 0777, TRUE);
    $patchFile = $remoteDir . '/1.diff';
    file_put_contents($patchFile, 'patch content');
    
    // Use a file:// URL to simulate a remote URL that filter_var(..., FILTER_VALIDATE_URL) accepts.
    $url = 'file://' . $patchFile;

    // Set up composer.json with this remote patch.
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['composer-patches']['patches'] = [
      'vendor/package' => [
        'Fix something' => $url,
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchMoveToLocalCommand::class);

    // Add a directory relative to the composer.json file.
    $localDir = 'patches';
    $tester->execute([
      'directory' => $localDir,
    ]);
    
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $newPath = $json['extra']['composer-patches']['patches']['vendor/package']['Fix something'];
    
    $this->assertStringContainsString('patches/fix-something-1.diff', $newPath);
    $this->assertFileExists($this->tempDir . '/' . $newPath);
    $this->assertEquals('patch content', file_get_contents($this->tempDir . '/' . $newPath));
  }

  /**
   * Tests moving remote patches when using an external patches file.
   */
  public function testMoveToLocalExternalFile() {
    $patchesFile = 'patches.json';
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['composer-patches']['patches-file'] = $patchesFile;
    unset($json['extra']['composer-patches']['patches']);
    file_put_contents($this->composerJsonPath, json_encode($json));

    $remoteDir = $this->tempDir . '/remote/-/merge_requests';
    if (!is_dir($remoteDir)) {
      mkdir($remoteDir, 0777, TRUE);
    }
    $patchFile = $remoteDir . '/2.diff';
    file_put_contents($patchFile, 'external patch content');
    $url = 'file://' . $patchFile;

    file_put_contents($this->tempDir . '/' . $patchesFile, json_encode([
      'patches' => [
        'vendor/package' => [
          'External fix' => $url,
        ],
      ],
    ]));

    $tester = $this->getCommandTester(PatchMoveToLocalCommand::class);
    
    $tester->execute([
      'directory' => 'local_patches',
    ]);

    $patchesJson = json_decode(file_get_contents($this->tempDir . '/' . $patchesFile), TRUE);
    $newPath = $patchesJson['patches']['vendor/package']['External fix'];
    
    $this->assertStringContainsString('local_patches/external-fix-2.diff', $newPath);
    $this->assertFileExists($this->tempDir . '/' . $newPath);
    $this->assertEquals('external patch content', file_get_contents($this->tempDir . '/' . $newPath));
  }

  /**
   * Tests that nothing happens when no remote patches match the criteria.
   */
  public function testNoPatchesModified() {
    $url = 'https://example.com/regular.patch'; // Does not contain 'merge_requests/'

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $json['extra']['composer-patches']['patches'] = [
      'vendor/package' => [
        'Regular patch' => $url,
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchMoveToLocalCommand::class);
    
    $tester->execute([
      'directory' => 'patches',
    ]);

    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $this->assertEquals($url, $json['extra']['composer-patches']['patches']['vendor/package']['Regular patch']);
  }

  /**
   * Tests moving a remote patch to local using Composer Patches 1 style.
   */
  public function testMoveToLocalComposerPatches1() {
    // Create a "remote" patch file.
    $remoteDir = $this->tempDir . '/remote/-/merge_requests';
    mkdir($remoteDir, 0777, TRUE);
    $patchFile = $remoteDir . '/1.diff';
    file_put_contents($patchFile, 'patch content');
    
    $url = 'file://' . $patchFile;

    // Set up composer.json with CP1 style patches.
    $json = [
      'name' => 'test/project',
      'extra' => [
        'patches' => [
          'vendor/package' => [
            'Fix bug with Composer Patches 1' => $url,
          ],
        ],
      ],
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $tester = $this->getCommandTester(PatchMoveToLocalCommand::class);

    $localDir = 'patches';
    $tester->execute([
      'directory' => $localDir,
    ]);
    
    $json = json_decode(file_get_contents($this->composerJsonPath), TRUE);
    $newPath = $json['extra']['patches']['vendor/package']['Fix bug with Composer Patches 1'];
    
    $this->assertStringContainsString('patches/fix-bug-with-composer-patches-1-1.diff', $newPath);
    $this->assertFileExists($this->tempDir . '/' . $newPath);
  }

  /**
   * Tests moving remote patches when using an external patches file with Composer Patches 1 style.
   */
  public function testMoveToLocalExternalFileComposerPatches1() {
    $patchesFile = 'patches.json';
    $json = [
      'name' => 'test/project',
      'require' => [
        'cweagans/composer-patches' => '1.7.3'
      ],
      'extra' => [
        'patches-file' => $patchesFile,
      ]
    ];
    file_put_contents($this->composerJsonPath, json_encode($json));

    $remoteDir = $this->tempDir . '/remote/-/merge_requests';
    if (!is_dir($remoteDir)) {
      mkdir($remoteDir, 0777, TRUE);
    }
    $patchFile = $remoteDir . '/2.diff';
    file_put_contents($patchFile, 'external patch content');
    $url = 'file://' . $patchFile;

    file_put_contents($this->tempDir . '/' . $patchesFile, json_encode([
      'patches' => [
        'vendor/package' => [
          'External fix' => $url,
        ],
      ],
    ]));

    $tester = $this->getCommandTester(PatchMoveToLocalCommand::class);
    
    $tester->execute([
      'directory' => 'local_patches',
    ]);

    $patchesJson = json_decode(file_get_contents($this->tempDir . '/' . $patchesFile), TRUE);
    $newPath = $patchesJson['patches']['vendor/package']['External fix'];
    
    $this->assertStringContainsString('local_patches/external-fix-2.diff', $newPath);
    $this->assertFileExists($this->tempDir . '/' . $newPath);
    $this->assertEquals('external patch content', file_get_contents($this->tempDir . '/' . $newPath));
  }
}
