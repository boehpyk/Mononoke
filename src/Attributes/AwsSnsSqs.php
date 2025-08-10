<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Exceptions\InvalidAttributeConfigurationException;

/**
 * Attribute to configure AWS SNS and SQS integration.
 *
 * When applied to a method, this attribute ensures that the specified SNS topic
 * and SQS queue are created (if they do not already exist) and that the queue
 * is subscribed to the topic. If registered with Mononoke, an event loop will be
 * initialized via ReactPHP to continuously poll messages from the SQS queue.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AwsSnsSqs
{
    public function __construct(public string $topicName, public string $queueName)
    {
        $this->validate();
    }

    private function validate(): void
    {
        if (! preg_match("/^[A-Za-z0-9-_]+/", $this->topicName)) {
            throw new InvalidAttributeConfigurationException("Topic Name may only include alphanumeric characters, dashes and hyphens (given: \"$this->topicName\"");
        }

        if (! preg_match("/^[A-Za-z0-9-_]+/", $this->queueName)) {
            throw new InvalidAttributeConfigurationException("Queue Name may only include alphanumeric characters, dashes and hyphens (given: \"$this->queueName\"");
        }
    }
}
