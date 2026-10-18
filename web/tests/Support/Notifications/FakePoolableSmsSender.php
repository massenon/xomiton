<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Domain\Notifications\Contracts\PoolableSmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;

/**
 * Faux pilote SMS HTTP « poolable » : requête vers https://sms.test/send ; 2xx → sent, autre → failed.
 */
class FakePoolableSmsSender extends FakeSmsSender implements PoolableSmsSender
{
    public function describeRequest(string $to, string $text): ?HttpRequestSpec
    {
        return new HttpRequestSpec('POST', 'https://sms.test/send', json: ['to' => $to, 'text' => $text]);
    }

    public function interpretResponse(int $status, string $body): DeliveryResult
    {
        return $status >= 200 && $status < 300 ? DeliveryResult::sent() : DeliveryResult::failed('HTTP '.$status);
    }

    public function interpretTransportError(string $error): DeliveryResult
    {
        return DeliveryResult::failed($error);
    }
}
