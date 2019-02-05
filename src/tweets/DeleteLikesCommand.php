<?php namespace Tweets;

use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteLikesCommand extends Command
{
    use Helpers;

    protected $config = [];

    /** @var TwitterOAuth $connector */
    protected $connector;

    /** @var InputInterface $input */
    protected $input;

    /** @var OutputInterface $output */
    protected $output;

    protected function configure()
    {
        $this->setName('tweets:delete-likes');
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

        $this->delete_likes();
    }

    private function delete_likes()
    {
        $json = json_decode(file_get_contents('likes.json'));
        $data = array_reverse($json);
        //$data = $json;
        $count = 0;

        foreach($data as $like)
        {
            $this->output->writeln('Processing ' . $like->like->tweetId);
            //sleep(1);
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
                $this->output->writeln($result->errors[0]->message);
            }
        }

        $this->output->writeln('Unliked '  . $count . ' Tweets successfully.');
    }
}
