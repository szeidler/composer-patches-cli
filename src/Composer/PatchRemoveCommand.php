<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchRemoveCommand extends PatchBaseCommand {

  protected function configure() {
    $this->setName('patch-remove')
      ->setDescription('Remove a from a composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::REQUIRED),
        new InputArgument('description', InputArgument::REQUIRED)
      ]);

    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output) {
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

  protected function execute(InputInterface $input, OutputInterface $output) {
    $extra = $extra = $this->getComposer()->getPackage()->getExtra();
    $package = $input->getArgument('package');
    $description = $input->getArgument('description');

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
    if ($this->getPatchType() === self::PATCHTYPE_ROOT) {
      $manipulator->addSubNode($json_node, $json_name, $patches);
    }
    elseif ($this->getPatchType() === self::PATCHTYPE_FILE) {
      $manipulator->removeMainKey('patches');
      $manipulator->addMainKey('patches', $patches);
    }

    // Store the manipulated JSON file.
    if (!file_put_contents($manipulator_filename, $manipulator->getContents())) {
      throw new \Exception($extra['patches-file'] . ' file could not be saved. Please check the permissions.');
    }

    $output->writeln('The patch was successfully removed.');
  }
}
