<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchAddCommand extends PatchBaseCommand {

  protected function configure() {
    $this->setName('patch-add')
      ->setDescription('Adds a patch to a composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::REQUIRED),
        new InputArgument('description', InputArgument::REQUIRED),
        new InputArgument('url', InputArgument::REQUIRED),
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
  }

}
