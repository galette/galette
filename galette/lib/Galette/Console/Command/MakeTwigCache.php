<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Make Twig cache
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:twig-cache',
    description: 'Compile Twig templates in cache'
)]
class MakeTwigCache extends AbstractCommand
{
    /**
     * Command execution
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = sprintf('%s/../../../../templates/default', __DIR__);
        $cache_dir = sprintf('%s/../../../../../tempcache', __DIR__);

        if (file_exists($cache_dir)) {
            $this->rmdirRecursive($cache_dir);
        }
        mkdir($cache_dir);

        $loader = new FilesystemLoader($directory, dirname($directory));
        $twig = $this->getMockedTwigEnvironment($loader);
        $twig->setCache($this->getTwigCacheHandler($cache_dir));

        $files = $this->getTemplatesFiles($directory);

        $progress_bar = new ProgressBar($output);
        foreach ($progress_bar->iterate($files) as $file) {
            $twig->load($file);
        }

        $output->writeln(''); // New to next line after progress bar display

        return Command::SUCCESS;
    }

    /**
     * Remove a directory and its content recursively.
     *
     * @param string $path Path to remove
     *
     * @return bool
     */
    private function rmdirRecursive(string $path): bool
    {
        if (!empty($path) && is_dir($path)) {
            $dir = new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS
            );
            $files = new RecursiveIteratorIterator(
                $dir,
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fpath) {
                ($fpath->isDir() && !$fpath->isLink()) ? rmdir($fpath->getPathname()) : unlink($fpath->getPathname());
            }

            rmdir($path);
            return true;
        }
        return false;
    }

    /**
     * Return template files.
     *
     * @param string $directory Directory to scan
     *
     * @return string[]
     */
    private function getTemplatesFiles(string $directory): array
    {
        $directory = realpath($directory);

        if (!is_dir($directory) || !is_readable($directory)) {
            throw new InvalidOptionException(
                sprintf('Unable to read directory "%s"', $directory)
            );
        }

        $dir_iterator = new RecursiveDirectoryIterator($directory);
        $recursive_iterator = new RecursiveIteratorIterator(
            $dir_iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        $files = [];
        /** @var SplFileInfo $file */
        foreach ($recursive_iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = preg_replace(
                '/^' . preg_quote($directory . DIRECTORY_SEPARATOR, '/') . '/',
                '',
                $file->getRealPath()
            );
        }

        return $files;
    }

    /**
     * Return a mocked Twig environment, with custom functions, filters and tests.
     *
     * @param LoaderInterface $loader Twig loader
     *
     * @return Environment
     */
    private function getMockedTwigEnvironment(LoaderInterface $loader): Environment
    {
        return new class ($loader) extends Environment {
            /**
             * Custom functions
             *
             * @param string $name Function name
             *
             * @return TwigFunction
             */
            public function getFunction(string $name): TwigFunction
            {
                $translation_functions = [
                    '__',
                    '_T',
                    '_Tn',
                    '_Tx',
                    '_Tnx',
                ];
                if (in_array($name, $translation_functions, true)) {
                    // Return a function that has its own name as callback
                    // for translation functions, so Twig will generate code following this pattern:
                    // $name($parameter, ...)`, e.g. `_T('str')` or `_Tn('str', 'strs', 5)`.
                    return new TwigFunction($name, $name);
                }
                return parent::getFunction($name) ?? new TwigFunction($name, function (): void {
                });
            }

            /**
             * Custom filters
             *
             * @param string $name Filter name
             *
             * @return TwigFilter
             */
            public function getFilter(string $name): TwigFilter
            {
                return parent::getFilter($name) ?? new TwigFilter($name, function (): void {
                });
            }

            /**
             * Custom tests
             *
             * @param string $name Test name
             *
             * @return ?TwigTest
             */
            public function getTest(string $name): ?TwigTest
            {
                if (in_array($name, ['divisible', 'same'])) {
                    // `same as` and `divisible by` will be search in 2 times.
                    // First check will be done on first word, should return `null` to
                    // trigger second search that will be done on full name.
                    return null;
                }
                return parent::getTest($name) ?? new TwigTest($name, function (): void {
                });
            }
        };
    }

    /**
     * Return a custom Twig cache handler.
     * This handler is useful to be able to preserve filenames of compiled files.
     *
     * @param string $directory Directory to store cache
     *
     * @return CacheInterface
     */
    private function getTwigCacheHandler(string $directory): CacheInterface
    {
        return new class ($directory) extends FilesystemCache {
            private string $directory;

            /**
             * Default constructor
             *
             * @param string $directory Directory to store cache
             * @param int    $options   Options
             */
            public function __construct(string $directory, int $options = 0)
            {
                $this->directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                parent::__construct($directory, $options);
            }

            /**
             * Generate a cache key for the given template class name.
             *
             * @param string $name      The template name
             * @param string $className The template class name
             *
             * @return string
             */
            public function generateKey(string $name, string $className): string
            {
                return $this->directory . $name;
            }
        };
    }
}
