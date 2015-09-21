<?php
namespace Kudos;

class MyBot {

    private $params = array();
    private $context = array();
    private $wsUrl;

    public function setToken($token) {
        $this->params = array('token' => $token);
    }

    public function run() {
        if (!isset($this->params['token'])) {
            throw new \Exception('A token must be set. Please see https://my.slack.com/services/new/bot');
        }

        $this->init();
        $logger = new \Zend\Log\Logger();
        $writer = new \Zend\Log\Writer\Stream("php://output");
        $logger->addWriter($writer);

        $loop = \React\EventLoop\Factory::create();
        $client = new \Devristo\Phpws\Client\WebSocket($this->wsUrl, $loop, $logger);

        $client->on("request", function($headers) use ($logger){
                $logger->notice("Request object created!");
        });

        $client->on("handshake", function() use ($logger) {
                $logger->notice("Handshake received!");
        });

        $client->on("connect", function() use ($logger, $client){
                $logger->notice("Connected!");
        });

        $client->on("message", function($message) use ($client, $logger){
            $data = $message->getData();
            $logger->notice("Got message: ".$data);
            $data = json_decode($data, true);

            //$data['text'] is the message
            $response = '';
            //print_r($this->context['self']);

            if (isset($data['text'])) {
				$isim = false;
				foreach($this->context['ims'] as $im) {
					if(isset($data['channel']) && $data['channel'] == $im['id'] && $data['user'] == $im['user']) {
						$isim = true;
					}
				}
				if($isim || strpos($data['text'], '<@'.$this->context['self']['id'].'>') === 0) {
					if(!$isim) {
						$p = trim(substr($data['text'], strlen('<@'.$this->context['self']['id'].'>:')));
					} else {
						$p = $data['text'];
					}
					//$p = "<@ID> did something awesome"
					//$p = "<@ID>: did something awesome"
					//we need to find out if $p starts with an <@ID>
					$e = explode(" ", $p, 2);
					$e[0] = trim($e[0], ":");

					$found = false;
					foreach($this->context['users'] as $user) {
						if($e[0] == "<@".$user['id'].">") {
							$found = $user;
							break;
						}
					}
					if($found) {
						//we need the rest of the message
						$response = "<@{$found['id']}> ".$e[1];
						$sender = false;
						foreach($this->context['users'] as $u) {
							if($data['user'] == $u['id']) {
								$sender = $u['name'];
								break;
							}
						}
					}
				}
				if($response != '') {

					//log it
					$logmsg = date('Y-m-d H:i:s', time())." ".$sender.": ".$user['name'].$data['text'];
					$file = 'log.txt';
					// Open the file to get existing content
					$current = file_get_contents($file);
					// Append a new person to the file
					$current .= $logmsg."\n";
					// Write the contents back to the file
					file_put_contents($file, $current);

					$client->send(
						json_encode(
							array(
								'id' => time(),
								'type' => 'message',
								'channel' => 'G0B00ME9Y',//$data['channel'],
								'text' => $response
							)
						)
					);

					$client->send(
						json_encode(
							array(
								'id' => time(),
								'type' => 'message',
								'channel' => 'G0B00ME9Y',//'G0B00ME9Y' (kudostest),//$data['channel'],
								'text' => 'way to go '.$user['profile']['first_name'].'! :clap:',
							)
						)
					);
				}
            }
        });

        $client->open();

        $loop->run();
    }

    private function init() {
        $url = 'https://slack.com/api/rtm.start';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($this->params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, true);
        $this->context = $response;
        if (isset($response['error'])) {
            throw new \Exception($response['error']);
        }
        $this->wsUrl = $response['url'];
    }

}
