<?php

namespace Remp\MailerModule\Tracker;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Ramsey\Uuid\Uuid;
use Tracy\Debugger;
use Tracy\Logger;

class Remp implements ITracker
{
    const TRACK_EVENT = '/track/event';

    const TRACK_COMMERCE = '/track/commerce';

    private $client;

    private $token;

    public function __construct(string $trackerHost, string $token)
    {
        $this->token = $token;
        $this->client = new Client([
            'base_uri' => $trackerHost,
        ]);
    }

    public function trackEvent(DateTime $dateTime, string $category, string $action, EventOptions $options)
    {
        $payload = array_filter([
            'system' => [
                'property_token' => $this->token,
                'time' => $dateTime->format(DATE_RFC3339),
            ],
            'user' => $options->getUser()->toArray(),
            'category' => $category,
            'action' => $action,
            'fields' => $options->getFields(),
            'value' => $options->getValue(),
            'remp_event_id' => Uuid::uuid4(),
        ]);

        try {
            $this->client->post(self::TRACK_EVENT, [
                'json' => $payload,
            ]);
        } catch (ClientException $e) {
            Debugger::log($e->getResponse()->getBody()->getContents(), Logger::ERROR);
        }
    }
}
