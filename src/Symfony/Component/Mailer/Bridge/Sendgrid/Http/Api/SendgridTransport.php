<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Http\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mailer\Transport\Http\Api\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Kevin Verschaeve
 *
 * @experimental in 4.3
 */
class SendgridTransport extends AbstractApiTransport
{
    private const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    private $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    protected function doSendEmail(Email $email, SmtpEnvelope $envelope): void
    {
        $response = $this->client->request('POST', self::ENDPOINT, [
            'json' => $this->getPayload($email, $envelope),
            'auth_bearer' => $this->key,
        ]);

        if (202 !== $response->getStatusCode()) {
            $errors = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send an email: %s (code %s).', implode('; ', array_column($errors['errors'], 'message')), $response->getStatusCode()));
        }
    }

    private function getPayload(Email $email, SmtpEnvelope $envelope): array
    {
        $addressStringifier = function (Address $address) {return ['email' => $address->toString()]; };

        $payload = [
            'personalizations' => [],
            'from' => ['email' => $envelope->getSender()->toString()],
            'content' => $this->getContent($email),
        ];

        if ($email->getAttachments()) {
            $payload['attachments'] = $this->getAttachments($email);
        }

        $personalization = [
            'to' => array_map($addressStringifier, $this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];
        if ($emails = array_map($addressStringifier, $email->getCc())) {
            $personalization['cc'] = $emails;
        }
        if ($emails = array_map($addressStringifier, $email->getBcc())) {
            $personalization['bcc'] = $emails;
        }

        $payload['personalizations'][] = $personalization;

        // these headers can't be overwritten according to Sendgrid docs
        // see https://developers.pepipost.com/migration-api/new-subpage/email-send
        $headersToBypass = ['x-sg-id', 'x-sg-eid', 'received', 'dkim-signature', 'content-transfer-encoding', 'from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'reply-to'];
        foreach ($email->getHeaders()->getAll() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            $payload['headers'][$name] = $header->toString();
        }

        return $payload;
    }

    private function getContent(Email $email): array
    {
        $content = [];
        if (null !== $text = $email->getTextBody()) {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }
        if (null !== $html = $email->getHtmlBody()) {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        return $content;
    }

    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'content' => $attachment->bodyToString(),
                'type' => $headers->get('Content-Type')->getBody(),
                'filename' => $filename,
                'disposition' => $disposition,
            ];

            if ('inline' === $disposition) {
                $att['content_id'] = $filename;
            }

            $attachments[] = $att;
        }

        return $attachments;
    }
}
