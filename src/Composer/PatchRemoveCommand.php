<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
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
    $dialog = $this->getHelperSet()->get('dialog');
    if (!$input->getArgument('package')) {
      $package = $dialog->ask($output, '<question>Specify the package from where you want to remove a patch: </question>');
      $input->setArgument('package', $package);
    }
    if (!$input->getArgument('description')) {
      $description = $dialog->ask($output, '<question>Enter the short description of the patch to be removed: </question>');
      $input->setArgument('description', $description);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $config = new Config($this->getComposer());

    $package = $input->getArgument('package');
    $description = $input->getArgument('description');

    // Read in the current patch file.
    $file = new JsonFile($config->getPatchesFile());
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Merge patches for the package.
    $contents = $manipulator->getContents();
    $patches = json_decode($contents, TRUE);

    // Check if the given package has packages.
    if (!isset($patches['patches'][$package])) {
      throw new \InvalidArgumentException('The given package does not have patches in your composer.json.');
    }

    $package_patches = $patches['patches'][$package];

    // Remove the patch.
    if (isset($package_patches[$description])) {
      unset($package_patches[$description]);
    }
    else {
      throw new \InvalidArgumentException('The given patch description does not exist for this package.');
    }

    // Merge in the updated packages into the JSON again.
    $manipulator->addSubNode('patches', $package, $package_patches);

    // Store the manipulated JSON file.
    file_put_contents($config->getPatchesFile(), $manipulator->getContents());

    $output->writeln('The patch was successfully removed.');
  }
}
