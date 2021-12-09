<?php
	require_once(__DIR__ . '/functions.php');

	use PhpAmqpLib\Connection\AMQPStreamConnection;
	use PhpAmqpLib\Message\AMQPMessage;

	$connection = new AMQPStreamConnection($rabbitmq['server'], $rabbitmq['port'], $rabbitmq['username'], $rabbitmq['password'], $rabbitmq['vhost']);
	$channel = $connection->channel();

	$channel->exchange_declare('events', 'topic', false, false, false);

	$msg = new AMQPMessage(json_encode(['event' => 'run-instance', 'instance' => $instanceid]));
	$channel->basic_publish($msg, 'events', 'event.run-instance');

	echo 'OK', "\n";
