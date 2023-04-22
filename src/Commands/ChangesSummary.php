<?php

declare(strict_types=1);

namespace App\Commands;

use App\Drupal\Changelog;
use App\Drupal\GitLab;
use App\OpenAI\ClientFactory;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'changes-summary')]
final class ChangesSummary extends Command
{

  protected function configure(): void
  {
    $this
      ->addArgument('project', InputArgument::REQUIRED, 'Project machine name')
      ->addArgument('from', InputArgument::REQUIRED, 'Branch/tag from')
      ->addArgument('to', InputArgument::OPTIONAL, 'Branch/tag to', 'HEAD');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $client = ClientFactory::get();
    $httpClient = new Client();

    try {
      $compare = (new GitLab($httpClient))->compare(
        $input->getArgument('project'),
        $input->getArgument('from'),
        $input->getArgument('to'),
      );
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $output->writeln($e->getMessage());
      return Command::FAILURE;
    }

    $commits = $compare->commits;

    $changelog = new Changelog(
      $httpClient,
      $input->getArgument('project'),
      $commits,
      $input->getArgument('from'),
      $input->getArgument('to'),
    );
    $processedChanges = Changelog::groupByType($changelog->getChanges());



    $contributors = implode(', ', $changelog->getContributors());
    $changesFromTo = sprintf(
      'Changes from %s to %s',
      $changelog->getFrom(),
      $changelog->getTo()
    );
    $changes = implode(
      PHP_EOL,
      array_map(
        static fn (array $change) => $change['summary'],
        $changelog->getChanges()
      )
    );

    $output->writeln($changes);
    $output->writeln('');

    $prompt = <<<PROMPT
 Summarize the following changes to a Drupal module from {$changelog->getFrom()} to {$changelog->getTo()} as release notes.

$changes

PROMPT;

    $result = $client->completions()->create([
      'model' => 'text-davinci-003',
      'prompt' => $prompt,
      'temperature' => 0.5,
      'max_tokens' => 150,
      'top_p' => 1,
      'frequency_penalty' => 0,
      'presence_penalty' => 0,
      'stop' => ["\"\"\""],
    ]);
    $output->writeln('<info>Result</info>');
    $output->writeln($result['choices'][0]['text']);

    return Command::SUCCESS;
  }

}
