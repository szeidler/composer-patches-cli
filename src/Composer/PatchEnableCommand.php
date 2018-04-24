<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Command\BaseCommand;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchEnableCommand extends BaseCommand {

  protected function configure() {
    $default_file_name = 'composer.patches.json';
    $this->setName('patch-enable')
      ->setDescription('Enables the patch functionality in your composer.json.')
      ->addOption(
        'file',
        '-f',
        InputOption::VALUE_REQUIRED,
        'Which file name should your patch file use.',
        $default_file_name
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $composer_filename = 'composer.json';
    $patches_filename = $input->getOption('file');

    // Read in the current root composer.json file.
    $file = new JsonFile($composer_filename);
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));

    // Create patch file if not existing.
    $patches_file = new JsonFile($patches_filename);
    if (!$patches_file->exists()) {
      copy(dirname(__FILE__ ) . '/../Fixtures/composer.patches.json.dist', $patches_filename);
      $output->writeln('The composer patches file was created.');
    }

    // Enable patching and define the patch file.
    $manipulator->addProperty('extra.enable-patching', TRUE);
    $manipulator->addProperty('extra.patches-file', $patches_filename);

    // Store the manipulated JSON file.
    file_put_contents($composer_filename, $manipulator->getContents());

    $output->writeln('The composer patches functionality was enabled successfully.');
  }
}
