<?php

$componentRoot = $_SERVER['COMPONENT_ROOT'];

if (!is_file($autoload = $componentRoot.'/vendor/autoload.php')) {
    $autoload = $componentRoot.'/../../../../vendor/autoload.php';
}

if (!file_exists($autoload)) {
    exit('You should run "composer install --dev" in the component before running this script.');
}

require_once $autoload;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

$serializer = new Serializer(
    new SerializerComponent\Serializer([new ObjectNormalizer(), new ArrayDenormalizer()], ['json' => new JsonEncoder()])
);

$connection = Connection::fromDsn(getenv('DSN'));
$receiver = new AmqpReceiver($connection, $serializer);
$retryStrategy = new MultiplierRetryStrategy(3, 0);

$worker = new Worker(['the_receiver' => $receiver], new class() implements MessageBusInterface {
    public function dispatch($envelope, array $stamps = []): Envelope
    {
        echo 'Get envelope with message: '.get_class($envelope->getMessage())."\n";
        echo sprintf("with stamps: %s\n", json_encode(array_keys($envelope->all()), JSON_PRETTY_PRINT));

        sleep(30);
        echo "Done.\n";

        return $envelope;
    }
});

echo "Receiving messages...\n";
$worker->run();
