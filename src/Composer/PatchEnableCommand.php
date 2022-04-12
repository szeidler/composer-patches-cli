<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchEnableCommand extends PatchBaseCommand {

  protected function configure() {
    $this->setName('patch-enable')
      ->setDescription('Enables the patch functionality in your composer.json.')
      ->addOption(
        'file',
        '-f',
        InputOption::VALUE_OPTIONAL,
        'Which file name should your patch file use.'
      );

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $extra = $this->getComposer()->getPackage()->getExtra();

    // Check, if patch file is already defined.
    if (!empty($extra['patches-file'])) {
      throw new \Exception('Patch file was already defined in your composer.json.');
    }

    $composer_filename = 'composer.json';
    $patches_filename = $input->getOption('file');

    // Read in the current root composer.json file.
    $file = new JsonFile($composer_filename);
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Create patch file if specified and not existing.
    if (!empty($patches_filename)) {
      $patches_file = new JsonFile($patches_filename);
      if (!$patches_file->exists()) {
        if (copy(dirname(__FILE__) . '/../Fixtures/composer.patches.json', $patches_filename)) {
          $output->writeln('The composer patches file was created.');
        }
        else {
          throw new \Exception('Patch could not be created.');
        }
      }
      $manipulator->addProperty('extra.patches-file', $patches_filename);
    }
    else {
      // Create an empty patches definition in the root composer.json.
      if (!isset($extra['patches'])) {
        $manipulator->addProperty('extra.patches', []);
      }
    }

    // Enable patching.
    $manipulator->addProperty('extra.enable-patching', TRUE);

    // Store the manipulated JSON file.
    if (!file_put_contents($composer_filename, $manipulator->getContents())) {
      throw new \Exception('Composer file could not be saved. Please check the permissions.');
    }

    $output->writeln('The composer patches functionality was enabled successfully.');

    return 0;
  }
}
