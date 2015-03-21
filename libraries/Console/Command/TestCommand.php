<?php
/**
 * Seabreeze
 * Copyright (C) 2014 IceFlame.net
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE
 * FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY
 * DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER
 * IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING
 * OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 * @package  FlameCore\Seabreeze
 * @version  0.1-dev
 * @link     http://www.flamecore.org
 * @license  ISC License <http://opensource.org/licenses/ISC>
 */

namespace FlameCore\Seabreeze\Console\Command;

use FlameCore\Seabreeze\Manifest\Project;
use FlameCore\Seabreeze\Runner\TestsRunner;
use FlameCore\Seabreeze\EventObserver\TestsObserver;
use FlameCore\Seabreeze\EventObserver\Provider\ConsoleProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "test" Console Command
 *
 * @author   Christian Neff <christian.neff@gmail.com>
 */
class TestCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('test')
             ->setDescription('Tests the project')
             ->addArgument('environment', InputArgument::REQUIRED, 'The test environment');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $directory = $this->getApplication()->getWorkingDir();
            $project = Project::fromDirectory($directory);
        } catch (\Exception $e) {
            throw new \DomainException('Missing or unreadable manifest file in working directory');
        }

        $name = $input->getArgument('environment');

        if (!$project->hasEnvironment($name)) {
            throw new \DomainException(sprintf('Deployment environment "%s" does not exist', $name));
        }

        $output->writeln('Starting testing process.');

        $environment = $project->getEnvironment($name);
        $observer = $this->initObserver($input, $output);

        $runner = new TestsRunner();
        $runner->observe($observer);
        $runner->run($environment);

        $output->writeln(sprintf('Tests finished %s.', !$runner->isSuccessful() ? 'with failures' : 'successfully'));

        return $runner->isSuccessful() ? 0 : 1;
    }

    protected function initObserver(InputInterface $input, OutputInterface $output)
    {
        $format = '- Running ... %current% / %max%';

        if ($output->isVerbose()) {
            $format .= ' (%elapsed:7s% elapsed)';
        }

        $options = [
            'process' => [
                'start.message'     => '- Running <info>%test%</info>...',
                'finish.message'    => '   Completed: %success%',
                'transform.success' => [
                    true  => 'Passed',
                    false => '<error>Failed</error>'
                ]
            ],
            'progress' => [
                'format' => $format
            ]
        ];

        $provider = new ConsoleProvider($output, $options);
        return new TestsObserver($provider);
    }
}