<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Installer;

class PatchAddCommand extends PatchBaseCommand {

  /**
   * Indicates that a patch with the same Description already exists.
   */
  const PATCH_DUPE_DESCR = 1;
  
  /**
   * Indicates that a patch withy the same URL already exists.
   */
  const PATCH_DUPE_URL = 2;
  
  /**
   * Indicates that a patch exists with both same URL and same Description.
   */
  const PATCH_DUPE_EXISTS = 3;

  protected function configure() {
    $this->setName('patch-add')
      ->setDescription('Adds a patch to a composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::REQUIRED),
        new InputArgument('description', InputArgument::REQUIRED),
        new InputArgument('url', InputArgument::REQUIRED),
        new InputOption('no-update', null, InputOption::VALUE_NONE, 'Do not run an update: as side effect patch will not be applied.'),
        new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Run the dependency update with the --no-dev option.'),
      ]);

    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output) {
    $dialog = $this->getHelperSet()->get('dialog');
    if (!$input->getArgument('package')) {
      $package = $dialog->ask($output, '<question>Specify the package name to be patched: </question>');
      $input->setArgument('package', $package);
    }
    if (!$input->getArgument('description')) {
      $description = $dialog->ask($output, '<question>Enter a short description of the change: </question>');
      $input->setArgument('description', $description);
    }
    if (!$input->getArgument('url')) {
      $url = $dialog->ask($output, '<question>Enter the URL or Path of the patch: </question>');
      $input->setArgument('url', $url);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $extra = $extra = $this->getComposer()->getPackage()->getExtra();
    $package = $input->getArgument('package');
    $description = $input->getArgument('description');
    $url = $input->getArgument('url');
    $updateDevMode = !$input->getOption('no-dev');

    // The patch needs to be an existing local path or a valid URL.
    if (!file_exists($url) && filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      throw new \Exception('Your patch url argument must be a valid URL or local path.');
    }

    if ($this->getPatchType() === self::PATCHTYPE_ROOT) {
      $manipulator_filename = 'composer.json';
      $json_node = 'extra';
      $json_name = 'patches';
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE) {
      $manipulator_filename = $extra['patches-file'];
      $json_node = null;
      $json_name = 'patches';
    }
    else {
      throw new \Exception('Composer patches seems to be not enabled. Please enable composer patches first.');
    }

    // Get the defined patches.
    $patches = $this->grabPatches();

    // Add the patches of the packages from the composer patch file.
    $package_patches = $patches[$package] ?? [];

    if ($duplicate = $this->isDuplicatePatch($url, $description, $package_patches)) {
      switch ($duplicate) {
        case self::PATCH_DUPE_EXISTS:
          // Patch is already listes with same Description and same URL
          $output->writeln('<info>The patch already exists. If it is not applied please run "$ composer update ' . $package . '".</info>');
          // Nothing else to do here, return to the caller.
          return TRUE;
          break;
        
        case self::PATCH_DUPE_DESCR:
        case self::PATCH_DUPE_URL:
          $type = ($duplicate === self::PATCH_DUPE_URL) ? 'URL' : 'Description';
          $message = 'A patch with the same "' . $type . '" already exists.';
          throw new \InvalidArgumentException($message);
      }
    }

    // Add new patch.
    $package_patches[$description] = $url;
    $patches[$package] = $package_patches;

    // Read in the current root composer.json file.
    $file = new JsonFile($manipulator_filename);
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Merge in the updated packages into the JSON.
    $manipulator->addSubNode($json_node, $json_name, $patches);

    // Store the manipulated JSON file.
    if (!file_put_contents($manipulator_filename, $manipulator->getContents())) {
      throw new \Exception($extra['patches-file'] . ' file could not be saved. Please check the permissions.');
    }
    $output->writeln('The patch was successfully added.');

    if (!$input->getOption('no-update')) {
      // Trigger install command after adding a patch.
      $install = Installer::create($this->getIO(), $this->getComposer());

      // We run an update, because the patch will otherwise not end up in the
      // composer.lock. Beware: This could update the package unwanted.
      $install->setUpdate(TRUE)
        // Forward the option
        ->setVerbose($input->getOption('verbose'))
        // Only update the current package
        ->setUpdateWhitelist([$package])
        // Don't update the dependencies of the patched package.
        ->setWhitelistTransitiveDependencies(FALSE)
        ->setWhitelistAllDependencies(FALSE)
        // Patches are always considered to be applied in "dev mode".
        // This is also required to prevent composer from removing all installed
        // dev dependencies.
        ->setDevMode($updateDevMode)
        ->run();
    }
  }

  /**
   *  Checks if a patch is already listed in the existing patches.
   * 
   * @param string $patch_url
   *   URL address of the patch to check.
   * @param string $patch_description
   *   Description string of the patch to check.
   * @param array $patches
   *   Array of existing patches, indexed by the description.
   *
   * @return int|false
   *   One of the self::PATCH_DUPE_* constants, or False if patch is new.
   */
  protected function isDuplicatePatch(string $patch_url, string $patch_description, array $patches) {
    // Check for a duplicate description.
    $duplicate_description = isset($patches[$patch_description]);

    // Check for a duplicate URL.
    $duplicate_url = FALSE;
    foreach($patches as $url) {
      if ($url === $patch_url) {
        $duplicate_url = TRUE;
        break;
      }
    }

    if ($duplicate_description) {
      if ($duplicate_url) {
        // A patch with the same description and same url already exists.
        return self::PATCH_DUPE_EXISTS;
      }
      // A patch with the same description already exists.
      return self::PATCH_DUPE_DESCR;
    }

    if ($duplicate_url) {
      // A patch with the same URL already exists.
      return self::PATCH_DUPE_URL;
    }

    // The patch is not duplicate.
    return FALSE;
  }
}
