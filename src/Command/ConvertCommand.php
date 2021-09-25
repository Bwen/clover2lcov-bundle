<?php

namespace Bwen\Clover2LcovBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ConvertCommand extends Command
{
    protected static $defaultName = 'convert:clover2lcov';

    public function __construct(private Filesystem $filesystem)
    {
        parent::__construct();
    }

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
            $target = './lcov.info';
        }

        if (!$this->filesystem->exists($target)) {
            $pathInfo = pathinfo($target);
            $this->filesystem->mkdir($pathInfo['dirname']);
        }
        file_put_contents($target, "");

        $cloverXML = simplexml_load_file($source);
        foreach ($cloverXML->project->file as $fileInfo) {
            $this->filesystem->appendToFile($target, "TN:\n");
            $this->filesystem->appendToFile($target, "SF:{$fileInfo['name']}\n");
            $this->filesystem->appendToFile($target, "FNF:{$fileInfo->metrics['methods']}\n");
            $this->filesystem->appendToFile($target, "FNH:{$fileInfo->metrics['coveredmethods']}\n");

            foreach ($fileInfo->line as $line) {
                $lineNumber = (int)$line['num'];
                $numberExecution = (int)$line['count'];

                if (isset($line['type']) && $line['type'] == 'method') {
                    $functionName = (string)$line['name'];

                    $this->filesystem->appendToFile($target, "FN:$lineNumber,$functionName\n");
                    $this->filesystem->appendToFile($target, "FNDA:$numberExecution,$functionName\n");
                } else {
                    $this->filesystem->appendToFile($target, "DA:$lineNumber,$numberExecution\n");
                }
            }

            $this->filesystem->appendToFile($target, "LF:{$fileInfo->metrics['statements']}\n");
            $this->filesystem->appendToFile($target, "LH:{$fileInfo->metrics['coveredstatements']}\n");
            $this->filesystem->appendToFile($target, "end_of_record\n");
        }

        $io->success("Lcov file created successfully at: $target");
        return ExitCode::SUCCESS;
    }
}
