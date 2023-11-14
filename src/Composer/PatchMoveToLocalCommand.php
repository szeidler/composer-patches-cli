<?php

namespace szeidler\ComposerPatchesCLI\Composer;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Util\HttpDownloader;

class PatchMoveToLocalCommand extends PatchBaseCommand {

  protected function configure() {
    $this->setName('patch-move-to-local')
      ->setDescription('Moves all remote patches to local.')
      ->setDefinition([
        new InputArgument('directory', InputArgument::REQUIRED, 'The directory where the patches should be moved to.')
      ]);

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $extra = $this->getComposer()->getPackage()->getExtra();

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

    $downloader = new HttpDownloader($this->getIO(), $this->getComposer()->getConfig());

    // Grab the defined patches.
    $patches = $this->grabPatches();

    $file = new JsonFile($manipulator_filename);
    $manipulator = new JsonManipulator(file_get_contents($file->getPath()));
    $modified = FALSE;

    foreach ($patches as $package => $packagePatches) {
      foreach ($packagePatches as $description => $url) {
        // Only move remote patches.
        if (filter_var($url, FILTER_VALIDATE_URL)) {
          // Create the patch directory if not existing.
          $directory = $input->getArgument('directory');
          if (!is_dir($directory)) {
            mkdir($directory);
          }

          // Move the patch file.
          $filename = basename($url);

          // A Gitlab merge request file name 1.diff is meaning less.
          // Therefore, prefix it with the description.
          if (str_contains($url, '/-/merge_requests/')) {
            $clean_description = str_replace(' ', '-', $description);
            $clean_description = strtolower($clean_description);
            $clean_description = preg_replace('/[^A-Za-z0-9\-]/', '', $clean_description);
            $filename = $clean_description . '-' . $filename;
          }

          // Download the remote patch and place it to the destination folder.
          $source = $url;
          $destination = $directory . '/' . $filename;
          if ($downloader->copy($source, $destination)) {
            $modified = TRUE;
            $patches[$package][$description] = $destination;
            if ($this->getIO()->isVerbose()) {
              $output->writeln('Moved patch: ' . $description . ' | ' . $url . ' to ' . $destination);
            }
          }
          else {
            throw new \Exception('Patch file could not be moved.');
          }
        }
      }
    }

    if (!$modified) {
      $this->getIO()->write('<info>No patches modified.</info>');
      return 0;
    }

    // Replace the remote URL with the local path.
    // Merge in the updated packages into the JSON.
    if (is_null($json_node)) {
      $manipulator->removeMainKey('patches');
      $manipulator->addMainKey('patches', $patches);
    }
    else {
      $manipulator->addSubNode($json_node, $json_name, $patches);
    }

    if (file_put_contents($manipulator_filename, $manipulator->getContents())) {
      $this->getIO()->write('<info>Remote Composer patches got successfully moved to local files and got updated in the composer.json or composer.patches.json.</info>');
    }
    else {
      throw new \Exception('Composer patches file could not be saved. Please check the permissions.');
    }

    return 0;
  }
}
