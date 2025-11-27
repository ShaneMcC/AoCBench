<?php
	require_once(__DIR__ . '/functions.php');

	if (!$enableScheduledUpdates) { die('Scheduled updates not enabled for this instance.'."\n"); }

	use PhpAmqpLib\Connection\AMQPStreamConnection;
	use PhpAmqpLib\Exception\AMQPTimeoutException;

	echo date('r'), ' - Started', "\n";

	$connection = AMQPStreamConnection::create_connection([
		['host' => $rabbitmq['server'], 'port' => $rabbitmq['port'], 'user' => $rabbitmq['username'], 'password' => $rabbitmq['password'], 'vhost' => $rabbitmq['vhost']]
	], [
		'heartbeat' => 60,
		'keepalive' => true
	]);
	$channel = $connection->channel();

	$channel->exchange_declare('events', 'topic', false, false, false);
	[$myQueue] = $channel->queue_declare('', false, false, true, false);
	$channel->queue_bind($myQueue, 'events', '#');
	$channel->basic_consume($myQueue, '', false, true, false, false, function ($msg) {
		$m = json_decode($msg->body, true);
		echo date('r'), ' - Got run request for instanceid: ', $m['instance'], "\n";
		handleScheduledUpdate($m['instance']);
	});

	while ($channel->is_consuming() && $channel->is_open()) {
		try {
			// The wait() call handles heartbeats internally when configured
			$channel->wait(null, false, 30);
		} catch (AMQPTimeoutException $e) {
			// Normal timeout, continue - no messages received
		}
	}

	echo date('r'), ' - Exited', "\n";
