<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\Composer;

class Config {

  protected $patchesFile;

  /**
   * Config constructor.
   */
  public function __construct(Composer $composer) {
    $extra = $composer->getPackage()->getExtra();
    if (empty($extra['patches-file'])) {
      throw new \Exception('Patch file was not defined in your composer.json.');
    }
    if (!file_exists($extra['patches-file'])) {
      throw new \Exception('Patch file ' . $extra['patches-file'] . ' does not exists.');
    }

    $this->setPatchesFile($extra['patches-file']);
  }

  /**
   * @return mixed
   */
  public function getPatchesFile() {
    return $this->patchesFile;
  }

  /**
   * @param mixed $patchesFile
   */
  public function setPatchesFile($patchesFile) {
    $this->patchesFile = $patchesFile;
  }

}