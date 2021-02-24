<?php

namespace SEOCommerce\ThemeBase\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ThemeCommand extends Command
{
    /**
     * Scan for possible themes
     *
     * @return array
     */
    protected function getPossibleThemes(): array
    {
        $workingPath = getcwd();
        $vendorPath = $workingPath.'/vendor';
        $possibleThemes = [];
        foreach (scandir($vendorPath) as $provider) {
            $providerPath = $vendorPath.'/'.$provider;
            if (in_array($provider, ['.', '..']) or !is_dir($providerPath)) {
                continue;
            }

            foreach (scandir($providerPath) as $theme) {
                $themePath = $providerPath.'/'.$theme;
                if (in_array($theme, ['.', '..']) or !is_dir($themePath)) {
                    continue;
                }

                // Detect theme
                if (file_exists($themePath.'/views/home.blade.php')) {
                    $possibleThemes[] = $provider.'/'.$theme;
                }
            }
        }

        return $possibleThemes;
    }

    /**
     * Ask to select a theme
     *
     * @param array $possibleThemes
     * @return string
     */
    protected function selectTheme(array $possibleThemes, InputInterface $input, OutputInterface $output): string
    {
        if (!count($possibleThemes)) {
            throw new \Exception('No theme found.');
        }

        $questionHelper = $this->getHelper('question');
        $selectedTheme = null;
        if (count($possibleThemes) === 1) {

            // Confirm theme
            $question = new ConfirmationQuestion('<info>Theme "'.$possibleThemes[0].'" found. Continue to install this theme? (y)</>', true);

            // Stop install
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Installation is cancelled</>');
                return Command::SUCCESS;
            }

            // Set theme to install
            $selectedTheme = $possibleThemes[0];

        } else {

            // Ask to choose theme
            $output->writeln('<info>Please select a theme: </>');
            $output->writeln('-----------------------------');

            // Show options
            foreach ($possibleThemes as $key => $possibleTheme) {
                $output->writeln('['.$key.'] '.$possibleTheme);
            }
            $output->writeln('-----------------------------');

            // Ask
            $question = new Question('Please enter the theme to install [0 to '.(count($possibleThemes) - 1).']');
            $themeKey = $questionHelper->ask($input, $output, $question);

            if (!isset($possibleThemes[$themeKey])) {
                throw new \Exception('No theme selected. Installation is cancelled.');
            }

            $selectedTheme = $possibleThemes[$themeKey];
        }

        return $selectedTheme;
    }
}
