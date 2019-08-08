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

  protected function configure() {
    $this->setName('patch-add')
      ->setDescription('Adds a patch to a composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::REQUIRED),
        new InputArgument('description', InputArgument::REQUIRED),
        new InputArgument('url', InputArgument::REQUIRED),
        new InputOption('no-update', null, InputOption::VALUE_NONE, 'Do not run an update: as side effect patch will not be applied.'),
        new InputOption('update-no-dev', null, InputOption::VALUE_NONE, 'Run the dependency update with the --no-dev option.'),
      ]);

    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output) {
    $dialog = $this->getHelperSet()->get('dialog');
    if (!$input->getArgument('package')) {
      $package = $dialog->ask($output, '<question>Please enter the package, you want to patch.</question>');
      $input->setArgument('package', $package);
    }
    if (!$input->getArgument('description')) {
      $description = $dialog->ask($output, '<question>Please enter a description for the patch.</question>');
      $input->setArgument('description', $description);
    }
    if (!$input->getArgument('url')) {
      $url = $dialog->ask($output, '<question>Please enter the URL or path of the patch.</question>');
      $input->setArgument('url', $url);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $config = new Config($this->getComposer());

    $package = $input->getArgument('package');
    $description = $input->getArgument('description');
    $url = $input->getArgument('url');
    $updateDevMode = !$input->getOption('update-no-dev');

    // Validate the patch url argument.
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      throw new \Exception('Your patch url argument must be a valid URL.');
    }

    // Read in the current patch file.
    $file = new JsonFile($config->getPatchesFile());
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Merge patches for the package.
    $contents = $manipulator->getContents();
    $patches = json_decode($contents, TRUE);

    $package_patches = [];
    // Add the patches of the packages from the composer patch file.
    if (isset($patches['patches'][$package])) {
      $package_patches = $patches['patches'][$package];
    }

    if (isset($package_patches[$description])) {
      throw new \Exception('The patch description already exists. Make sure to add patches only once and use an unique description.');
    }

    // Add new patch.
    $package_patches[$description] = $url;

    // Merge in the updated packages into the JSON again.
    $manipulator->addSubNode('patches', $package, $package_patches);

    // Store the manipulated JSON file.
    file_put_contents($config->getPatchesFile(), $manipulator->getContents());

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

}
