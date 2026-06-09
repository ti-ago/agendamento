<?php
require_once('includes/security.php');
configurarSessao();

$_SESSION = [];
session_destroy();

header("Location: index.php");
