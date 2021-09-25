<?php

namespace Bwen\Clover2LcovBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConvertCommand extends Command
{
    protected static $defaultName = 'convert:clover2lcov';

    protected function configure()
    {
        $this
            ->setDescription('Converts a clover file to a lcov file')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to clover source file to be converted')
            ->addArgument('target', InputArgument::OPTIONAL, 'Path to the lcov target file to be generated. Default: ./lcov')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $source = realpath($input->getArgument('source'));
        if (!$source) {
            $io->error('Please specify a valid source path to the clover file to be converted');
        }

        $target = $input->getArgument('target');
        if (!$target) {
            $target = 'lcov';
        }

        $lines = [];
        $cloverXML = simplexml_load_file($source);
        foreach ($cloverXML->project->file as $fileInfo) {
            $lines[] = 'TN:';
            $lines[] = 'SF:' . $fileInfo['name'];
            $lines[] = 'FNF:' . $fileInfo->metrics['methods'];
            $lines[] = 'FNH:' . $fileInfo->metrics['coveredmethods'];

            foreach ($fileInfo->line as $line) {
                $lineNumber = (int)$line['num'];
                $numberExecution = (int)$line['count'];

                if (isset($line['type']) && $line['type'] == 'method') {
                    $functionName = (string)$line['name'];

                    $lines[] = "FN:$lineNumber,$functionName";
                    $lines[] = "FNDA:$numberExecution,$functionName";
                } else {
                    $lines[] = "DA:$lineNumber,$numberExecution";
                }
            }

            $lines[] = 'LF:' . $fileInfo->metrics['statements'];
            $lines[] = 'LH:' . $fileInfo->metrics['coveredstatements'];
            $lines[] = "end_of_record";
        }

        file_put_contents($target, implode("\n", $lines));
        return ExitCode::SUCCESS;
    }
}
