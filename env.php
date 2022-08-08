<?php
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__FILE__));
$dotenv->load();