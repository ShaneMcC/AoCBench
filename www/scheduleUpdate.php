<?php
	require_once(__DIR__ . '/functions.php');

	if (!$enableScheduledUpdates) { die('FAIL'. "\n"); }

	use PhpAmqpLib\Connection\AMQPStreamConnection;
	use PhpAmqpLib\Message\AMQPMessage;

	$connection = new AMQPStreamConnection($rabbitmq['server'], $rabbitmq['port'], $rabbitmq['username'], $rabbitmq['password'], $rabbitmq['vhost']);
	$channel = $connection->channel();

	$channel->exchange_declare('events', 'topic', false, false, false);

	$msg = new AMQPMessage(json_encode(['event' => 'run-instance', 'instance' => $instanceid]));
	$channel->basic_publish($msg, 'events', 'event.run-instance');

	if (isset($instanceid) && !empty($instanceid) && file_exists($schedulerStateFile)) {
		$schedulerState = json_decode(file_get_contents($schedulerStateFile), true);
		$schedulerState[$instanceid]['time'] = time();
		file_put_contents($schedulerStateFile, json_encode($schedulerState));
	}

	echo 'OK', "\n";
