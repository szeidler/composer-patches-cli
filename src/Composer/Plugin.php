<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capable;

class Plugin implements PluginInterface, Capable {

  public function activate(Composer $composer, IOInterface $io) {
  }

  public function getCapabilities() {
    return [
      'Composer\Plugin\Capability\CommandProvider' => 'szeidler\ComposerPatchesCLI\Composer\CommandProvider',
    ];
  }

  public function deactivate(Composer $composer, IOInterface $io)
  {
  }

  public function uninstall(Composer $composer, IOInterface $io)
  {
  }

}
