<?php

namespace SEOCommerce\ThemeBase\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

class Install extends ThemeCommand
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'install';

    protected function configure()
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {

            $questionHelper = $this->getHelper('question');
            $filesystem = new Filesystem;

            // Scan possible themes
            $workingPath = getcwd();
            $vendorPath = $workingPath.'/vendor';
            $possibleThemes = $this->getPossibleThemes();

            // Select theme
            $themeToInstall = $this->selectTheme($possibleThemes, $input, $output);

            // Ask to override public file or not
            $overridePublicFiles = $questionHelper->ask($input, $output, new ConfirmationQuestion('Override public files if existing? (n)', false));

            // Start to install
            $output->writeln('<info>Installing theme '.$themeToInstall.'</>');

            // Copy base view files
            $installViewPath = $workingPath.'/views/_base';
            if (is_dir($installViewPath)) {
                $filesystem->remove($installViewPath);
            }
            mkdir($installViewPath, 0777);
            $filesystem->mirror($vendorPath.'/'.$themeToInstall.'/views', $installViewPath);
            $output->writeln('<info>Base view files copied to: '.$installViewPath.'</>');

            // Generate proxy view files
            $baseViewIterator = new \RecursiveDirectoryIterator($installViewPath);
            foreach(new \RecursiveIteratorIterator($baseViewIterator) as $file) {

                // Skip if not .php
                if (!preg_match('/\.php$/i', (string) $file)) {
                    continue;
                }

                $relativePath = str_replace($installViewPath.'/', '', $file);
                $proxyViewPath = $workingPath.'/views/'.$relativePath;

                // Generate if not exists
                if (!file_exists($proxyViewPath)) {
                    $proxyDirPath = explode('/', $proxyViewPath);
                    unset($proxyDirPath[count($proxyDirPath) - 1]);
                    $proxyDirPath = implode('/', $proxyDirPath);
                    if (!is_dir($proxyDirPath)) {
                        mkdir($proxyDirPath, 0777, true);
                    }

                    $relativeBladePath = str_replace('/', '.', $relativePath);
                    $relativeBladePath = preg_replace('/\.php$/i', '', $relativeBladePath);
                    $relativeBladePath = preg_replace('/\.blade$/i', '', $relativeBladePath);
                    $filesystem->touch($proxyViewPath);
                    $filesystem->dumpFile($proxyViewPath, '@include(config(\'app.theme\').\'::_base.'.$relativeBladePath.'\')');
                }
            }

            $output->writeln('<info>View files are generated.</>');

            // Copy public files
            $installPublicPath = $workingPath.'/public';
            if (!is_dir($installPublicPath)) {
                mkdir($installPublicPath, 0777, true);
            }
            $filesystem->mirror($vendorPath.'/'.$themeToInstall.'/public', $installPublicPath, null, [
                'override' => $overridePublicFiles
            ]);
            $output->writeln('<info>Public files are copied to "'.$installPublicPath.'".</>');

        } catch (\Exception $e) {
            $output->writeln('<error>Failed to install theme. '.$e->getMessage().'</>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Theme installed successfully!</>');
        return Command::SUCCESS;
    }
}
