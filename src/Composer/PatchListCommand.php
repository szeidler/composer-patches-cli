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

    return 0;
  }

}
