<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\Command\BaseCommand;
use Composer\Json\JsonFile;
use Composer\Semver\Comparator;
use Composer\Installer;
use Composer\DependencyResolver\Request;
use cweagans\Composer\Plugin\Patches;

class PatchBaseCommand extends BaseCommand {

  const PATCHTYPE_ROOT = 1;
  const PATCHTYPE_FILE = 2;
  const PATCHTYPE_ROOT_CP1 = 3;
  const PATCHTYPE_FILE_CP1 = 4;

  protected function configure(): void {
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
    $extra = $this->requireComposer()->getPackage()->getExtra();

    if (isset($extra['composer-patches']['patches'])) {
      return self::PATCHTYPE_ROOT;
    }
    elseif (isset($extra['patches'])) {
      return self::PATCHTYPE_ROOT_CP1;
    }
    elseif (isset($extra['composer-patches']['patches-file'])) {
      return self::PATCHTYPE_FILE;
    }
    elseif (isset($extra['patches-file'])) {
      return self::PATCHTYPE_FILE_CP1;
    }

    return NULL;
  }

  /**
   * Returns the version of cweagans/composer-patches if installed.
   *
   * @return string|null
   */
  protected function getComposerPatchesVersion() {
    $composer = $this->requireComposer();
    $repositoryManager = $composer->getRepositoryManager();
    $localRepository = $repositoryManager->getLocalRepository();
    $packages = $localRepository->getPackages();

    foreach ($packages as $package) {
      if ($package->getName() === 'cweagans/composer-patches') {
        return $package->getVersion();
      }
    }

    // Fallback: check require in composer.json if not in local repo (e.g. during tests or before install)
    $configPath = Factory::getComposerFile();
    if (file_exists($configPath)) {
      $config = json_decode(file_get_contents($configPath), true);
      $allRequires = array_merge($config['require'] ?? [], $config['require-dev'] ?? []);
      if (isset($allRequires['cweagans/composer-patches'])) {
        $versionConstraint = $allRequires['cweagans/composer-patches'];
        if (Comparator::lessThan($versionConstraint, '2.0.0') || strpos($versionConstraint, 'dev-') === 0) {
          return '1.99.99'; // Simulated version for Composer Patches 1
        }
      }
    }

    return NULL;
  }

  /**
   * Checks if the installed version of Composer Patches is version 1.
   *
   * @return bool
   */
  protected function isComposerPatches1() {
    $version = $this->getComposerPatchesVersion();
    return $version && version_compare($version, '2.0.0', '<');
  }

  /**
   * Get the Patches plugin instance.
   */
  protected function getPatchesPluginInstance() {
    foreach ($this->requireComposer()->getPluginManager()->getPlugins() as $plugin) {
      $className = get_class($plugin);
      if (str_starts_with($className, 'cweagans\Composer\Plugin\Patches')) {
        return $plugin;
      }
    }
    return NULL;
  }

  /**
   * Run the patches-relock command.
   */
  protected function runPatchesRelock(): void {
    $plugin = $this->getPatchesPluginInstance();
    if ($plugin) {
      if (file_exists($plugin->getLockFile()->getPath())) {
        unlink($plugin->getLockFile()->getPath());
      }
      $plugin->createNewPatchesLock();
    }
  }

  /**
   * Run the patches-repatch command.
   */
  protected function runRepatch(): void {
    $plugin = $this->getPatchesPluginInstance();
    if ($plugin) {
      $plugin->loadLockedPatches();
      $patchCollection = $plugin->getPatchCollection();
      if ($patchCollection) {
        $localRepository = $this->requireComposer()
          ->getRepositoryManager()
          ->getLocalRepository();

        $patched_packages = $patchCollection->getPatchedPackages();
        $packages = array_filter($localRepository->getPackages(), function ($val) use ($patched_packages) {
          return in_array($val->getName(), $patched_packages);
        });

        $promises = [];
        foreach ($packages as $package) {
          $uninstallOperation = new UninstallOperation($package);
          $promises[] = $this->requireComposer()
            ->getInstallationManager()
            ->uninstall($localRepository, $uninstallOperation);
        }

        $promises = array_filter($promises);
        if (!empty($promises)) {
          $this->requireComposer()->getLoop()->wait($promises);
        }

        $install = Installer::create($this->getIO(), $this->requireComposer());
        $install->run();
      }
    }
  }

  /**
   * Run the reinstall command for a package.
   *
   * @param string $package
   * @param bool $devMode
   */
  protected function runReinstall(string $package, bool $devMode = TRUE): void {
    $install = Installer::create($this->getIO(), $this->requireComposer());
    $install->setUpdate(TRUE)
      ->setUpdateAllowList([$package])
      ->setUpdateAllowTransitiveDependencies(Request::UPDATE_ONLY_LISTED)
      ->setDevMode($devMode)
      ->run();
  }

  /**
   * Updates the lock file hash.
   */
  protected function updateLockFile(): void {
    $composerJsonPath = Factory::getComposerFile();
    $composerJson = new JsonFile($composerJsonPath);
    $this->requireComposer()->getLocker()->updateHash($composerJson);
  }

  /**
   * Get the patches from root composer or external file
   *
   * Currently directly extracted from the Composer Patches code base.
   *
   * @return array
   * @throws \Exception
   * @see https://github.com/cweagans/composer-patches/blob/1.x/src/Patches.php
   */
  protected function grabPatches() {
    // First, try to get the patches from the root composer.json.
    $extra = $this->requireComposer()->getPackage()->getExtra();
    if ($this->getPatchType() === self::PATCHTYPE_ROOT) {
      $this->getIO()->write('<info>Gathering patches from root composer.json.</info>');
      $patches = $extra['composer-patches']['patches'];
      return $patches;
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_ROOT_CP1) {
      $this->getIO()->write('<info>Gathering patches from root composer.json (extra.patches).</info>');
      $patches = $extra['patches'];
      return $patches;
    }
    // If it's not specified there, look for a patches-file definition.
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE) {
      $this->getIO()->write('<info>Gathering patches from patch file.</info>');
      $patchesFile = $extra['composer-patches']['patches-file'];
      $patches = file_get_contents($patchesFile);
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
        return $patches['patches'];
      }
      elseif (!$patches) {
        throw new \Exception('There was an error in the supplied patch file');
      }
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE_CP1) {
      $this->getIO()->write('<info>Gathering patches from patch file (extra.patches-file).</info>');
      $patchesFile = $extra['patches-file'];
      $patches = file_get_contents($patchesFile);
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
        return $patches['patches'];
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
