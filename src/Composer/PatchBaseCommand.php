<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\Factory;
use Composer\Command\BaseCommand;

class PatchBaseCommand extends BaseCommand {

  protected function configure() {
    $this->changeWorkingDirToComposerRoot();
  }

  protected function changeWorkingDirToComposerRoot() {
    $io = $this->getIO();
    $dir = dirname(getcwd());
    $home = realpath(getenv('HOME') ?: getenv('USERPROFILE') ?: '/');
    // abort when we reach the home dir or top of the filesystem
    while (dirname($dir) !== $dir && $dir !== $home) {
      if (file_exists($dir . '/' . Factory::getComposerFile())) {
        if ($io->askConfirmation('<info>No composer.json in current directory, do you want to use the one at ' . $dir . '?</info> [<comment>Y,n</comment>]? ', TRUE)) {
          $oldWorkingDir = getcwd();
          chdir($dir);
        }
        break;
      }
      $dir = dirname($dir);
    }
  }

}
