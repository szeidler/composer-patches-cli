<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateConfigurationExistsException;
use szeidler\ComposerPatchesCLI\Exception\PatchMigrateNoConfigurationFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PatchMigrateCommand extends PatchBaseCommand {

  protected function configure(): void {
    $this->setName('patch-migrate-config')
      ->setDescription('Migrate Composer Patches 1 configuration to Composer Patches 2.');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $extra = $this->requireComposer()->getPackage()->getExtra();
    $patchType = $this->getPatchType();

    if (isset($extra['composer-patches'])) {
      throw new PatchMigrateConfigurationExistsException('Composer Patches 2 configuration already exists.');
    }

    if ($patchType !== self::PATCHTYPE_ROOT_CP1 && $patchType !== self::PATCHTYPE_FILE_CP1) {
      throw new PatchMigrateNoConfigurationFoundException('No Composer Patches 1 configuration found to migrate.');
    }

    $composer_filename = 'composer.json';
    $composer_file = new JsonFile($composer_filename);
    $composer_manipulator = new JsonManipulator(file_get_contents($composer_file->getPath()));

    $patches = $this->grabPatches();

    if ($patchType === self::PATCHTYPE_ROOT_CP1) {
      $output->writeln('<info>Migrating patches from root composer.json...</info>');

      // Move patches to the new location.
      $composer_manipulator->removeSubNode('extra', 'patches');
      $composer_manipulator->addSubNode('extra', 'composer-patches.patches', $patches);
    }
    elseif ($patchType === self::PATCHTYPE_FILE_CP1) {
      $patches_filename = $extra['patches-file'];
      $output->writeln("<info>Migrating patches from $patches_filename...</info>");

      // Update composer.json to use the new patches-file location.
      $composer_manipulator->removeSubNode('extra', 'patches-file');
      $composer_manipulator->addSubNode('extra', 'composer-patches.patches-file', $patches_filename);
    }

    // Handle patches-ignore -> ignore-dependency-patches
    if (isset($extra['patches-ignore'])) {
      $ignored = [];
      foreach ($extra['patches-ignore'] as $package => $package_patches) {
        // CP1 patches-ignore format is slightly different, but often it was just a list of patches.
        // "patches-ignore": { "source/package": { "target/package": { "description": "url" } } }
        // CP2 ignore-dependency-patches is just a list of packages whose patches should be ignored.
        // "ignore-dependency-patches": ["some/package"]
        $ignored[] = $package;
      }
      $composer_manipulator->removeSubNode('extra', 'patches-ignore');
      $composer_manipulator->addSubNode('extra', 'composer-patches.ignore-dependency-patches', array_unique($ignored));
    }

    // Handle patchLevel -> package-depths
    if (isset($extra['patchLevel'])) {
      $depths = [];
      foreach ($extra['patchLevel'] as $package => $level) {
        // Convert -p1 to 1
        $depths[$package] = (int) str_replace('-p', '', $level);
      }
      $composer_manipulator->removeSubNode('extra', 'patchLevel');
      $composer_manipulator->addSubNode('extra', 'composer-patches.package-depths', $depths);
    }

    // Handle enable-patching, composer-patches-skip-reporting and composer-exit-on-patch-failure (cleanup)
    $cleanup_keys = [
      'enable-patching',
      'composer-patches-skip-reporting',
      'composer-exit-on-patch-failure',
    ];
    foreach ($cleanup_keys as $cleanup_key) {
      if (isset($extra[$cleanup_key])) {
        $composer_manipulator->removeSubNode('extra', $cleanup_key);
      }
    }

    // Store the manipulated JSON file.
    if (!file_put_contents($composer_filename, $composer_manipulator->getContents())) {
      throw new \Exception('Composer file could not be saved.');
    }

    $output->writeln('Migration completed successfully.');

    $output->writeln(
      '<info>Running composer update nothing to refresh lock file...</info>',
    );
    $this->updateLockFile();
    $this->resetComposer();

    $output->writeln('<info>Relocking patches...</info>');
    $this->runPatchesRelock();

    $output->writeln('<info>Repatching dependencies...</info>');
    $this->runRepatch();

    return 0;
  }
}
