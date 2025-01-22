<?php
namespace App\Command;

use App\Service\NewsParserService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumeMessagesCommand extends Command
{
    private $newsParserService;

    public function __construct(NewsParserService $newsParserService)
    {
        parent::__construct();
        $this->newsParserService = $newsParserService;
    }

    protected function configure()
    {
        $this
            ->setName('app:consume-messages')
            ->setDescription('Consumes messages from RabbitMQ');
    }

    /* protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('news_parser', false, true, false, false);

        $callback = function ($msg) use ($output) {
            $data = json_decode($msg->body, true);
            if ($data['type'] === 'parse_article') {
                $this->newsParserService->parseArticle($data['data']['url']);
                $output->writeln("Parsed article: " . $data['data']['url']);
            }
            $msg->ack();
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume('news_parser', '', false, false, false, false, $callback);

        // Updated while loop
        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return Command::SUCCESS;
    } */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('news_parser', false, true, false, false);

        $callback = function ($msg) use ($output) {
            $data = json_decode($msg->body, true);
            if ($data['type'] === 'parse_article') {
                $this->newsParserService->parseArticle($data['data']['url']);
                $output->writeln("Parsed article: " . $data['data']['url']);
            }
            $msg->ack();
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume('news_parser', '', false, false, false, false, $callback);

        $output->writeln("Waiting for messages. To exit press CTRL+C");

        // Updated while loop
        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return Command::SUCCESS;
    }
}