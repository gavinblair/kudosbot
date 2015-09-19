<?php
namespace Kudos;
require 'vendor/autoload.php';

$bot = new MyBot();
include 'config.php';
$bot->setToken($api); // Get your token here https://my.slack.com/services/new/bot
$bot->run();
