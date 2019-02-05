<?php namespace Tweets;

use Abraham\TwitterOAuth\TwitterOAuth;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends Command
{
    use Helpers;

    protected $config = [];

    /** @var TwitterOAuth $connector */
    protected $connector;

    /** @var InputInterface $input */
    protected $input;

    /** @var OutputInterface $output */
    protected $output;

    /** @var array $skips */
    protected $skips;

    /** @var string $start_id */
    protected $start_id;

    protected function configure()
    {
        $this->setName('tweets:delete')
             ->addOption(
                 'skip',
                 'i',
                 InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                 'An ID of a specific Tweet to Skip'
             )
             ->addOption(
                 'with-likes',
                 'l',
                 InputOption::VALUE_NONE,
                 'Enable the option to also delete likes'
             )
             ->addOption(
                 'start',
                 's',
                 InputOption::VALUE_REQUIRED,
                 'Set the initial offset to start processing in order'
             )
             ->addOption(
                 'all',
                 'a',
                 InputOption::VALUE_NONE,
                 'Delete all possible content'
             );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $filePath = __DIR__ . '/../../config/config.yml';

        $this->checkFileExistence($filePath, $output);

        try {
            $this->config = Yaml::parse(file_get_contents($filePath))['Tweets'];
        } catch (ParseException $e) {
            $output->writeln(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
            exit;
        }
        $this->getAuthenticator();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->skips = $input->getOption('skip');
        $this->start_id = $input->getOption('start');

        $this->delete_tweets();

        if($input->hasOption('with-likes') || $input->hasOption('all')) {
            $this->delete_likes();
        }
    }

    private function delete_tweets()
    {
        $json = json_decode(file_get_contents('tweets.json'));
        $data = array_reverse($json);
        $count = 0;
        $processing = is_null($this->start_id);

        foreach($data as $tweet)
        {
            if(!$processing) {
                if($this->start_id != $tweet->id) {
                    continue;
                }
                $processing = true;
            }

            if(in_array($tweet->id, $this->skips)) {
                $this->output->writeln(sprintf('Skipping ' . $tweet->id));
                continue;
            }

            $result = $this->connector->post('statuses/destroy', ['id' => $tweet->id]);

            if (property_exists($result, 'text')) {
                $this->output->writeln(sprintf(
                    '<comment>[OK]</comment> Deleting: "%s" which was created at: %s',
                    $result->id,
                    $result->created_at
                ));
                $count++;
            } else {
                $this->output->writeln(sprintf(
                    '<error>[ERR]</error> Tweet with the ID: "%s" has <error>NOT</error> been deleted.',
                    $tweet->id
                ));
            }
        }

        $this->output->writeln('Deleted '  . $count . ' Tweets successfully.');
    }

    private function delete_likes()
    {
        $json = json_decode(file_get_contents('likes.json'));
        $data = array_reverse($json);
        $count = 0;

        foreach($data as $like)
        {
            $result = $this->connector->post('favorites/destroy', ['id' => $like->like->tweetId]);

            if (property_exists($result, 'text')) {
                $this->output->writeln(sprintf(
                    '<comment>[OK]</comment> Unlike: "%s" which was created at: %s',
                    $result->id,
                    $result->created_at
                ));
                $count++;
            } else {
                $this->output->writeln(sprintf(
                    '<error>[ERR]</error> Tweet with the ID: "%s" has <error>NOT</error> been unliked.',
                    $like->like->tweetId
                ));
            }
        }

        $this->output->writeln('Unliked '  . $count . ' Tweets successfully.');
    }
}
