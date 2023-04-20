<?php

declare(strict_types=1);

namespace App\Commands;

use App\OpenAI\ClientFactory;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'issue-summary')]
final class IssueSummary extends Command
{

  protected function configure(): void
  {
    $this
      ->addArgument('nid', InputArgument::REQUIRED, 'Issue ID')
      ->addOption('refine', null, InputOption::VALUE_NONE);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $client = ClientFactory::get();
    $httpClient = new Client();

    $output->writeln('Fetching issue data', OutputInterface::VERBOSITY_DEBUG);
    $issueUrl = "https://www.drupal.org/api-d7/node/{$input->getArgument('nid')}.json";
    $issueJson = (string) $httpClient->get($issueUrl)->getBody();
    $issueData = \json_decode($issueJson, false, 512, JSON_THROW_ON_ERROR);

    $prompt = <<<PROMPT
You are a PHP developer working with Drupal. Summarize this reported issue and classify the type of request.

$issueData->title
{$issueData->body->value}

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

    if ($input->getOption('refine')) {
      $result = $client->edits()->create([
        'model' => 'text-davinci-edit-001',
        'input' => $result['choices'][0]['text'],
        'instruction' => 'Fix the grammar and use an active voice.',
        'temperature' => 0.2,
      ]);
      $output->writeln('<info>Refined</info>');
      $output->writeln($result['choices'][0]['text']);
    }

    return Command::SUCCESS;
  }

}
