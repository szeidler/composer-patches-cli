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

      // Handle composer-exit-on-patch-failure -> exit-on-patch-failure
      if (isset($extra['composer-exit-on-patch-failure'])) {
        $composer_manipulator->removeSubNode('extra', 'composer-exit-on-patch-failure');
        $composer_manipulator->addSubNode('extra', 'composer-patches.exit-on-patch-failure', $extra['composer-exit-on-patch-failure']);
      }

      // Handle composer-patches-skip-reporting -> skip-reporting
      if (isset($extra['composer-patches-skip-reporting'])) {
        $composer_manipulator->removeSubNode('extra', 'composer-patches-skip-reporting');
        $composer_manipulator->addSubNode('extra', 'composer-patches.skip-reporting', $extra['composer-patches-skip-reporting']);
      }

      // Handle enable-patching (cleanup)
      if (isset($extra['enable-patching'])) {
        $composer_manipulator->removeSubNode('extra', 'enable-patching');
      }

      // Store the manipulated JSON file.
      if (!file_put_contents($composer_filename, $composer_manipulator->getContents())) {
        throw new \Exception('Composer file could not be saved.');
      }
    }
    elseif ($patchType === self::PATCHTYPE_FILE_CP1) {
      $patches_filename = $extra['patches-file'];
      $output->writeln("<info>Migrating patches from $patches_filename...</info>");

      // Update composer.json to use the new patches-file location.
      $composer_manipulator->removeSubNode('extra', 'patches-file');
      $composer_manipulator->addSubNode('extra', 'composer-patches.patches-file', $patches_filename);
      
      if (!file_put_contents($composer_filename, $composer_manipulator->getContents())) {
        throw new \Exception('Composer file could not be saved.');
      }

      // Update the patches file itself.
      $patches_file = new JsonFile($patches_filename);
      $patches_manipulator = new JsonManipulator(file_get_contents($patches_file->getPath()));
      // CP2 expects patches to be in the root "patches" key of the patches-file, which is the same as CP1.
      // So no changes might be needed to the content of the file itself if it only contains "patches".
      // However, we should check if there's anything else in there.
    }

    $output->writeln('Migration completed successfully.');
    
    $application = $this->getApplication();
    $application->setAutoExit(FALSE);
    $output->writeln('<info>Relocking patches...</info>');
    $application->run(new ArrayInput(['command' => 'patches-relock']), $output);

    $output->writeln('<info>Repatching dependencies...</info>');
    $application->run(new ArrayInput(['command' => 'patches-repatch']), $output);

    return 0;
  }
}
