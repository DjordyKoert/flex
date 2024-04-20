<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Configurator;

use Symfony\Flex\Lock;
use Symfony\Flex\Recipe;
use Symfony\Flex\Update\RecipeUpdate;

class ImportMapConfigurator extends AbstractConfigurator
{
    private const FILE = '/importmap.php';

    public function configure(Recipe $recipe, $config, Lock $lock, array $options = [])
    {
        $this->write('Adding importmap entries');
        $importMapContent = $this->configureImportMap($config);

        file_put_contents($this->options->get('root-dir').self::FILE, $this->buildContents($importMapContent));
    }

    public function unconfigure(Recipe $recipe, $config, Lock $lock)
    {
        $file = $this->options->get('root-dir').self::FILE;
        if (!file_exists($file)) {
            return;
        }

        $existingImportMap = $this->load($file);
        foreach (array_keys($config) as $key) {
            unset($existingImportMap[$key]);
        }

        file_put_contents($this->options->get('root-dir').self::FILE, $this->buildContents($existingImportMap));
    }

    public function update(RecipeUpdate $recipeUpdate, array $originalConfig, array $newConfig): void
    {
        $removedPackages = array_diff(array_keys($originalConfig), array_keys($newConfig));

        $originalImportMap = $this->configureImportMap($originalConfig, true);
        $recipeUpdate->setOriginalFile(
            $this->options->get('root-dir').self::FILE,
            $this->buildContents($originalImportMap)
        );

        // Remove the packages that are not in the new config
        $newImportMap = $this->configureImportMap($newConfig, true);
        foreach ($removedPackages as $package) {
            unset($newImportMap[$package]);
        }

        $recipeUpdate->setNewFile(
            $this->options->get('root-dir').self::FILE,
            $this->buildContents($newImportMap)
        );
    }

    private function load(string $file): array
    {
        $importMap = file_exists($file) ? (require $file) : [];
        if (!\is_array($importMap)) {
            $importMap = [];
        }

        return $importMap;
    }

    private function configureImportMap(array $config, bool $resetPackage = false): array
    {
        $file = $this->options->get('root-dir').self::FILE;
        $importMap = $this->load($file);

        foreach ($config as $package => $packageOptions) {
            if (!isset($importMap[$package]) || $resetPackage) {
                $importMap[$package] = $packageOptions;
            }

        }

        return $importMap;
    }

    private function buildContents(array $importMap): string
    {
        $contents = "<?php\n\nreturn [\n";
        foreach ($importMap as $key => $options) {
            $contents .= "    '$key' => [\n";
            foreach ($options as $optionKey => $value) {
                $booleanValue = var_export($value, true);
                $contents .= "        '$optionKey' => $booleanValue,\n";
            }
            $contents .= "    ],\n";
        }
        $contents .= "];\n";

        return $contents;
    }

}
