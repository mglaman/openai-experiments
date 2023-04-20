<?php

declare(strict_types=1);

namespace App\OpenAI;

use OpenAI;
use OpenAI\Client;

final class ClientFactory
{
  public static function get(): Client
  {
    return OpenAI::client($_ENV['OPENAI_KEY']);
  }
}
