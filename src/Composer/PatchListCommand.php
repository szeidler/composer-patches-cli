<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

class PatchListCommand extends PatchBaseCommand {

  protected function configure() {
    $this->setName('patch-list')
      ->setDescription('Lists the patches from the composer patch file.')
      ->setDefinition([
        new InputArgument('package', InputArgument::OPTIONAL),
      ]);

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $formatter = $this->getHelper('formatter');
    $package = $input->getArgument('package');

    // Grab the defined patches.
    $patches = $this->grabPatches();

    if (empty($package)) {
      // If the package argument is missing, list patches for all packages.
      foreach ($patches as $package => $packagePatches) {
        $output->writeLn('Package: ' . $package);

        // Create the patches table.
        $table = new Table($output);
        $table->setHeaders(['Description', 'URL']);
        foreach ($packagePatches as $description => $url) {
          $table->addRow([$description, $url]);
        }
        $table->render();
        $output->writeLn('');
      }
    }
    else {
      // List patches for the given package.
      if (isset($patches[$package])) {
        $output->writeLn('Package: ' . $package);

        // Create the patches table.
        $table = new Table($output);
        $table->setHeaders(['Description', 'URL']);
        foreach ($patches[$package] as $description => $url) {
          $table->addRow([$description, $url]);
        }
        $table->render();
      }
      else {
        $message = [
          'No patches found!',
          'There were not patches found for the given package: ' . $package,
        ];
        $formattedBlock = $formatter->formatBlock($message, 'error');
        $output->writeln($formattedBlock);
      }
    }
  }

  /**
   * Get the patches from root composer or external file
   *
   * Currently directly extracted from the Composer Patches code base.
   *
   * @return Patches
   * @throws \Exception
   * @see https://github.com/cweagans/composer-patches/blob/1.x/src/Patches.php
   */
  protected function grabPatches() {
    // First, try to get the patches from the root composer.json.
    $extra = $this->getComposer()->getPackage()->getExtra();
    if (isset($extra['patches'])) {
      $this->getIO()->write('<info>Gathering patches for root package.</info>');
      $patches = $extra['patches'];
      return $patches;
    }
    // If it's not specified there, look for a patches-file definition.
    elseif (isset($extra['patches-file'])) {
      $this->getIO()->write('<info>Gathering patches from patch file.</info>');
      $patches = file_get_contents($extra['patches-file']);
      $patches = json_decode($patches, TRUE);
      $error = json_last_error();
      if ($error != 0) {
        switch ($error) {
          case JSON_ERROR_DEPTH:
            $msg = ' - Maximum stack depth exceeded';
            break;
          case JSON_ERROR_STATE_MISMATCH:
            $msg = ' - Underflow or the modes mismatch';
            break;
          case JSON_ERROR_CTRL_CHAR:
            $msg = ' - Unexpected control character found';
            break;
          case JSON_ERROR_SYNTAX:
            $msg = ' - Syntax error, malformed JSON';
            break;
          case JSON_ERROR_UTF8:
            $msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
          default:
            $msg = ' - Unknown error';
            break;
        }
        throw new \Exception('There was an error in the supplied patches file:' . $msg);
      }
      if (isset($patches['patches'])) {
        $patches = $patches['patches'];
        return $patches;
      }
      elseif (!$patches) {
        throw new \Exception('There was an error in the supplied patch file');
      }
    }
    else {
      return [];
    }
  }

}
