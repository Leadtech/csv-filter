<?php

namespace Leadtech\Command;


use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class MoveCacheFilesCommand.
 */
class CsvFilterCommand extends Command
{
    const SUCCESS_EXIT_CODE = 0;
    const FAILED_EXIT_CODE = 1;

    /** @var  InputInterface */
    protected $input = null;

    /** @var  OutputInterface */
    protected $output = null;

    /** @var SymfonyStyle */
    protected $io = null;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var string */
    protected $outputDir;

    /** @var string */
    protected $cacheDir;

    /**
     * @param null|string $name
     * @param LoggerInterface $logger
     */
    public function __construct($name, LoggerInterface $logger = null)
    {
        parent::__construct($name);
        $this->logger = $logger;
    }

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setDescription('Move cache files to sub directories.')
            ->addOption('--in', '-i', InputOption::VALUE_REQUIRED, 'Expects absolute path or a filename to store the file in the same folder as the input file.')
            ->addOption('--out', '-o', InputOption::VALUE_REQUIRED, 'Expects absolute path or a filename to store the file in the same folder as the input file.')
            ->addOption('--search', '-s', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Enter a number of search terms, if the string is found than we', [])
            ->addOption('--filter', '-f', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Enter a number of search terms, if the string is found than we', [])
            ->addOption('--with-headers', '-wh', InputOption::VALUE_NONE, 'Whether the first line of the CSV file contains headers.')
            ->addOption('--columns', '-c', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,  'Optionally enter the column numbers for each column that should be evaluated. All columns are evaluated by default.', []);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set input and output
        $this->input = $input;
        $this->output = $output;

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('CSV Filter v1.0');

        // Gets the input file or fail
        if (!($inputFile = $this->getAndVerifyInputFile($input))) {
            return 1;
        }

        // Gets the output file
        $outputFile = $input->getOption('out');
        if (!$outputFile) {
            $this->io->error("Please provide the output filename or absolute path by providing the `--out` argument");

            return 1;
        }

        // Gets the directory part from the non existing output file. If the file does not exist try to create it.
        $pathParts = explode('/', str_replace('\\', '/', $outputFile));
        $filename = array_pop($pathParts);
        if ($pathParts) {
            $dir = implode(DIRECTORY_SEPARATOR, $pathParts);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    $this->io->error("Failed to create output directory '{$dir}'");

                    return 1;
                }
            }
            $outputFile = realpath($dir) . DIRECTORY_SEPARATOR . $filename;
        } else {
            // Only the filename was provided, use the current working directory to generate the absolute path
            $outputFile = getcwd() . DIRECTORY_SEPARATOR . $filename;
        }

        // Make sure the file does not exist
        if (file_exists($outputFile)) {
            $this->io->error("File '{$outputFile}' already exists!");

            return 1;
        }

        // Process CSV file
        if ($this->processFile($inputFile, $outputFile)) {
            $this->io->success("OK! Filtered data saved to file {$outputFile}");

            return 1;
        }

        return 0;
    }

    /**
     * @param string $inputFile
     * @param string $outputFile
     * @return bool
     */
    protected function processFile(string $inputFile, string $outputFile) : bool
    {
        $columnNumbers = $this->input->getOption('columns');

        // Gets the search terms and filters, when the line matches the given term, the line will be added to the output.
        // UNLESS the line matches a filter. All lines that match a filter are always excluded from the output file.
        $searchTerms = $this->input->getOption('search');
        $filterTerms = $this->input->getOption('filter');

        $this->io->text("Processing CSV file...");

        // Create file pointers
        $in = fopen($inputFile, 'r');
        $out = fopen($outputFile, 'w+');

        // Start progress tracking, use memory efficient function to count the lines.
        $this->io->progressStart($this->countLines($inputFile));

        // Read the CSV
        $isFirstLine = true;
        $csvLineCount = 0;
        while ($cols = fgetcsv($in)) {

            $this->io->progressAdvance($csvLineCount++);

            // Deals with possible headers on the first line
            if ($isFirstLine) {
                $isFirstLine = false;
                if ($this->input->hasOption('with-headers')) {
                    fputcsv($out, $cols);
                    continue;
                }
            }

            // If no column numbers are provided, get the column numbers from the actual data.
            if (empty($columnNumbers)) {
                $columnNumbers = array_keys($cols);
            }

            foreach ($columnNumbers as $columnNumber) {
                $columnValue = @$cols[$columnNumber];
                foreach ($filterTerms as $filterTerm) {
                    if (strpos(strtolower($columnValue), strtolower($filterTerm)) !== false) {
                        continue 3;
                    } else {
                        // If the string contains of multiple words try if the value does match the query but in different order.
                        $filterParts = explode(' ', $filterTerm);
                        $isMatch = true;
                        foreach ($filterParts as $filterPart) {
                            if (!strpos(strtolower($columnValue), $filterPart)) {
                                $isMatch = false;
                                break;
                            }
                        }
                        if ($isMatch) {
                            continue 3;
                        }
                    }
                }
                foreach ($searchTerms as $searchTerm) {
                    if (strpos(strtolower($columnValue), strtolower($searchTerm)) !== false) {
                        fputcsv($out, $cols);
                        continue 3;
                    } else {
                        // If the string contains of multiple words try if the value does match the query but in different order.
                        $queryParts = explode(' ', $searchTerm);
                        $isMatch = true;
                        foreach ($queryParts as $queryPart) {
                            if (!strpos(strtolower($columnValue), $queryPart)) {
                                $isMatch = false;
                                break;
                            }
                        }
                        if ($isMatch) {
                            fputcsv($out, $cols);
                            continue 3;
                        }
                    }
                }
            }
        }

        $this->io->progressFinish();

        fclose($in);
        fclose($out);

        return true;
    }

    /**
     * @param InputInterface $input
     * @return string|null
     */
    protected function getAndVerifyInputFile(InputInterface $input) : ?string
    {
        if ($file = $input->getOption('in')) {
            if (file_exists($file)) {
                return realpath($file);
            }
            if (file_exists(getcwd() . DIRECTORY_SEPARATOR . $file)) {
                return realpath(getcwd() . DIRECTORY_SEPARATOR . $file);
            }
            $this->io->error("Could not find input file '{$file}'");

            return null;
        }

        $this->io->error('Please provide the input fill using the `--in` option.');

        return null;
    }

    /**
     * Count lines of possible large CSV file.
     *
     * @param string $filepath
     *
     * @return int
     */
    protected function countLines(string $filepath) : int
    {
        $count = 0;
        $handle = fopen($filepath, "r");
        while(!feof($handle)){
            $line = fgets($handle);
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}