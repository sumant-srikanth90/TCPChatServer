<?php

class MsgThread extends Thread
{
    private $id;
	private $holder;
 
    public function __construct($id, Threaded $holder)
    {
        $this->id = $id;		
		$this->holder = $holder;
    }
 
    public function run()
    {			
		// Reading too many times from threaded object causes issues, so we move contents to this array
		$msgsocks = [];
		foreach ($this->holder as $sock)
			$msgsocks[] = $sock;

		// Loop for reading from socket and broadcasting to clients
		do {
			$input = "";
			if (trim(socket_recv($msgsocks[$this->id], $input, 2, MSG_WAITALL)) == false) {
			//if (($input = @trim(socket_read($msgsocks[$this->id], 1024))) == false) {								
				break;
			}
			$u = unpack("n", $input);
			echo "input == " . $u;
			
			if (($input = @trim(socket_read($msgsocks[$this->id], 1024))) == false) {								
				break;
			}
			$date = date('H:i:s');
			
			if ($input == '*shutdown*') {					
				$output = "[" . $date . "] Server Message: Connection Closed!". "\0";			
				$output = pack("v1a" . strlen($output), strlen($output), $output);
				
				// Send this msg to all clients
				foreach($msgsocks as $sock)
					socket_write($sock, $output, strlen($output));				

				break;
			} else {			
				echo "[" . $date . "] Client " . ($this->id + 1) . " Message: " . $input . "\n";				
				
				$output = "[" . $date . "] Client " . ($this->id + 1) . " says: " . $input . "\0";
				$output = pack("v1a" . strlen($output), strlen($output), $output);			

				// Send this msg to all clients
				foreach($msgsocks as $sock)
					socket_write($sock, $output, strlen($output));				
			}			
			
		} while (true);
		
		// Socket is closed by client!
		$date = date('H:i:s');
		echo "[" . $date . "] Server Message: Connection " . ($this->id + 1) . " Closed!\n";				
	}
}

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$address = '192.168.3.124';
$port = 443;

$NUM_CLIENTS = 1;

if (($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    die ("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
}

if (@socket_bind($sock, $address, $port) === false) {
    die ("socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n");	
}

if (@socket_listen($sock, 5) === false) {
    die ("socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n");
}

do {

	$msgsocks = new Threaded();
	 
	// Wait for connections
	foreach (range(1, $NUM_CLIENTS) as $i) {
		echo "Waiting for Client " . $i . "... \n";
		$msgsock = socket_accept($sock) or die ("Failed to accept connection!");    
		$msgsocks[] = $msgsock;
		
		$date = date('H:i:s');
		echo "[" . $date . "] Client " . $i . " Connected! Waiting for others... \n";
			
		$output = "[" . $date . "] Server Message: Client ". $i . " Connected! Waiting for others...". "\0";			
		$output = pack("v1a" . strlen($output), strlen($output), $output);	
		socket_write($msgsock, $output, strlen($output));				 
	}

	// Send 'All connected' message to all clients
	sleep(1);
	$date = date('H:i:s');
	echo "All Clients Connected! \n";
	$output = "[" . $date . "] Server Message: All Clients Connected!". "\0";
	$output = pack("v1a" . strlen($output), strlen($output), $output);
	foreach($msgsocks as $msgsock)
		socket_write($msgsock, $output, strlen($output));
		
	// Worker pool for reading and writing messages
	$workers = [];
	 
	// Initialize and start the threads
	foreach (range(0, $NUM_CLIENTS - 1) as $i) {
		// Pass index and msgsocks
		$workers[$i] = new MsgThread($i, $msgsocks);
		$workers[$i]->start();
	}
	 
	// Wait for connections
	foreach (range(0, $NUM_CLIENTS - 1) as $i) {
		$workers[$i]->join();
	}

} while (true);

?>