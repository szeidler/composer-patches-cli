<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Composer\DependencyResolver\Request;
use Composer\Installer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchRemoveCommand extends PatchBaseCommand {

  protected function configure(): void {
    $this->setName('patch-remove')
      ->setDescription('Remove a from a composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::REQUIRED),
        new InputArgument('description', InputArgument::REQUIRED),
        new InputOption('no-update', null, InputOption::VALUE_NONE, 'Do not run an update: as side effect patch will not be removed from the installed package.'),
      ]);

    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output): void {
    $dialog = $this->getHelperSet()->get('question');
    if (!$input->getArgument('package')) {
      $question = new Question('Specify the package name to be patched: ');
      $package = $dialog->ask($input, $output, $question);
      $input->setArgument('package', $package);
    }
    if (!$input->getArgument('description')) {
      $question = new Question('Enter a short description of the change: ');
      $description = $dialog->ask($input, $output, $question);
      $input->setArgument('description', $description);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $extra = $extra = $this->requireComposer()->getPackage()->getExtra();
    $package = $input->getArgument('package');
    $description = $input->getArgument('description');

    if ($this->getPatchType() === self::PATCHTYPE_ROOT) {
      $manipulator_filename = 'composer.json';
      $json_node = 'extra';
      $json_name = 'composer-patches.patches';
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_ROOT_CP1) {
      $manipulator_filename = 'composer.json';
      $json_node = 'extra';
      $json_name = 'patches';
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE) {
      $manipulator_filename = $extra['composer-patches']['patches-file'];
      $json_node = null;
      $json_name = 'patches';
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE_CP1) {
      $manipulator_filename = $extra['patches-file'];
      $json_node = null;
      $json_name = 'patches';
    }
    else {
      throw new \Exception('Composer patches seems to be not enabled. Please enable composer patches first.');
    }

    // Read in the current patch file.
    $file = new JsonFile($manipulator_filename);
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Merge patches for the package.
    $patches = $this->grabPatches();

    // Remove the patch.
    if (isset($patches[$package][$description])) {
      unset($patches[$package][$description]);
    }
    else {
      throw new \InvalidArgumentException('The given patch description does not exist for this package.');
    }

    // Check if there is any remaining patch for the package. Otherwise remove
    // the empty package definition as well.
    if (empty($patches[$package])) {
      unset($patches[$package]);
    }

    // Merge in the updated packages into the JSON again.
    if ($this->getPatchType() === self::PATCHTYPE_ROOT || $this->getPatchType() === self::PATCHTYPE_ROOT_CP1) {
      $manipulator->addSubNode($json_node, $json_name, $patches);
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE || $this->getPatchType() === self::PATCHTYPE_FILE_CP1) {
      $manipulator->removeMainKey('patches');
      $manipulator->addMainKey('patches', $patches);
    }

    // Store the manipulated JSON file.
    if (!file_put_contents($manipulator_filename, $manipulator->getContents())) {
      throw new \Exception($manipulator_filename . ' file could not be saved. Please check the permissions.');
    }

    $output->writeln('The patch was successfully removed.');

    if (!$input->getOption('no-update')) {
      $updateDevMode = !$input->hasOption('no-dev') || !$input->getOption('no-dev');
      if (!$this->isComposerPatches1()) {
        $output->writeln('<info>Relocking patches...</info>');
        $this->runPatchesRelock();
        $output->writeln('<info>Repatching dependencies...</info>');
        $this->runRepatch();
      }
      $output->writeln('<info>Reinstalling package...</info>');
      $this->runReinstall($package, $updateDevMode);
    }

    return 0;
  }
}
