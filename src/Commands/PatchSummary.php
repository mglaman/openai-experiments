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

#[AsCommand(name: 'patch-summary')]
final class PatchSummary extends Command
{

  protected function configure(): void
  {
    $this
      ->addArgument('diffUrl', InputArgument::REQUIRED, 'URL to diff')
      ->addOption('refine', null, InputOption::VALUE_NONE);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $client = ClientFactory::get();
    $httpClient = new Client();

    $output->writeln('Fetching diff', OutputInterface::VERBOSITY_DEBUG);
    $patch = (string)$httpClient->get($input->getArgument('diffUrl'))->getBody(
    );

    $prompt = <<<PROMPT
You are a PHP developer working with Drupal. You are reviewing a pull request submitted to fix a reported issue. 
Summarize what the patch is modifying and how it will change the module's code to resolve the issue.

$patch
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
