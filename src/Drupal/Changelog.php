<?php

declare(strict_types=1);

namespace App\Drupal;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

final class Changelog
{
  private const CATEGORY_MAP = [
    0 => 'Misc',
    1 => 'Bug',
    2 => 'Task',
    3 => 'Feature',
    4 => 'Support',
    5 => 'Plan',
  ];

  private int $issueCount = 0;

  private array $changes;

  private array $contributors;

  public function __construct(
    private readonly ClientInterface $client,
    private readonly string $project,
    array $commits,
    private readonly string $from,
    private readonly string $to
  ) {
    if (count($commits) === 0) {
      throw new \RuntimeException('No commits for the changelog to process.');
    }
    $contributors = [];
    foreach ($commits as $commit) {
      $contributors[] = CommitParser::extractUsernames($commit->title);
      $nid = CommitParser::getNid($commit->title);
      if ($nid !== null) {
        try {
          $issue = \json_decode(
            (string) $this->client->get("https://www.drupal.org/api-d7/node/$nid.json")
              ->getBody()
          );
          $issueCategory = $issue->field_issue_category ?? 0;
          $issueCategoryLabel = self::CATEGORY_MAP[$issueCategory];
          $this->issueCount++;
        } catch (RequestException $e) {
          $issueCategoryLabel = self::CATEGORY_MAP[0];
        }
      } else {
        $issueCategoryLabel = self::CATEGORY_MAP[0];
      }
      $this->changes[] = [
        'nid' => $nid,
        'link' => $nid !== null ? "https://www.drupal.org/i/$nid" : '',
        'type' => $issueCategoryLabel,
        'summary' => preg_replace('/^(Patch |- |Issue ){0,3}/', '', $commit->title),
        'contributors' => $this->getChangeContributors($commit->title),
      ];
    }
    $this->contributors = array_unique(array_merge(...$contributors));
    sort($this->contributors);
  }

  /**
   * @return array
   */
  public function getContributors(): array
  {
    return $this->contributors;
  }

  /**
   * @return array
   */
  public function getChanges(): array
  {
    return $this->changes;
  }

  /**
   * @return int
   */
  public function getIssueCount(): int
  {
    return $this->issueCount;
  }

  /**
   * @return string
   */
  public function getProject(): string
  {
    return $this->project;
  }

  /**
   * @return string
   */
  public function getFrom(): string
  {
    return $this->from;
  }

  /**
   * @return string
   */
  public function getTo(): string
  {
    return $this->to;
  }

  public static function groupByType(array $changes): array
  {
    $grouped = [];
    foreach ($changes as $change) {
      $grouped[$change['type']][] = $change;
    }
    ksort($grouped);
    return $grouped;
  }

  private function getChangeContributors(string $change): array
  {
    $match = [];
    preg_match('/by ([^:]+):/S', $change, $match);
    if (count($match) !== 2) {
      return [];
    }
    $names = explode(', ', $match[1]);
    sort($names);
    return $names;
  }

}
