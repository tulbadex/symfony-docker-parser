<?php
namespace App\Command;

use App\Service\NewsParserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParseNewsCommand extends Command
{
    protected static $defaultName = 'app:parse-news';
    private $newsParserService;

    public function __construct(NewsParserService $newsParserService)
    {
        parent::__construct();
        $this->newsParserService = $newsParserService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Dispatch news parsing jobs to RabbitMQ.')
            ->setHelp('This command sends parsing jobs to RabbitMQ for execution.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Fetching news from https://highload.today/category/novosti/',
            '============',
            '',
        ]);
        $output->writeln('Starting news parsing...');

        $this->newsParserService->parseNewsUrls();

        $output->writeln('News parsing completed.');

        return Command::SUCCESS;
    }
}
