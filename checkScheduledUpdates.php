<?php
	require_once(__DIR__ . '/functions.php');

	use PhpAmqpLib\Connection\AMQPStreamConnection;
	use PhpAmqpLib\Message\AMQPMessage;

	$connection = new AMQPStreamConnection($rabbitmq['server'], $rabbitmq['port'], $rabbitmq['username'], $rabbitmq['password'], $rabbitmq['vhost']);
	$channel = $connection->channel();

	$channel->exchange_declare('events', 'topic', false, false, false);
	[$myQueue] = $channel->queue_declare('', false, false, true, false);
	$channel->queue_bind($myQueue, 'events', '#');
	$channel->basic_consume($myQueue, '', false, true, false, false, function ($msg) {
		$m = json_decode($msg->body, true);
		handleScheduledUpdate($m['instance']);
	});

	while ($channel->is_open()) {
	    $channel->wait();
	}
