# Composer Patches CLI

The Composer Patches CLI provides a simple CLI for [cweagans/composer-patches](https://github.com/cweagans/composer-patches).

## Requirements

* PHP 7.0 or greater
* Composer

## Installation

Add Composer Patches CLI as a composer dependency.

`composer global require szeidler/composer-patches-cli:dev-master`

## Usage

### Patch Enable

The patch enable function enables the patching functionality in your root composer.json and creates a patch file if not existing.

The patch enable command accepts the following options.

* `--file` Filename of the composer patch file to be created (default: composer.patches.json')

Example: 

```sh
composer patch-enable --file='patches.json'  
```

### Patch Add

```sh
composer patch-add <package> <description> <url> 
```

The patch add command accepts the following arguments in the defined order.

1. `<package>` Name of the package to patch.
2. `<description>` Description of the patch to be used.
3. `<url>` URL or local path of the patch file.

Example:

```sh
composer patch-add drupal/core "SA-CORE-2018-002" "https://cgit.drupalcode.org/drupal/rawdiff/?h=8.5.x&id=5ac8738fa69df34a0635f0907d661b509ff9a28f"
```

The patch add command accepts the following options.

* `--no-update` Use this option to prevent composer to update the package and apply the patch. The patch will only end
up in your `composer.json`, not `composer.lock` file.

* `--no-dev` Run the dependency update with the --no-dev option.

You can omit arguments for an interactive mode.

### Patch Remove

```sh
composer patch-remove <package> <description>
```

The patch remove command accepts the following arguments in the defined order.

1. `<package>` Name of the package from which you want to remove the patch.
2. `<description>` Description of the patch to be removed.

Example:

```sh
composer patch-remove drupal/core "SA-CORE-2018-002"
```

You can omit arguments for an interactive mode.

### Patch List

```sh
composer patch-list <package>
```

The patch add command accepts the following arguments.

1. `<package>` (optional) Name of the package to patch.

If the package argument is omitted, the command will return all defined patches.


Example:

```sh
$ composer patch-list            

Package: drupal/core
+-----------------------------------------+-------------------------------------------------------------------------------------------------+
| Description                             | URL                                                                                             |
+-----------------------------------------+-------------------------------------------------------------------------------------------------+
| Simple decimals fail to pass validation | https://www.drupal.org/files/issues/2018-04-23/drupal_2230909_113.patch                         |
| SA-CORE-2018-002                        | https://cgit.drupalcode.org/drupal/rawdiff/?h=8.5.x&id=5ac8738fa69df34a0635f0907d661b509ff9a28f |
+-----------------------------------------+-------------------------------------------------------------------------------------------------+
```

## Credits

Stephan Zeidler for [Ramsalt Lab AS](https://ramsalt.com)

## License

The MIT License (MIT)
