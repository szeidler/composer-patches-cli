<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\Factory;
use Composer\Command\BaseCommand;

class PatchBaseCommand extends BaseCommand {

  const PATCHTYPE_ROOT = 1;
  const PATCHTYPE_FILE = 2;

  protected function configure() {
    parent::configure();
  }

  /**
   * Get the patch storage type.
   *
   * This can be either the root composer.json or a composer patches file.
   *
   * @return int|null
   *   The patch type.
   */
  protected function getPatchType() {
    $extra = $this->getComposer()->getPackage()->getExtra();

    if (isset($extra['patches'])) {
      return self::PATCHTYPE_ROOT;
    }
    elseif (isset($extra['patches-file'])) {
      return self::PATCHTYPE_FILE;
    }

    return NULL;
  }

  /**
   * Get the patches from root composer or external file
   *
   * Currently directly extracted from the Composer Patches code base.
   *
   * @return Patches
   * @throws \Exception
   * @see https://github.com/cweagans/composer-patches/blob/1.x/src/Patches.php
   */
  protected function grabPatches() {
    // First, try to get the patches from the root composer.json.
    $extra = $this->getComposer()->getPackage()->getExtra();
    if ($this->getPatchType() === self::PATCHTYPE_ROOT) {
      $this->getIO()->write('<info>Gathering patches from root composer.json.</info>');
      $patches = $extra['patches'];
      return $patches;
    }
    // If it's not specified there, look for a patches-file definition.
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE) {
      $this->getIO()->write('<info>Gathering patches from patch file.</info>');
      $patches = file_get_contents($extra['patches-file']);
      $patches = json_decode($patches, TRUE);
      $error = json_last_error();
      if ($error != 0) {
        switch ($error) {
          case JSON_ERROR_DEPTH:
            $msg = ' - Maximum stack depth exceeded';
            break;
          case JSON_ERROR_STATE_MISMATCH:
            $msg = ' - Underflow or the modes mismatch';
            break;
          case JSON_ERROR_CTRL_CHAR:
            $msg = ' - Unexpected control character found';
            break;
          case JSON_ERROR_SYNTAX:
            $msg = ' - Syntax error, malformed JSON';
            break;
          case JSON_ERROR_UTF8:
            $msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
          default:
            $msg = ' - Unknown error';
            break;
        }
        throw new \Exception('There was an error in the supplied patches file:' . $msg);
      }
      if (isset($patches['patches'])) {
        $patches = $patches['patches'];
        return $patches;
      }
      elseif (!$patches) {
        throw new \Exception('There was an error in the supplied patch file');
      }
    }
    else {
      return [];
    }
  }

}
