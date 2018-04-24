# Composer Patches CLI

The Composer Patches CLI provides a simple CLI for [cweagans/composer-patches](https://github.com/cweagans/composer-patches).

## Requirements

* PHP 5.6.0 or greater (PHP 7 recommended)
* Composer

## Installation

Add Composer Patches CLI as a composer dependency.

`composer require szeidler/composer-patches-cli:dev-master`

## Usage

### Patch Enable

The patch enable function enables the patching functionality in your root composer.json and creates a patch file if not existing.

The patch enable command accepts the following options.

* `--file` Filename of the composer patch file to be created.

```sh
composer patch-enable  
```

### Patch Add

The patch add command accepts the following arguments.

* `--package` Name of the package to patch.
* `--description` Description of the patch to be used.
* `--url` URL of the patch file.

```sh
composer patch-add drupal/core "SA-CORE-2018-002" "https://cgit.drupalcode.org/drupal/rawdiff/?h=8.5.x&id=5ac8738fa69df34a0635f0907d661b509ff9a28f"  
```

## Credits

Stephan Zeidler for [Ramsalt Lab AS](https://ramsalt.com)

## License

The MIT License (MIT)
